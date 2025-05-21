<?php
require_once '../config.php';

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request: ID is required']);
    exit;
}

$feeId = intval($_GET['id']);

// Fetch delivery fee data
$query = "SELECT * FROM delivery_fees WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $feeId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $fee = $result->fetch_assoc();
    echo json_encode(['success' => true, 'data' => $fee]);
} else {
    echo json_encode(['success' => false, 'message' => 'Delivery fee not found']);
}

$conn->close();
?>
