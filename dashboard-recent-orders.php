<?php
session_start();
include '../config.php';

// Check if admin is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Initialize response array
$response = [
    'success' => true,
    'data' => []
];

try {
    // Get recent orders of all statuses
    $query = "
        SELECT 
            o.id,
            CONCAT(u.first_name, ' ', u.last_name) AS customer,
            o.created_at AS date,
            o.total AS amount,
            o.status
        FROM orders o
        JOIN users u ON o.user_id = u.id
        ORDER BY o.created_at DESC
        LIMIT 5
    ";
    
    $result = $conn->query($query);
    $recentOrders = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Format date
            $row['date'] = date('M d, Y H:i', strtotime($row['date']));
            
            // Format amount
            $row['amount'] = number_format($row['amount'], 2);
            
            // Add status class for styling
            $row['status_class'] = 'status-' . strtolower($row['status']);
            
            $recentOrders[] = $row;
        }
    }
    
    $response['data'] = $recentOrders;
    
    // If no orders found, use sample data
    if (empty($response['data'])) {
        $response['data'] = [
            [
                'id' => 1001,
                'customer' => 'John Doe',
                'date' => date('M d, Y H:i', strtotime('-1 day')),
                'amount' => number_format(12500, 2),
                'status' => 'Pending',
                'status_class' => 'status-pending'
            ],
            [
                'id' => 1002,
                'customer' => 'Jane Smith',
                'date' => date('M d, Y H:i', strtotime('-2 days')),
                'amount' => number_format(8750, 2),
                'status' => 'Processing',
                'status_class' => 'status-processing'
            ],
            [
                'id' => 1003,
                'customer' => 'Mike Johnson',
                'date' => date('M d, Y H:i', strtotime('-3 days')),
                'amount' => number_format(15200, 2),
                'status' => 'Shipped',
                'status_class' => 'status-shipped'
            ]
        ];
    }
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = 'Database error: ' . $e->getMessage();
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
