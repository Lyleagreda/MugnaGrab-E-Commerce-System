<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Check if action is provided
if (!isset($_POST['action'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No action specified']);
    exit;
}

// Handle update action
if ($_POST['action'] === 'update') {
    // Check required fields
    if (!isset($_POST['id'], $_POST['name'], $_POST['category'], $_POST['price'], $_POST['stock'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    
    // Get form data
    $id = intval($_POST['id']);
    $name = trim($_POST['name']);
    $category = trim($_POST['category']);
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);
    $status = isset($_POST['status']) ? trim($_POST['status']) : 'Active';
    $sale = isset($_POST['sale']) && $_POST['sale'] == '1';
    $oldPrice = ($sale && isset($_POST['oldPrice']) && !empty($_POST['oldPrice'])) ? floatval($_POST['oldPrice']) : null;
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    
    // Validate data
    if (empty($name) || $price <= 0 || $stock < 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid product data']);
        exit;
    }
    
    try {
        // Include the products file to access the existing data
        include('../data/products.php');
        
        // Find the product to update
        $productFound = false;
        $imagePath = '';
        
        foreach ($products as $key => $product) {
            if ($product['id'] == $id) {
                // Keep the existing image path
                $imagePath = $product['image'] ?? 'images/products/default-product.jpg';
                
                // Handle image upload if provided
                if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                    $uploadDir = '../images/products/';
                    
                    // Create directory if it doesn't exist
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    
                    $fileName = basename($_FILES['image']['name']);
                    $targetFilePath = $uploadDir . $fileName;
                    
                    // Generate unique filename if file already exists
                    $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
                    $baseName = pathinfo($fileName, PATHINFO_FILENAME);
                    $counter = 1;
                    
                    while (file_exists($targetFilePath)) {
                        $fileName = $baseName . '_' . $counter . '.' . $fileExtension;
                        $targetFilePath = $uploadDir . $fileName;
                        $counter++;
                    }
                    
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFilePath)) {
                        $imagePath = 'images/products/' . $fileName;
                    } else {
                        throw new Exception('Failed to upload image. Please try again.');
                    }
                }
                
                // Update product data
                $products[$key]['name'] = $name;
                $products[$key]['category'] = $category;
                $products[$key]['price'] = $price;
                $products[$key]['stock'] = $stock;
                $products[$key]['status'] = $status;
                $products[$key]['sale'] = $sale;
                $products[$key]['oldPrice'] = $oldPrice;
                $products[$key]['description'] = $description;
                $products[$key]['image'] = $imagePath;
                
                $productFound = true;
                break;
            }
        }
        
        if (!$productFound) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Product not found']);
            exit;
        }
        
        // Save the updated products array back to the file
        $updatedData = "<?php\n// Sample product data\n\$products = " . var_export($products, true) . ";\n";
        
        // Write the updated data back to the file
        $result = file_put_contents('../data/products.php', $updatedData);
        
        if ($result === false) {
            throw new Exception('Failed to write data to the file');
        }
        
        // Return success response with image path
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'Product updated successfully',
            'imagePath' => '../' . $imagePath
        ]);
        
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    
    exit;
}

// If we get here, the action was not recognized
header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'Unknown action']);
?>
