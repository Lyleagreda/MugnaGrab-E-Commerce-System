<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

include '../config.php'; // Make sure this file contains your DB connection ($conn)

// Include PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load Composer's autoloader (if you're using Composer)
// require 'vendor/autoload.php';

// Or manually include the PHPMailer files
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// Include TCPDF library for PDF generation
require_once('TCPDF/tcpdf.php');

// Handle status update if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['new_status'];
    $old_status = '';
    
    // Get the current status before updating
    $status_query = "SELECT status, user_id FROM orders WHERE id = ?";
    $stmt = $conn->prepare($status_query);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $status_result = $stmt->get_result();
    if ($status_row = $status_result->fetch_assoc()) {
        $old_status = $status_row['status'];
        $user_id = $status_row['user_id'];
    }
    
    // Update the order status
    $update_query = "UPDATE orders SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("si", $new_status, $order_id);
    
    if ($stmt->execute()) {
        $statusMessage = "Order #$order_id status updated to $new_status";
        $statusType = "success";
        
        // Send email notification if status changed to "shipped"
        if ($new_status === 'shipped' && $old_status !== 'shipped') {
            // Update product stock quantities
            try {
                // Start transaction
                $conn->begin_transaction();
                
                // Get all items in this order
                $items_query = "SELECT product_id, quantity FROM order_items WHERE order_id = ?";
                $stmt = $conn->prepare($items_query);
                $stmt->bind_param("i", $order_id);
                $stmt->execute();
                $items_result = $stmt->get_result();
                
                // Load the products array from the file
                require_once '../data/products.php';
                
                // Create a temporary copy of the products array to work with
                $updated_products = $products;
                
                // Check if all products have enough stock before making any changes
                $insufficient_stock = [];
                
                while ($item = $items_result->fetch_assoc()) {
                    $product_id = $item['product_id'];
                    $ordered_quantity = $item['quantity'];
                    
                    // Find the product in the array
                    $product_index = -1;
                    foreach ($updated_products as $index => $product) {
                        if ($product['id'] == $product_id) {
                            $product_index = $index;
                            break;
                        }
                    }
                    
                    if ($product_index !== -1) {
                        // Check if there's enough stock
                        if ($updated_products[$product_index]['stock'] < $ordered_quantity) {
                            $insufficient_stock[] = [
                                'product_id' => $product_id,
                                'name' => $updated_products[$product_index]['name'],
                                'available' => $updated_products[$product_index]['stock'],
                                'required' => $ordered_quantity
                            ];
                        } else {
                            // Decrease the stock in our temporary array
                            $updated_products[$product_index]['stock'] -= $ordered_quantity;
                        }
                    }
                }
                
                // If any product has insufficient stock, throw an exception
                if (!empty($insufficient_stock)) {
                    $error_message = "Cannot ship order due to insufficient stock for the following products:\n";
                    foreach ($insufficient_stock as $item) {
                        $error_message .= "- {$item['name']} (ID: {$item['product_id']}): Available: {$item['available']}, Required: {$item['required']}\n";
                    }
                    throw new Exception($error_message);
                }
                
                // Reset the result pointer to use it again
                $items_result->data_seek(0);
                
                // All products have enough stock, now update the actual products array
                while ($item = $items_result->fetch_assoc()) {
                    $product_id = $item['product_id'];
                    $ordered_quantity = $item['quantity'];
                    
                    // Find the product in the original array and update it
                    foreach ($products as $index => $product) {
                        if ($product['id'] == $product_id) {
                            $products[$index]['stock'] -= $ordered_quantity;
                            
                            // Update status if stock is low
                            if ($products[$index]['stock'] <= 50) {
                                $products[$index]['status'] = 'Low Stock';
                            }
                            
                            break;
                        }
                    }
                }
                
                // Write the updated products array back to the file
                $file_content = "<?php\n// Sample product data\n\$products = " . var_export($products, true) . ";\n\n";
                file_put_contents('../data/products.php', $file_content);
                
                // Commit transaction
                $conn->commit();
                
                $statusMessage .= " and product stock quantities have been updated.";

                // Include the product sales tracker
                require_once 'product-sales-tracker.php';

                // Get all items in this order for sales tracking
                $sales_items_query = "SELECT product_id, product_name, quantity, price, total FROM order_items WHERE order_id = ?";
                $stmt = $conn->prepare($sales_items_query);
                $stmt->bind_param("i", $order_id);
                $stmt->execute();
                $sales_items_result = $stmt->get_result();
                $sales_items = [];

                while ($item = $sales_items_result->fetch_assoc()) {
                    $sales_items[] = $item;
                }

                // Update product sales data
                $sales_updated = updateProductSalesData($order_id, $sales_items);

                if ($sales_updated) {
                    $statusMessage .= " Sales data has been updated.";
                } else {
                    // Log error but don't stop the process
                    error_log("Failed to update sales data for order #$order_id");
                }

            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                $statusMessage = "Error updating order status: " . $e->getMessage();
                $statusType = "error";
                
                // Return early to prevent sending email
                goto end_of_ship_status;
            }

            // Get user email
            $user_query = "SELECT email, first_name, last_name FROM users WHERE id = ?";
            $stmt = $conn->prepare($user_query);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $user_result = $stmt->get_result();
            
            if ($user_row = $user_result->fetch_assoc()) {
                $user_email = $user_row['email'];
                $user_name = $user_row['first_name'] . ' ' . $user_row['last_name'];
                
                // Generate and send email with PDF receipt
                $email_sent = sendOrderShippedEmail($order_id, $user_email, $user_name);
                
                if ($email_sent) {
                    $statusMessage .= " and email notification sent to customer.";
                } else {
                    $statusMessage .= " but failed to send email notification.";
                }
            }
        }
        // Send email notification if status changed to "delivered"
        else if ($new_status === 'delivered' && $old_status !== 'delivered') {
            // Get user email
            $user_query = "SELECT email, first_name, last_name FROM users WHERE id = ?";
            $stmt = $conn->prepare($user_query);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $user_result = $stmt->get_result();
            
            if ($user_row = $user_result->fetch_assoc()) {
                $user_email = $user_row['email'];
                $user_name = $user_row['first_name'] . ' ' . $user_row['last_name'];
                
                // Generate and send email with PDF receipt
                $email_sent = sendOrderDeliveredEmail($order_id, $user_email, $user_name);
                
                if ($email_sent) {
                    $statusMessage .= " and email notification sent to customer.";
                } else {
                    $statusMessage .= " but failed to send email notification.";
                }
            }
        }
    } else {
        $statusMessage = "Error updating order status: " . $conn->error;
        $statusType = "error";
    }
    
}

