<?php
session_start();
include '../config.php';

// Check if admin is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
   echo "Unauthorized access";
   exit;
}

// Check if order_id is provided
if (!isset($_GET['order_id'])) {
   echo "Order ID is required";
   exit;
}

$order_id = $_GET['order_id'];

// Check if TCPDF library exists
if (!file_exists('TCPDF/tcpdf.php')) {
   echo "TCPDF library not found. Please make sure it's installed in the TCPDF directory.";
   exit;
}

// Include TCPDF library
require_once('TCPDF/tcpdf.php');

try {
   // Get order details
   $order_query = "SELECT o.* FROM orders o WHERE o.id = ?";
   $stmt = $conn->prepare($order_query);
   $stmt->bind_param("i", $order_id);
   $stmt->execute();
   $order_result = $stmt->get_result();
   
   if ($order_result->num_rows === 0) {
       echo "Order not found";
       exit;
   }
   
   $order = $order_result->fetch_assoc();
   
   // Get customer details
   $customer_query = "SELECT u.* FROM users u WHERE u.id = ?";
   $stmt = $conn->prepare($customer_query);
   $stmt->bind_param("i", $order['user_id']);
   $stmt->execute();
   $customer_result = $stmt->get_result();
   $customer = $customer_result->fetch_assoc();
   
   // Get order items
   $items_query = "SELECT oi.* FROM order_items oi WHERE oi.order_id = ?";
   $stmt = $conn->prepare($items_query);
   $stmt->bind_param("i", $order_id);
   $stmt->execute();
   $items_result = $stmt->get_result();
   $items = [];
   
   while ($item = $items_result->fetch_assoc()) {
       $items[] = $item;
   }
   
   // Get shipping address
   $address_query = "SELECT * FROM user_addresses WHERE id = ?";
   $stmt = $conn->prepare($address_query);
   $stmt->bind_param("i", $order['shipping_address_id']);
   $stmt->execute();
   $address_result = $stmt->get_result();
   $shipping_address = $address_result->fetch_assoc();
   
   // Create new PDF document
   $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
   
   // Set document information
   $pdf->SetCreator('Mugna');
   $pdf->SetAuthor('Mugna');
   $pdf->SetTitle('Payment Receipt - Order #' . $order_id);
   $pdf->SetSubject('Payment Receipt');
   
   // Remove default header/footer
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
   $pdf->SetFont('helvetica', 'B', 16);
   
   // ---------------------------------------------------------
   // MODERN HEADER
   // ---------------------------------------------------------
   
   // Set background color for header
   $pdf->SetFillColor(37, 99, 235); // Blue background
   $pdf->Rect(0, 0, $pdf->getPageWidth(), 30, 'F');
   
   // Add company name
   $pdf->SetFont('helvetica', 'B', 20);
   $pdf->SetTextColor(255, 255, 255);
   $pdf->SetXY(15, 8);
   $pdf->Cell(0, 10, 'Mugna', 0, 1, 'L');
   
   // Add receipt title
   $pdf->SetFont('helvetica', '', 12);
   $pdf->SetXY(15, 18);
   $pdf->Cell(0, 10, 'PAYMENT RECEIPT', 0, 1, 'R');
   
   // Reset text color
   $pdf->SetTextColor(0, 0, 0);
   
   // Add some space after header
   $pdf->SetY(40);
   
   // ---------------------------------------------------------
   // RECEIPT INFORMATION
   // ---------------------------------------------------------
   
   // Receipt number and date
   $pdf->SetFont('helvetica', 'B', 12);
   $pdf->Cell(0, 10, 'Receipt Information', 0, 1);
   
   $pdf->SetFont('helvetica', '', 10);
   
   // Create HTML for receipt info
   $receiptInfoHTML = '
   <table cellspacing="0" cellpadding="5" border="0">
       <tr>
           <td width="30%"><strong>Order ID:</strong></td>
           <td width="70%">#' . $order['id'] . '</td>
       </tr>
       <tr>
           <td><strong>Order Date:</strong></td>
           <td>' . date('F d, Y h:i A', strtotime($order['created_at'])) . '</td>
       </tr>
       <tr>
           <td><strong>Payment Method:</strong></td>
           <td>' . ucwords(str_replace('_', ' ', $order['payment_method'])) . '</td>
       </tr>
       <tr>
           <td><strong>Status:</strong></td>
           <td>' . ucfirst($order['status']) . '</td>
       </tr>
       
   </table>
   ';
   
   $pdf->writeHTML($receiptInfoHTML, true, false, true, false, '');
   
   $pdf->Ln(5);
   
   // ---------------------------------------------------------
   // CUSTOMER INFORMATION
   // ---------------------------------------------------------
   
   $pdf->SetFont('helvetica', 'B', 12);
   $pdf->Cell(0, 10, 'Customer Information', 0, 1);
   
   $pdf->SetFont('helvetica', '', 10);
   
   // Create HTML for customer info
   $customerInfoHTML = '
   <table cellspacing="0" cellpadding="5" border="0">
       <tr>
           <td width="30%"><strong>Name:</strong></td>
           <td width="70%">' . $customer['first_name'] . ' ' . $customer['last_name'] . '</td>
       </tr>
       <tr>
           <td><strong>Email:</strong></td>
           <td>' . $customer['email'] . '</td>
       </tr>
       <tr>
           <td><strong>Phone:</strong></td>
           <td>' . ($customer['phone'] ? $customer['phone'] : 'Not provided') . '</td>
       </tr>
   </table>
   ';
   
   $pdf->writeHTML($customerInfoHTML, true, false, true, false, '');
   
   $pdf->Ln(5);
   
   // ---------------------------------------------------------
   // SHIPPING INFORMATION
   // ---------------------------------------------------------
   
   $pdf->SetFont('helvetica', 'B', 12);
   $pdf->Cell(0, 10, 'Shipping Information', 0, 1);
   
   $pdf->SetFont('helvetica', '', 10);
   
   // Create HTML for shipping info
   $shippingInfoHTML = '
   <table cellspacing="0" cellpadding="5" border="0">
       <tr>
           <td width="30%"><strong>Address:</strong></td>
           <td width="70%">' . $shipping_address['address_line1'] . ', ' . $shipping_address['address_line2'] . '</td>
       </tr>
       <tr>
           <td><strong>City:</strong></td>
           <td>' . $shipping_address['city'] . '</td>
       </tr>
       <tr>
           <td><strong>State:</strong></td>
           <td>' . $shipping_address['state'] . '</td>
       </tr>
       <tr>
           <td><strong>Postal Code:</strong></td>
           <td>' . $shipping_address['postal_code'] . '</td>
       </tr>
   </table>
   ';
   
   $pdf->writeHTML($shippingInfoHTML, true, false, true, false, '');
   
   $pdf->Ln(5);
   
   // ---------------------------------------------------------
   // ORDER ITEMS
   // ---------------------------------------------------------
   
   $pdf->SetFont('helvetica', 'B', 12);
   $pdf->Cell(0, 10, 'Order Items', 0, 1);
   
   // Create HTML for order items table
   $itemsHTML = '
   <table cellspacing="0" cellpadding="8" border="1" style="border-collapse: collapse;">
       <thead>
           <tr style="background-color: #f8f9fa;">
               <th width="50%" align="left"><strong>Product</strong></th>
               <th width="15%" align="center"><strong>Price</strong></th>
               <th width="15%" align="center"><strong>Quantity</strong></th>
               <th width="20%" align="right"><strong>Total</strong></th>
           </tr>
       </thead>
       <tbody>
   ';
   
   foreach ($items as $item) {
       $itemsHTML .= '
       <tr>
           <td>' . $item['product_name'] . '</td>
           <td align="center">₱' . number_format($item['price'], 2) . '</td>
           <td align="center">' . $item['quantity'] . '</td>
           <td align="right">₱' . number_format($item['total'], 2) . '</td>
       </tr>
       ';
   }
   
   $itemsHTML .= '
       </tbody>
   </table>
   ';
   
   $pdf->writeHTML($itemsHTML, true, false, true, false, '');
   
   $pdf->Ln(5);
   
   // ---------------------------------------------------------
   // PAYMENT SUMMARY
   // ---------------------------------------------------------
   
   $pdf->SetFont('helvetica', 'B', 12);
   $pdf->Cell(0, 10, 'Payment Summary', 0, 1);
   
   // Create HTML for payment summary
   $summaryHTML = '
   <table cellspacing="0" cellpadding="5" border="0">
       <tr>
           <td width="80%" align="right"><strong>Subtotal:</strong></td>
           <td width="20%" align="right">₱' . number_format($order['subtotal'], 2) . '</td>
       </tr>
       <tr>
           <td align="right"><strong>Shipping Fee:</strong></td>
           <td align="right">₱' . number_format($order['total'] - $order['subtotal'], 2) . '</td>
       </tr>
       <tr style="background-color: #f0f4ff;">
           <td align="right"><strong>Total Amount:</strong></td>
           <td align="right"><strong>₱' . number_format($order['total'], 2) . '</strong></td>
       </tr>
   </table>
   ';
   
   $pdf->writeHTML($summaryHTML, true, false, true, false, '');
   
   $pdf->Ln(10);
   
   // ---------------------------------------------------------
   // FOOTER
   // ---------------------------------------------------------
   
   // Add a line
   $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
   
   $pdf->Ln(5);
   
   // Add footer text
   $pdf->SetFont('helvetica', 'I', 8);
   $pdf->Cell(0, 5, 'This is an official receipt for your order. Thank you for shopping with Mugna Leather Arts!', 0, 1, 'C');
   $pdf->Cell(0, 5, 'For any questions regarding this order, please contact our customer support.', 0, 1, 'C');
   $pdf->Cell(0, 5, 'Generated on ' . date('F d, Y h:i A') . ' PHT', 0, 1, 'C');
   
   // Output the PDF
   $pdf->Output('Payment_Receipt_Order_' . $order_id . '.pdf', 'I');
   
} catch (Exception $e) {
   echo "Error generating receipt: " . $e->getMessage();
}
