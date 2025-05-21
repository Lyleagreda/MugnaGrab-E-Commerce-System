<?php
// This file handles tracking product sales data

/**
 * Updates product sales data when an order is shipped
 * 
 * @param int $order_id The ID of the order being shipped
 * @param array $items Array of order items with product_id and quantity
 * @return bool True if successful, false otherwise
 */
function updateProductSalesData($order_id, $items) {
    // Path to the sales data file
    $sales_data_file = '../data/product_sales.php';
    
    // Initialize or load existing sales data
    if (file_exists($sales_data_file)) {
        include $sales_data_file;
    } else {
        // Create initial sales data structure if file doesn't exist
        $product_sales = [
            'total_sales' => 0,
            'products' => []
        ];
    }
    
    // Update sales data for each product in the order
    foreach ($items as $item) {
        $product_id = $item['product_id'];
        $quantity = $item['quantity'];
        $revenue = $item['total'];
        
        // Update total sales
        $product_sales['total_sales'] += $revenue;
        
        // Check if product exists in sales data
        $product_exists = false;
        foreach ($product_sales['products'] as &$product) {
            if ($product['id'] == $product_id) {
                // Update existing product sales data
                $product['quantity_sold'] += $quantity;
                $product['revenue'] += $revenue;
                $product['last_sold_date'] = date('Y-m-d H:i:s');
                $product_exists = true;
                break;
            }
        }
        
        // Add new product to sales data if it doesn't exist
        if (!$product_exists) {
            $product_sales['products'][] = [
                'id' => $product_id,
                'quantity_sold' => $quantity,
                'revenue' => $revenue,
                'first_sold_date' => date('Y-m-d H:i:s'),
                'last_sold_date' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    // Write updated sales data back to file
    $file_content = "<?php\n// Product sales data - automatically updated\n\$product_sales = " . var_export($product_sales, true) . ";\n\n";
    
    // Try to write to file
    try {
        file_put_contents($sales_data_file, $file_content);
        return true;
    } catch (Exception $e) {
        error_log("Error updating product sales data: " . $e->getMessage());
        return false;
    }
}

/**
 * Gets sales data for a specific product
 * 
 * @param int $product_id The ID of the product
 * @return array|null Sales data for the product or null if not found
 */
function getProductSalesData($product_id) {
    // Path to the sales data file
    $sales_data_file = '../data/product_sales.php';
    
    // Check if sales data file exists
    if (!file_exists($sales_data_file)) {
        return null;
    }
    
    // Load sales data
    include $sales_data_file;
    
    // Find product in sales data
    foreach ($product_sales['products'] as $product) {
        if ($product['id'] == $product_id) {
            return $product;
        }
    }
    
    return null;
}

/**
 * Gets top selling products based on quantity or revenue
 * 
 * @param string $sort_by Field to sort by ('quantity' or 'revenue')
 * @param int $limit Number of products to return
 * @return array Array of top selling products
 */
function getTopSellingProducts($sort_by = 'revenue', $limit = 5) {
    // Path to the sales data file
    $sales_data_file = '../data/product_sales.php';
    
    // Check if sales data file exists
    if (!file_exists($sales_data_file)) {
        return [];
    }
    
    // Load sales data
    include $sales_data_file;
    
    // Sort products by specified field
    $sort_field = ($sort_by === 'quantity') ? 'quantity_sold' : 'revenue';
    
    usort($product_sales['products'], function($a, $b) use ($sort_field) {
        return $b[$sort_field] <=> $a[$sort_field];
    });
    
    // Return top products
    return array_slice($product_sales['products'], 0, $limit);
}