end_of_ship_status:

// Function to generate PDF receipt for an order
function generateOrderPDF($order_id) {
    global $conn;
    
    // Get order details
    $order_query = "SELECT o.*, u.first_name, u.last_name, u.email, u.phone 
                    FROM orders o 
                    LEFT JOIN users u ON o.user_id = u.id 
                    WHERE o.id = ?";
    $stmt = $conn->prepare($order_query);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return false;
    }
    
    $order = $result->fetch_assoc();
    
    // Get order items
    $items_query = "SELECT oi.* 
                    FROM order_items oi 
                    WHERE oi.order_id = ?";
    $stmt = $conn->prepare($items_query);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $items_result = $stmt->get_result();
    $items = [];
    
    while ($item = $items_result->fetch_assoc()) {
        $items[] = $item;
    }
    
    // Get shipping address
    $shipping_query = "SELECT * FROM user_addresses WHERE id = ?";
    $stmt = $conn->prepare($shipping_query);
    $stmt->bind_param("i", $order['shipping_address_id']);
    $stmt->execute();
    $shipping_result = $stmt->get_result();
    $shipping = $shipping_result->fetch_assoc();
    
    // If no shipping address found, use default placeholder
    if (!$shipping) {
        $shipping = [
            'address_line1' => 'No shipping address provided',
            'address_line2' => '',
            'city' => '',
            'state' => '',
            'postal_code' => ''
        ];
    }
    
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Mugna Leather Arts');
    $pdf->SetAuthor('Mugna Leather Arts');
    $pdf->SetTitle('Order #' . $order_id . ' Receipt');
    $pdf->SetSubject('Order Receipt');
    
    // Remove header and footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Set default monospaced font
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    
    // Set margins
    $pdf->SetMargins(15, 15, 15);
    
    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, 15);
    
    // Set image scale factor
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', '', 10);
    
    // Company logo and info
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Mugna Leather Arts', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 5, 'Crafted to Endure, Styled to Inspire', 0, 1, 'C');
    $pdf->Cell(0, 5, 'www.mugnaleatherarts.com', 0, 1, 'C');
    $pdf->Cell(0, 5, 'support@mugnaleatherarts.com', 0, 1, 'C');
    $pdf->Ln(10);
    
    // Order information
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'ORDER RECEIPT', 0, 1, 'C');
    $pdf->Ln(5);
    
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(90, 7, 'Order Information:', 0, 0);
    $pdf->Cell(90, 7, 'Customer Information:', 0, 1);
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(90, 7, 'Order #: ' . $order['id'], 0, 0);
    $pdf->Cell(90, 7, 'Name: ' . $order['first_name'] . ' ' . $order['last_name'], 0, 1);
    
    $pdf->Cell(90, 7, 'Date: ' . date('F d, Y', strtotime($order['created_at'])), 0, 0);
    $pdf->Cell(90, 7, 'Email: ' . $order['email'], 0, 1);
    
    $pdf->Cell(90, 7, 'Status: ' . ucfirst($order['status']), 0, 0);
    $pdf->Cell(90, 7, 'Phone: ' . ($order['phone'] ? $order['phone'] : 'Not provided'), 0, 1);
    
    $pdf->Cell(90, 7, 'Payment Method: ' . ucfirst(str_replace('_', ' ', $order['payment_method'])), 0, 1);
    
    $pdf->Ln(5);
    
    // Shipping address
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 7, 'Shipping Address:', 0, 1);
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 7, $shipping['address_line1'], 0, 1);
    if (!empty($shipping['address_line2'])) {
        $pdf->Cell(0, 7, $shipping['address_line2'], 0, 1);
    }
    $pdf->Cell(0, 7, $shipping['city'] . ', ' . $shipping['state'] . ' ' . $shipping['postal_code'], 0, 1);
    
    $pdf->Ln(5);
    
    // Order items
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 7, 'Order Items:', 0, 1);
    $pdf->Ln(2);
    
    // Table header
    $pdf->SetFillColor(240, 240, 240);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(90, 7, 'Product', 1, 0, 'L', true);
    $pdf->Cell(30, 7, 'Price', 1, 0, 'R', true);
    $pdf->Cell(30, 7, 'Quantity', 1, 0, 'C', true);
    $pdf->Cell(30, 7, 'Total', 1, 1, 'R', true);
    
    // Table content
    $pdf->SetFont('helvetica', '', 10);
    foreach ($items as $item) {
        $pdf->Cell(90, 7, $item['product_name'], 1, 0, 'L');
        $pdf->Cell(30, 7, '₱' . number_format($item['price'], 2), 1, 0, 'R');
        $pdf->Cell(30, 7, $item['quantity'], 1, 0, 'C');
        $pdf->Cell(30, 7, '₱' . number_format($item['price'] * $item['quantity'], 2), 1, 1, 'R');
    }
    
    // Order summary
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 7, 'Order Summary:', 0, 1);
    $pdf->Ln(2);
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(150, 7, 'Subtotal:', 0, 0, 'R');
    $pdf->Cell(30, 7, '₱' . number_format($order['subtotal'], 2), 0, 1, 'R');
    
    $shipping_fee = $order['total'] - $order['subtotal'];
    $pdf->Cell(150, 7, 'Shipping Fee:', 0, 0, 'R');
    $pdf->Cell(30, 7, '₱' . number_format($shipping_fee, 2), 0, 1, 'R');
    
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(150, 7, 'Total:', 0, 0, 'R');
    $pdf->Cell(30, 7, '₱' . number_format($order['total'], 2), 0, 1, 'R');
    
    // Thank you note
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'I', 10);
    $pdf->Cell(0, 7, 'Thank you for shopping with Mugna Leather Arts!', 0, 1, 'C');
    $pdf->Cell(0, 7, 'If you have any questions, please contact our customer support.', 0, 1, 'C');
    
    // Output the PDF as a string
    return $pdf->Output('order_' . $order_id . '.pdf', 'S');
}

