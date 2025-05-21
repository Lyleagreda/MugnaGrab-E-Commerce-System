<?php
session_start();

// Check if the user is logged in as admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Check if products data is provided
if (!isset($_POST['products'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No products data provided']);
    exit;
}

try {
    // Decode the JSON data
    $products = json_decode($_POST['products'], true);
    
    if ($products === null && json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON data: ' . json_last_error_msg());
    }
    
    // Path to the products.php file
    $productsFile = '../data/products.php';
    
    // Create backup of the original file
    $backupFile = '../data/products_backup_' . date('Y-m-d_H-i-s') . '.php';
    if (file_exists($productsFile)) {
        copy($productsFile, $backupFile);
    }
    
    // Generate the new file content
    $fileContent = "<?php\n// Sample product data\n\$products = " . var_export($products, true) . ";\n\n";
    
    // Write to the file
    $result = file_put_contents($productsFile, $fileContent);
    
    if ($result === false) {
        throw new Exception('Failed to write to products file');
    }
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'message' => 'Products updated successfully',
        'backup' => basename($backupFile)
    ]);
    
} catch (Exception $e) {
    // Return error response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'Error updating products: ' . $e->getMessage()
    ]);
}
