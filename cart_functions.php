<?php
function addToCart($userId, $productId, $quantity, $pdo) {
    // Check if product already exists in cart
    $stmt = $pdo->prepare("SELECT * FROM user_cart WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$userId, $productId]);
    $existing = $stmt->fetch();

    if ($existing) {
        // If exists, update quantity
        $stmt = $pdo->prepare("UPDATE user_cart SET quantity = quantity + ? WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$quantity, $userId, $productId]);
    } else {
        // If not, insert new
        $stmt = $pdo->prepare("INSERT INTO user_cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $productId, $quantity]);
    }

    return ['success' => true, 'message' => 'Item added to cart'];
}
?>
