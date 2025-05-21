<?php
session_start();
require 'db.php'; // Include your database connection

// Check if the user is logged in
if (!isset($_SESSION['logged_in']) || !isset($_SESSION['user_email'])) {
    // Display JavaScript alert if not logged in
    echo "<script>alert('You are not logged in. Please log in to access your account.');</script>";
    // Redirect to login page
    echo "<script>window.location.href = 'index.php';</script>";
    exit;  // Stop further execution of the script
}

// Get user ID from session
$user_email = $_SESSION['user_email'];
$stmt_user = $conn->prepare("SELECT id FROM users WHERE email = :email");
$stmt_user->bindParam(':email', $user_email, PDO::PARAM_STR);
$stmt_user->execute();

if ($stmt_user->rowCount() > 0) {
    $user = $stmt_user->fetch(PDO::FETCH_ASSOC);
    $user_id = $user['id'];
} else {
    // If no matching user is found, terminate the process
    echo "<script>alert('User session invalid. Please log in again.');</script>";
    echo "<script>window.location.href = 'index.php';</script>";
    exit;
}

if (isset($_POST['product_id'], $_POST['product_name'], $_POST['price'], $_POST['quantity'])) {
    $product_id = intval($_POST['product_id']);
    $product_name = $_POST['product_name'];
    $price = floatval($_POST['price']);
    $quantity = intval($_POST['quantity']);
    $created_at = date('Y-m-d H:i:s');

    // Check if product is already in the cart
    $stmt_check = $conn->prepare("SELECT * FROM cart WHERE user_id = :user_id AND product_id = :product_id");
    $stmt_check->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_check->bindParam(':product_id', $product_id, PDO::PARAM_INT);
    $stmt_check->execute();

    if ($stmt_check->rowCount() > 0) {
        // Product already in cart, so we update the quantity
        $stmt_update = $conn->prepare("UPDATE cart SET quantity = quantity + :quantity WHERE user_id = :user_id AND product_id = :product_id");
        $stmt_update->bindParam(':quantity', $quantity, PDO::PARAM_INT);
        $stmt_update->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt_update->bindParam(':product_id', $product_id, PDO::PARAM_INT);
        $stmt_update->execute();
    } else {
        // Product not in cart, so we insert a new record
        $stmt_insert = $conn->prepare("INSERT INTO cart (user_id, product_id, product_name, price, quantity, added_at)
                                      VALUES (:user_id, :product_id, :product_name, :price, :quantity, :added_at)");
        $stmt_insert->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt_insert->bindParam(':product_id', $product_id, PDO::PARAM_INT);
        $stmt_insert->bindParam(':product_name', $product_name, PDO::PARAM_STR);
        $stmt_insert->bindParam(':price', $price, PDO::PARAM_STR);
        $stmt_insert->bindParam(':quantity', $quantity, PDO::PARAM_INT);
        $stmt_insert->bindParam(':added_at', $created_at, PDO::PARAM_STR);
        $stmt_insert->execute();
    }

    // Redirect back to the previous page with success message
$redirect_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'products.php';
header("Location: $redirect_url?success=1");
exit;

} else {
    echo "Invalid product data.";
    exit;
}
?>
