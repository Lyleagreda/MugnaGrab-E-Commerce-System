<?php
session_start();
require 'db.php'; // Include your database connection

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !isset($_SESSION['user_email'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Get address ID from request
$address_id = isset($_GET['address_id']) ? intval($_GET['address_id']) : 0;

if ($address_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid address ID']);
    exit;
}

try {
    // Get the user's email from session
    $user_email = $_SESSION['user_email'];
    
    // Get user ID
    $stmt_user = $conn->prepare("SELECT id FROM users WHERE email = :email");
    $stmt_user->bindParam(':email', $user_email, PDO::PARAM_STR);
    $stmt_user->execute();
    
    if ($stmt_user->rowCount() > 0) {
        $user = $stmt_user->fetch(PDO::FETCH_ASSOC);
        $user_id = $user['id'];
        
        // Verify that the address belongs to the user
        $stmt_address = $conn->prepare("SELECT * FROM user_addresses WHERE id = :address_id AND user_id = :user_id");
        $stmt_address->bindParam(':address_id', $address_id, PDO::PARAM_INT);
        $stmt_address->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt_address->execute();
        
        if ($stmt_address->rowCount() > 0) {
            $address = $stmt_address->fetch(PDO::FETCH_ASSOC);
            $city = $address['city'];
            $state = $address['state'];
            
            // Get shipping fee based on city and state
            $stmt_shipping = $conn->prepare("SELECT fee_amount FROM delivery_fees WHERE city = :city AND state = :state AND is_available = 1");
            $stmt_shipping->bindParam(':city', $city, PDO::PARAM_STR);
            $stmt_shipping->bindParam(':state', $state, PDO::PARAM_STR);
            $stmt_shipping->execute();
            
            $shipping_fee = 0; // Default shipping fee
            
            if ($stmt_shipping->rowCount() > 0) {
                $shipping_fee = $stmt_shipping->fetchColumn();
            } else {
                // If no specific fee for this city/state, try to get a default fee for the state
                $stmt_default = $conn->prepare("SELECT fee_amount FROM delivery_fees WHERE state = :state AND city = 'Default' AND is_available = 1");
                $stmt_default->bindParam(':state', $state, PDO::PARAM_STR);
                $stmt_default->execute();
                
                if ($stmt_default->rowCount() > 0) {
                    $shipping_fee = $stmt_default->fetchColumn();
                }
            }
            
            // Return the shipping fee
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true, 
                'fee' => $shipping_fee,
                'city' => $city,
                'state' => $state
            ]);
            exit;
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Address not found or does not belong to user']);
            exit;
        }
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}
?>