// Function to send order shipped email with PDF receipt
function sendOrderShippedEmail($order_id, $email, $name) {
    try {
        // Create a new PHPMailer instance
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->SMTPDebug = 0;                      // Enable verbose debug output (set to 2 for debugging)
        $mail->isSMTP();                           // Send using SMTP
        $mail->Host       = 'smtp.gmail.com';      // SMTP server
        $mail->SMTPAuth   = true;                  // Enable SMTP authentication
        $mail->Username   = 'dharzdesu@gmail.com'; // SMTP username
        $mail->Password   = 'pwwv gwns hnyo bztl';  // SMTP password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Enable TLS encryption
        $mail->Port       = 587;                  // TCP port to connect to

        // Recipients
        $mail->setFrom('dhardesu@gmail.com', 'Mugna Leather Arts');
        $mail->addAddress($email, $name);                 // Add a recipient
        
        // Generate PDF receipt
        $pdf_content = generateOrderPDF($order_id);
        
        if ($pdf_content === false) {
            return false;
        }
        
        // Attachments
        $mail->addStringAttachment($pdf_content, 'Order_' . $order_id . '_Receipt.pdf');
        
        // Content
        $mail->isHTML(true);                       // Set email format to HTML
        $mail->Subject = 'Your Mugna Leather Arts Order #' . $order_id . ' has been Shipped!';
        
        // Email body
        $mail->Body = '
        <html>
        <head>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                }
                .container {
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                }
                .header {
                    background-color: #2563eb;
                    color: white;
                    padding: 20px;
                    text-align: center;
                }
                .content {
                    padding: 20px;
                    background-color: #f8fafc;
                }
                .footer {
                    text-align: center;
                    margin-top: 20px;
                    font-size: 12px;
                    color: #64748b;
                }
                .button {
                    display: inline-block;
                    background-color: #2563eb;
                    color: white;
                    padding: 10px 20px;
                    text-decoration: none;
                    border-radius: 5px;
                    margin-top: 20px;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Your Order Has Been Shipped!</h1>
                </div>
                <div class="content">
                    <p>Dear ' . $name . ',</p>
                    <p>Great news! Your order <strong>#' . $order_id . '</strong> has been shipped and is on its way to you.</p>
                    <p>We\'ve attached a PDF receipt of your order for your records.</p>
                    <p>You can track your order status by logging into your account or using the tracking link below.</p>
                    <p>If you have any questions or concerns about your order, please don\'t hesitate to contact our customer support team.</p>
                    <p>Thank you for shopping with Mugna Leather Arts!</p>
                    <a href="https://www.mugnaleatherarts.com/track-order?id=' . $order_id . '" class="button">Track Your Order</a>
                </div>
                <div class="footer">
                    <p>© ' . date('Y') . ' Mugna Leather Arts. All rights reserved.</p>
                    <p>This is an automated email, please do not reply to this message.</p>
                </div>
            </div>
        </body>
        </html>
        ';
        
        // Plain text version for non-HTML mail clients
        $mail->AltBody = 'Dear ' . $name . ',
        
Great news! Your order #' . $order_id . ' has been shipped and is on its way to you.

We\'ve attached a PDF receipt of your order for your records.

You can track your order status by logging into your account or using the tracking link below.

If you have any questions or concerns about your order, please don\'t hesitate to contact our customer support team.

Thank you for shopping with Mugna Leather Arts!

To track your order, visit: https://www.mugnaleatherarts.com/track-order?id=' . $order_id . '

© ' . date('Y') . ' Mugna Leather Arts. All rights reserved.
This is an automated email, please do not reply to this message.';
        
        // Send the email
        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log the error
        error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

// Function to send order delivered email with PDF receipt
function sendOrderDeliveredEmail($order_id, $email, $name) {
    try {
        // Create a new PHPMailer instance
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->SMTPDebug = 0;                      // Enable verbose debug output (set to 2 for debugging)
        $mail->isSMTP();                           // Send using SMTP
        $mail->Host       = 'smtp.gmail.com';      // SMTP server
        $mail->SMTPAuth   = true;                  // Enable SMTP authentication
        $mail->Username   = 'dharzdesu@gmail.com'; // SMTP username
        $mail->Password   = 'pwwv gwns hnyo bztl';  // SMTP password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Enable TLS encryption
        $mail->Port       = 587;                  // TCP port to connect to

        // Recipients
        $mail->setFrom('dharzdesu@gmail.com', 'Mugna Leather Arts');
        $mail->addAddress($email, $name);                 // Add a recipient
        
        // Generate PDF receipt
        $pdf_content = generateOrderPDF($order_id);
        
        if ($pdf_content === false) {
            return false;
        }
        
        // Attachments  
        $mail->addStringAttachment($pdf_content, 'Order_' . $order_id . '_Receipt.pdf');
        
        // Content
        $mail->isHTML(true);                       // Set email format to HTML
        $mail->Subject = 'Your Mugna Leather Arts Order #' . $order_id . ' has been Delivered!';
        
        // Email body
        $mail->Body = '
        <html>
        <head>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                }
                .container {
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                }
                .header {
                    background-color: #2563eb;
                    color: white;
                    padding: 20px;
                    text-align: center;
                }
                .content {
                    padding: 20px;
                    background-color: #f8fafc;
                }
                .footer {
                    text-align: center;
                    margin-top: 20px;
                    font-size: 12px;
                    color: #64748b;
                }
                .button {
                    display: inline-block;
                    background-color: #2563eb;
                    color: white;
                    padding: 10px 20px;
                    text-decoration: none;
                    border-radius: 5px;
                    margin-top: 20px;
                }
                .highlight {
                    background-color: #dcfce7;
                    padding: 15px;
                    border-radius: 5px;
                    margin: 15px 0;
                    border-left: 4px solid #10b981;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Your Order Has Been Delivered!</h1>
                </div>
                <div class="content">
                    <p>Dear ' . $name . ',</p>
                    <p>Great news! Your order <strong>#' . $order_id . '</strong> has been delivered to your shipping address.</p>
                    <div class="highlight">
                        <p><strong>Please mark your order as "Received"</strong> once you have verified all items are in good condition.</p>
                    </div>
                    <p>We\'ve attached a PDF receipt of your order for your records.</p>
                    <p>If you have any questions or concerns about your order, please don\'t hesitate to contact our customer support team.</p>
                    <p>Thank you for shopping with Mugna Leather Arts!</p>
                    <a href="https://www.mugnaleatherarts.com/my-orders?id=' . $order_id . '" class="button">View Your Order</a>
                </div>
                <div class="footer">
                    <p>© ' . date('Y') . ' Mugna Leather Arts. All rights reserved.</p>
                    <p>This is an automated email, please do not reply to this message.</p>
                </div>
            </div>
        </body>
        </html>
        ';
        
        // Plain text version for non-HTML mail clients
        $mail->AltBody = 'Dear ' . $name . ',
        
Great news! Your order #' . $order_id . ' has been delivered to your shipping address.

IMPORTANT: Please mark your order as "Received" once you have verified all items are in good condition.

We\'ve attached a PDF receipt of your order for your records.

If you have any questions or concerns about your order, please don\'t hesitate to contact our customer support team.

Thank you for shopping with Mugna Leather Arts!

To view your order, visit: https://www.mugnaleatherarts.com/my-orders?id=' . $order_id . '

© ' . date('Y') . ' Mugna Leather Arts. All rights reserved.
This is an automated email, please do not reply to this message.';
        
        // Send the email
        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log the error
        error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build the query with filters
$query = "SELECT o.*, u.first_name, u.last_name, u.email, u.phone 
          FROM orders o 
          LEFT JOIN users u ON o.user_id = u.id 
          WHERE 1=1";

$params = [];
$types = "";

if (!empty($status_filter)) {
    $query .= " AND o.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($date_from)) {
    $query .= " AND DATE(o.created_at) >= ?";
    $params[] = $date_from;
    $types .= "s";
}

if (!empty($date_to)) {
    $query .= " AND DATE(o.created_at) <= ?";
    $params[] = $date_to;
    $types .= "s";
}

if (!empty($search)) {
    $search_term = "%$search%";
    $query .= " AND (o.id LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ssss";
}

// Add sorting
$query .= " ORDER BY o.created_at DESC";

// Prepare and execute the query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$orders = $result->fetch_all(MYSQLI_ASSOC);

// Get order statuses for filter dropdown
$status_query = "SELECT DISTINCT status FROM orders ORDER BY 
                CASE 
                    WHEN status = 'pending' THEN 1
                    WHEN status = 'processing' THEN 2
                    WHEN status = 'shipped' THEN 3
                    WHEN status = 'delivered' THEN 4
                    WHEN status = 'received' THEN 5
                    WHEN status = 'cancelled' THEN 6
                    ELSE 7
                END";
$status_result = $conn->query($status_query);
$statuses = [];
while ($row = $status_result->fetch_assoc()) {
    $statuses[] = $row['status'];
}

// Page title
$pageTitle = 'Orders';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - Mugna Admin</title>
    <link rel="stylesheet" href="css/admin-styles.css">
    <link rel="shortcut icon" href="../images/mugna-icon.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Modern UI Styles */
        :root {
            --primary-color: #2563eb;
            --primary-light: #3b82f6;
            --primary-dark: #1d4ed8;
            --primary-bg: rgba(37, 99, 235, 0.05);
            --success-color: #10b981;
            --success-bg: rgba(16, 185, 129, 0.05);
            --warning-color: #f59e0b;
            --warning-bg: rgba(245, 158, 11, 0.05);
            --danger-color: #ef4444;
            --danger-bg: rgba(239, 68, 68, 0.05);
            --info-color: #0ea5e9;
            --info-bg: rgba(14, 165, 233, 0.05);
            --light-bg: #f8fafc;
            --card-bg: #ffffff;
            --border-color: #e2e8f0;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --text-muted: #94a3b8;
            --border-radius: 0.75rem;
            --input-radius: 0.5rem;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --transition: all 0.3s ease;
            --transition-fast: all 0.15s ease;
        }

        body {
            background-color: var(--light-bg);
            color: var(--text-primary);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .content-wrapper {
            padding: 2rem;
        }

        .content-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
        }

        .content-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
            letter-spacing: -0.025em;
        }

        /* Filter Section */
        .filter-section {
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: var(--input-radius);
            background-color: var(--light-bg);
            color: var(--text-primary);
            font-size: 0.875rem;
            transition: var(--transition-fast);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .filter-buttons {
            display: flex;
            gap: 0.75rem;
            align-items: flex-end;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.5rem;
            border-radius: var(--input-radius);
            font-weight: 600;
            font-size: 0.875rem;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            box-shadow: 0 4px 6px rgba(37, 99, 235, 0.2);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary-color));
            transform: translateY(-2px);
            box-shadow: 0 6px 8px rgba(37, 99, 235, 0.25);
        }

        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
        }

        .btn-outline:hover {
            background-color: var(--light-bg);
            border-color: var(--text-secondary);
            color: var(--text-primary);
        }

        /* Orders Table */
        .orders-table-wrapper {
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            border: 1px solid var(--border-color);
        }

        .orders-table {
            width: 100%;
            border-collapse: collapse;
        }

        .orders-table th,
        .orders-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .orders-table th {
            background-color: var(--light-bg);
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .orders-table tbody tr {
            transition: var(--transition-fast);
        }

        .orders-table tbody tr:hover {
            background-color: var(--primary-bg);
        }

        .orders-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Status Badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: capitalize;
        }

        .status-pending {
            background-color: var(--warning-bg);
            color: var(--warning-color);
        }

        .status-processing {
            background-color: var(--info-bg);
            color: var(--info-color);
        }

        .status-shipped {
            background-color: var(--primary-bg);
            color: var(--primary-color);
        }

        .status-delivered {
            background-color: var(--success-bg);
            color: var(--success-color);
        }

        .status-received {
            background-color: var(--success-bg);
            color: var(--success-color);
        }

        .status-cancelled {
            background-color: var(--danger-bg);
            color: var(--danger-color);
        }

        /* Action Buttons */
        .action-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background-color: var(--light-bg);
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
            cursor: pointer;
            transition: var(--transition-fast);
            margin-right: 0.5rem;
        }

        .action-btn:hover {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .action-btn.view-btn:hover {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .action-btn.edit-btn:hover {
            background-color: var(--warning-color);
            border-color: var(--warning-color);
        }

        .action-btn.delete-btn:hover {
            background-color: var(--danger-color);
            border-color: var(--danger-color);
        }

        /* Order Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .modal.active {
            display: block;
            opacity: 1;
        }

        .modal-content {
            background-color: var(--card-bg);
            margin: 2% auto;
            width: 90%;
            max-width: 1000px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
            position: relative;
            transform: translateY(-20px);
            transition: transform 0.3s ease;
            overflow: hidden;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
        }

        .modal.active .modal-content {
            transform: translateY(0);
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
        }

        .modal-close {
            font-size: 1.5rem;
            font-weight: bold;
            color: white;
            cursor: pointer;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.2);
            transition: var(--transition-fast);
        }

        .modal-close:hover {
            background-color: rgba(255, 255, 255, 0.3);
        }

        .modal-body {
            padding: 1.5rem;
            overflow-y: auto;
        }

        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        /* Order Details */
        .order-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .order-info-card {
            background-color: var(--light-bg);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            border: 1px solid var(--border-color);
        }

        .order-info-card h3 {
            font-size: 1rem;
            font-weight: 600;
            margin-top: 0;
            margin-bottom: 1rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .order-info-card h3 i {
            color: var(--primary-color);
        }

        .info-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .info-list li {
            display: flex;
            margin-bottom: 0.75rem;
            font-size: 0.875rem;
        }

        .info-list li:last-child {
            margin-bottom: 0;
        }

        .info-label {
            font-weight: 500;
            color: var(--text-secondary);
            width: 40%;
            flex-shrink: 0;
        }

        .info-value {
            color: var(--text-primary);
            font-weight: 500;
        }

        /* Order Items */
        .order-items {
            margin-bottom: 1.5rem;
        }

        .order-items h3 {
            font-size: 1rem;
            font-weight: 600;
            margin-top: 0;
            margin-bottom: 1rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .order-items h3 i {
            color: var(--primary-color);
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            overflow: hidden;
        }

        .items-table th,
        .items-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.875rem;
        }

        .items-table th {
            background-color: var(--light-bg);
            font-weight: 600;
            color: var(--text-secondary);
        }

        .items-table tbody tr:last-child td {
            border-bottom: none;
        }

        .product-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .product-image {
            width: 50px;
            height: 50px;
            border-radius: var(--input-radius);
            object-fit: cover;
            background-color: var(--light-bg);
            border: 1px solid var(--border-color);
        }

        .product-name {
            font-weight: 500;
            color: var(--text-primary);
        }

        .product-price,
        .product-quantity,
        .product-total {
            font-weight: 500;
        }

        /* Order Summary */
        .order-summary {
            background-color: var(--light-bg);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            border: 1px solid var(--border-color);
            margin-bottom: 1.5rem;
        }

        .order-summary h3 {
            font-size: 1rem;
            font-weight: 600;
            margin-top: 0;
            margin-bottom: 1rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .order-summary h3 i {
            color: var(--primary-color);
        }

        .summary-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .summary-list li {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            font-size: 0.875rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px dashed var(--border-color);
        }

        .summary-list li:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
            font-weight: 700;
            font-size: 1rem;
            color: var(--primary-color);
        }

        .summary-label {
            font-weight: 500;
            color: var(--text-secondary);
        }

        .summary-value {
            font-weight: 500;
            color: var(--text-primary);
        }

        /* Payment Screenshot */
        .payment-screenshot {
            background-color: var(--light-bg);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            border: 1px solid var(--border-color);
            margin-bottom: 1.5rem;
        }

        .payment-screenshot h3 {
            font-size: 1rem;
            font-weight: 600;
            margin-top: 0;
            margin-bottom: 1rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .payment-screenshot h3 i {
            color: var(--primary-color);
        }

        .screenshot-container {
            text-align: center;
        }

        .screenshot-image {
            max-width: 100%;
            max-height: 400px;
            border-radius: var(--input-radius);
            border: 1px solid var(--border-color);
            cursor: pointer;
            transition: var(--transition);
        }

        .screenshot-image:hover {
            transform: scale(1.02);
            box-shadow: var(--shadow-md);
        }

        /* Status Update Form */
        .status-update-form {
            background-color: var(--light-bg);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            border: 1px solid var(--border-color);
        }

        .status-update-form h3 {
            font-size: 1rem;
            font-weight: 600;
            margin-top: 0;
            margin-bottom: 1rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-update-form h3 i {
            color: var(--primary-color);
        }

        .status-form {
            display: flex;
            gap: 1rem;
            align-items: flex-end;
        }

        .status-form .form-group {
            flex: 1;
            margin-bottom: 0;
        }

        /* Alert Styles */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideDown 0.3s ease-out forwards;
            box-shadow: var(--shadow);
            border-left: 4px solid;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background-color: var(--success-bg);
            color: var(--success-color);
            border-left-color: var(--success-color);
        }

        .alert-error {
            background-color: var(--danger-bg);
            color: var(--danger-color);
            border-left-color: var(--danger-color);
        }

        .alert-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .alert-success .alert-icon {
            background-color: var(--success-color);
            color: white;
        }

        .alert-error .alert-icon {
            background-color: var(--danger-color);
            color: white;
        }

        .alert-content {
            flex: 1;
            font-weight: 500;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1.5rem;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--border-color);
        }

        .empty-state h3 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .empty-state p {
            margin-bottom: 1.5rem;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .content-wrapper {
                padding: 1.5rem;
            }
            
            .content-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .filter-buttons {
                flex-direction: column;
                width: 100%;
            }
            
            .filter-buttons .btn {
                width: 100%;
            }
            
            .orders-table {
                display: block;
                overflow-x: auto;
            }
            
            .modal-content {
                width: 95%;
                margin: 5% auto;
            }
            
            .order-details {
                grid-template-columns: 1fr;
            }
            
            .status-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .status-form .form-group {
                margin-bottom: 1rem;
            }
        }

        /* Lightbox for Payment Screenshot */
        .lightbox {
            display: none;
            position: fixed;
            z-index: 1100;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.9);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .lightbox.active {
            display: flex;
            opacity: 1;
            align-items: center;
            justify-content: center;
        }

        .lightbox-content {
            max-width: 90%;
            max-height: 90%;
        }

        .lightbox-image {
            display: block;
            max-width: 100%;
            max-height: 90vh;
            margin: auto;
            border-radius: 4px;
        }

        .lightbox-close {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 30px;
            color: white;
            cursor: pointer;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background-color: rgba(0, 0, 0, 0.5);
            transition: var(--transition-fast);
        }

        .lightbox-close:hover {
            background-color: rgba(0, 0, 0, 0.8);
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 2rem;
            gap: 0.5rem;
        }

        .pagination-btn {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--input-radius);
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            font-weight: 500;
            transition: var(--transition-fast);
            cursor: pointer;
        }

        .pagination-btn:hover {
            background-color: var(--light-bg);
            color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .pagination-btn.active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .pagination-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <?php include 'includes/header.php'; ?>

            <!-- Orders Content -->
            <div class="content-wrapper">
                <div class="content-header">
                    <h1>Manage Orders</h1>
                </div>

                <?php if (isset($statusMessage)): ?>
                <div class="alert alert-<?php echo $statusType; ?>">
                    <div class="alert-icon">
                        <i class="fas fa-<?php echo $statusType === 'success' ? 'check' : 'exclamation'; ?>"></i>
                    </div>
                    <div class="alert-content">
                        <?php echo $statusMessage; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Filter Section -->
                <div class="filter-section">
                    <form action="" method="GET" class="filter-form">
                        <div class="form-group">
                            <label for="status">Order Status</label>
                            <select name="status" id="status" class="form-control">
                                <option value="">All Statuses</option>
                                <?php foreach ($statuses as $status): ?>
                                    <option value="<?php echo $status; ?>" <?php echo $status_filter === $status ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($status); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="date_from">Date From</label>
                            <input type="date" name="date_from" id="date_from" class="form-control" value="<?php echo $date_from; ?>">
                        </div>
                        <div class="form-group">
                            <label for="date_to">Date To</label>
                            <input type="date" name="date_to" id="date_to" class="form-control" value="<?php echo $date_to; ?>">
                        </div>
                        <div class="form-group">
                            <label for="search">Search</label>
                            <input type="text" name="search" id="search" class="form-control" placeholder="Order ID, Customer, Email..." value="<?php echo $search; ?>">
                        </div>
                        <div class="filter-buttons">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                            <a href="admin-orders.php" class="btn btn-outline">
                                <i class="fas fa-redo"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Orders Table -->
                <div class="orders-table-wrapper">
                    <?php if (count($orders) > 0): ?>
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Total</th>
                                <th>Payment</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>#<?php echo $order['id']; ?></td>
                                <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                <td>
                                    <?php echo $order['first_name'] . ' ' . $order['last_name']; ?><br>
                                    <small class="text-muted"><?php echo $order['email']; ?></small>
                                </td>
                                <td>₱<?php echo number_format($order['total'], 2); ?></td>
                                <td><?php echo ucfirst(str_replace('_', ' ', $order['payment_method'])); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($order['status']); ?>">
                                        <?php echo $order['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="action-btn view-btn" onclick="viewOrder(<?php echo $order['id']; ?>)" title="View Order">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-shopping-cart"></i>
                        <h3>No Orders Found</h3>
                        <p>There are no orders matching your filter criteria. Try adjusting your filters or check back later.</p>
                        <a href="admin-orders.php" class="btn btn-primary">
                            <i class="fas fa-redo"></i> Reset Filters
                        </a>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Pagination -->

            </div>
        </main>
    </div>

    <!-- Order View Modal -->
    <div id="orderModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Order #<span id="modalOrderId"></span></h2>
                <span class="modal-close">&times;</span>
            </div>
            <div class="modal-body">
                <div id="orderDetails"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal()">Close</button>
                <button class="btn btn-primary" onclick="printOrder()">
                    <i class="fas fa-print"></i> Print Order
                </button>
            </div>
        </div>
    </div>

    <!-- Lightbox for Payment Screenshot -->
    <div id="screenshotLightbox" class="lightbox">
        <span class="lightbox-close">&times;</span>
        <div class="lightbox-content">
            <img id="lightboxImage" class="lightbox-image" src="/placeholder.svg" alt="Payment Screenshot">
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Modal functionality
            const modal = document.getElementById('orderModal');
            const modalClose = document.querySelector('.modal-close');
            
            modalClose.addEventListener('click', closeModal);
            
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    closeModal();
                }
            });
            
            // Lightbox functionality
            const lightbox = document.getElementById('screenshotLightbox');
            const lightboxClose = document.querySelector('.lightbox-close');
            
            lightboxClose.addEventListener('click', function() {
                lightbox.classList.remove('active');
            });
            
            window.addEventListener('click', function(event) {
                if (event.target === lightbox) {
                    lightbox.classList.remove('active');
                }
            });

            // Live filter functionality
            const filterForm = document.querySelector('.filter-form');
            const filterInputs = filterForm.querySelectorAll('select, input[type="date"], input[type="text"]');
            
            filterInputs.forEach(input => {
                input.addEventListener('change', function() {
                    filterForm.submit();
                });
                
                // For text input, add debounce for typing
                if (input.type === 'text') {
                    let debounceTimer;
                    input.addEventListener('input', function() {
                        clearTimeout(debounceTimer);
                        debounceTimer = setTimeout(() => {
                            filterForm.submit();
                        }, 500); // Wait 500ms after user stops typing
                    });
                }
            });
            
            // Hide the filter buttons since we're using live filtering
            document.querySelector('.filter-buttons').style.display = 'none';
        });
        
        // Close modal function
        function closeModal() {
            const modal = document.getElementById('orderModal');
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
        
        // View order function
        function viewOrder(orderId) {
            const modal = document.getElementById('orderModal');
            const modalOrderId = document.getElementById('modalOrderId');
            const orderDetails = document.getElementById('orderDetails');
            
            // Set order ID in modal title
            modalOrderId.textContent = orderId;
            
            // Show loading state
            orderDetails.innerHTML = '<div style="text-align: center; padding: 2rem;"><i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary-color);"></i><p>Loading order details...</p></div>';
            
            // Show modal
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
            
            // Fetch order details
            fetch('get_order_details.php?order_id=' + orderId)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        orderDetails.innerHTML = `<div class="alert alert-error"><div class="alert-icon"><i class="fas fa-exclamation"></i></div><div class="alert-content">${data.error}</div></div>`;
                        return;
                    }
                    
                    // Format order details HTML
                    let html = `
                        <div class="order-details">
                            <div class="order-info-card">
                                <h3><i class="fas fa-info-circle"></i> Order Information</h3>
                                <ul class="info-list">
                                    <li>
                                        <span class="info-label">Order ID:</span>
                                        <span class="info-value">#${data.order.id}</span>
                                    </li>
                                    <li>
                                        <span class="info-label">Date:</span>
                                        <span class="info-value">${formatDate(data.order.created_at)}</span>
                                    </li>
                                    <li>
                                        <span class="info-label">Status:</span>
                                        <span class="info-value">
                                            <span class="status-badge status-${data.order.status.toLowerCase()}">${data.order.status}</span>
                                        </span>
                                    </li>
                                    <li>
                                        <span class="info-label">Payment Method:</span>
                                        <span class="info-value">${formatPaymentMethod(data.order.payment_method)}</span>
                                    </li>
                                </ul>
                            </div>
                            
                            <div class="order-info-card">
                                <h3><i class="fas fa-user"></i> Customer Information</h3>
                                <ul class="info-list">
                                    <li>
                                        <span class="info-label">Name:</span>
                                        <span class="info-value">${data.customer.first_name} ${data.customer.last_name}</span>
                                    </li>
                                    <li>
                                        <span class="info-label">Email:</span>
                                        <span class="info-value">${data.customer.email}</span>
                                    </li>
                                    <li>
                                        <span class="info-label">Phone:</span>
                                        <span class="info-value">${data.customer.phone || 'Not provided'}</span>
                                    </li>
                                </ul>
                            </div>
                            
                            <div class="order-info-card">
                                <h3><i class="fas fa-map-marker-alt"></i> Shipping Address</h3>
                                <ul class="info-list">
                                    <li>
                                        <span class="info-label">Address:</span>
                                        <span class="info-value">${data.shipping.address_line1}</span>
                                    </li>
                                    ${data.shipping.address_line2 ? `
                                    <li>
                                        <span class="info-label"></span>
                                        <span class="info-value">${data.shipping.address_line2}</span>
                                    </li>
                                    ` : ''}
                                    <li>
                                        <span class="info-label">City:</span>
                                        <span class="info-value">${data.shipping.city}</span>
                                    </li>
                                    <li>
                                        <span class="info-label">State/Province:</span>
                                        <span class="info-value">${data.shipping.state}</span>
                                    </li>
                                    <li>
                                        <span class="info-label">Postal Code:</span>
                                        <span class="info-value">${data.shipping.postal_code}</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="order-items">
                            <h3><i class="fas fa-shopping-cart"></i> Order Items</h3>
                            <table class="items-table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Price</th>
                                        <th>Quantity</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                    `;
                    
                    // Add order items
                    data.items.forEach(item => {
                        html += `
                            <tr>
                                <td>
                                    <div class="product-info">
                                        <img src="${item.product_image || 'images/placeholder.jpg'}" alt="${item.product_name}" class="product-image">
                                        <span class="product-name">${item.product_name}</span>
                                    </div>
                                </td>
                                <td class="product-price">₱${formatPrice(item.price)}</td>
                                <td class="product-quantity">${item.quantity}</td>
                                <td class="product-total">₱${formatPrice(item.price * item.quantity)}</td>
                            </tr>
                        `;
                    });
                    
                    html += `
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="order-summary">
                            <h3><i class="fas fa-calculator"></i> Order Summary</h3>
                            <ul class="summary-list">
                                <li>
                                    <span class="summary-label">Subtotal:</span>
                                    <span class="summary-value">₱${formatPrice(data.order.subtotal)}</span>
                                </li>
                                <li>
                                    <span class="summary-label">Shipping Fee:</span>
                                    <span class="summary-value">₱${formatPrice(data.order.total - data.order.subtotal)}</span>
                                </li>
                                <li>
                                    <span class="summary-label">Total:</span>
                                    <span class="summary-value">₱${formatPrice(data.order.total)}</span>
                                </li>
                            </ul>
                        </div>
                    `;
                    
                    // Add payment screenshot if available and not COD
                    if (data.order.payment_method !== 'cash_on_delivery' && data.order.payment_screenshot) {
                        html += `
                            <div class="payment-screenshot">
                                <h3><i class="fas fa-receipt"></i> Payment Screenshot</h3>
                                <div class="screenshot-container">
                                    <img src="../${data.order.payment_screenshot}" alt="Payment Screenshot" class="screenshot-image" onclick="openLightbox('../${data.order.payment_screenshot}')">
                                </div>
                            </div>
                        `;
                    }
                    

                    // Add status update form with conditional options based on current status
                    if (data.order.status === 'cancelled') {
                        html += `
                            <div class="status-update-form">
                                <h3><i class="fas fa-edit"></i> Order Status</h3>
                                <div class="alert alert-error" style="margin-bottom: 0;">
                                    <div class="alert-icon">
                                        <i class="fas fa-exclamation-triangle"></i>
                                    </div>
                                    <div class="alert-content">
                                        This order has been cancelled and cannot be updated.
                                    </div>
                                </div>
                            </div>
                        `;
                    } else {
                        let statusOptions = '';
                        
                        if (data.order.status === 'pending') {
                            statusOptions = `<option value="processing">Processing</option>`;
                        } else if (data.order.status === 'processing') {
                            statusOptions = `<option value="shipped">Shipped</option>`;
                        } else if (data.order.status === 'shipped') {
                            statusOptions = `<option value="delivered">Delivered</option>`;
                        } else if (data.order.status === 'delivered') {
                            statusOptions = `<option value="received">Received</option>`;
                        } else if (data.order.status === 'received') {
                            statusOptions = `<option value="received" selected>Received</option>`;
                        }
                        
                        if (statusOptions) {
                            html += `
                                <div class="status-update-form">
                                    <h3><i class="fas fa-edit"></i> Update Order Status</h3>
                                    <form action="" method="POST" class="status-form">
                                        <div class="form-group">
                                            <label for="new_status">New Status</label>
                                            <select name="new_status" id="new_status" class="form-control">
                                                <option value="${data.order.status}" selected>${data.order.status.charAt(0).toUpperCase() + data.order.status.slice(1)}</option>
                                                ${statusOptions}
                                            </select>
                                        </div>
                                        <input type="hidden" name="order_id" value="${data.order.id}">
                                        <button type="submit" name="update_status" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Update Status
                                        </button>
                                    </form>
                                </div>
                            `;
                        } else {
                            html += `
                                <div class="status-update-form">
                                    <h3><i class="fas fa-edit"></i> Order Status</h3>
                                    <div class="alert alert-success" style="margin-bottom: 0;">
                                        <div class="alert-icon">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                        <div class="alert-content">
                                            This order is in its final state and cannot be updated further.
                                        </div>
                                    </div>
                                </div>
                            `;
                        }
                    }
                    
                    // Update order details in modal
                    orderDetails.innerHTML = html;
                })
                .catch(error => {
                    console.error('Error fetching order details:', error);
                    orderDetails.innerHTML = `<div class="alert alert-error"><div class="alert-icon"><i class="fas fa-exclamation"></i></div><div class="alert-content">Error loading order details. Please try again.</div></div>`;
                });
        }
        
        // Edit order function
        function editOrder(orderId) {
            // Redirect to edit page
            window.location.href = 'admin-edit-order.php?id=' + orderId;
        }
        
        // Print order function
        function printOrder() {
            const orderDetails = document.getElementById('orderDetails').innerHTML;
            const printWindow = window.open('', '_blank');
            
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Order #${document.getElementById('modalOrderId').textContent}</title>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
                    <style>
                        body {
                            font-family: Arial, sans-serif;
                            line-height: 1.6;
                            color: #333;
                            padding: 20px;
                        }
                        h1 {
                            text-align: center;
                            margin-bottom: 20px;
                        }
                        .print-header {
                            text-align: center;
                            margin-bottom: 30px;
                        }
                        .print-header h2 {
                            margin: 5px 0;
                        }
                        .print-header p {
                            margin: 5px 0;
                            color: #666;
                        }
                        .status-badge {
                            display: inline-block;
                            padding: 3px 10px;
                            border-radius: 20px;
                            font-size: 12px;
                            font-weight: bold;
                            text-transform: capitalize;
                        }
                        .status-pending { background-color: #fff4e5; color: #f59e0b; }
                        .status-processing { background-color: #e0f2fe; color: #0ea5e9; }
                        .status-shipped { background-color: #dbeafe; color: #2563eb; }
                        .status-delivered { background-color: #dcfce7; color: #10b981; }
                        .status-received { background-color: #dcfce7; color: #10b981; }
                        .status-cancelled { background-color: #fee2e2; color: #ef4444; }
                        .order-info-card, .order-items, .order-summary {
                            margin-bottom: 30px;
                        }
                        .info-list {
                            list-style: none;
                            padding: 0;
                        }
                        .info-list li {
                            display: flex;
                            margin-bottom: 10px;
                        }
                        .info-label {
                            font-weight: bold;
                            width: 150px;
                        }
                        .items-table {
                            width: 100%;
                            border-collapse: collapse;
                        }
                        .items-table th, .items-table td {
                            border: 1px solid #ddd;
                            padding: 10px;
                            text-align: left;
                        }
                        .items-table th {
                            background-color: #f8f9fa;
                        }
                        .product-info {
                            display: flex;
                            align-items: center;
                        }
                        .product-image {
                            width: 50px;
                            height: 50px;
                            margin-right: 10px;
                        }
                        .summary-list {
                            list-style: none;
                            padding: 0;
                        }
                        .summary-list li {
                            display: flex;
                            justify-content: space-between;
                            margin-bottom: 10px;
                            padding-bottom: 10px;
                            border-bottom: 1px dashed #ddd;
                        }
                        .summary-list li:last-child {
                            font-weight: bold;
                            font-size: 16px;
                        }
                        .status-update-form, .payment-screenshot {
                            display: none;
                        }
                        @media print {
                            .no-print {
                                display: none;
                            }
                        }
                    </style>
                </head>
                <body>
                    <div class="print-header">
                        <h2>Mugna Leather Arts</h2>
                        <p>Order #${document.getElementById('modalOrderId').textContent}</p>
                        <p>${new Date().toLocaleDateString()}</p>
                    </div>
                    ${orderDetails}
                    <div class="no-print" style="text-align: center; margin-top: 30px;">
                        <button onclick="window.print();" style="padding: 10px 20px; background: #2563eb; color: white; border: none; border-radius: 5px; cursor: pointer;">
                            Print Order
                        </button>
                    </div>
                </body>
                </html>
            `);
            
            printWindow.document.close();
            printWindow.focus();
        }
        
        // Open lightbox for payment screenshot
        function openLightbox(imageSrc) {
            const lightbox = document.getElementById('screenshotLightbox');
            const lightboxImage = document.getElementById('lightboxImage');
            
            lightboxImage.src = imageSrc;
            lightbox.classList.add('active');
        }
        
        // Format date
        function formatDate(dateString) {
            const options = { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' };
            return new Date(dateString).toLocaleDateString('en-US', options);
        }
        
        // Format price
        function formatPrice(price) {
            return parseFloat(price).toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }
        
        // Format payment method
        function formatPaymentMethod(method) {
            return method.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
        }
    </script>
</body>
</html>
