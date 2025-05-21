<?php
session_start();

// Check if the user is logged in as admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Set timezone to Philippine time
date_default_timezone_set('Asia/Manila');

// Include TCPDF library
require_once('TCPDF/tcpdf.php');

// Get filter parameters
$search = isset($_POST['search']) ? $_POST['search'] : '';
$category = isset($_POST['category']) ? $_POST['category'] : '';
$stockStatus = isset($_POST['stockStatus']) ? $_POST['stockStatus'] : '';
$sortBy = isset($_POST['sortBy']) ? $_POST['sortBy'] : 'name-asc';

// Get filtered products
$filteredProducts = [];
if (isset($_POST['products'])) {
    $filteredProducts = json_decode($_POST['products'], true);
}

// If no products were passed, get all products
if (empty($filteredProducts)) {
    include '../data/products.php';
    $filteredProducts = $products;
}

// Get status based on stock
function getStatus($stock) {
    if ($stock <= 0) {
        return 'Out of Stock';
    } elseif ($stock <= 20) {
        return 'Low Stock';
    } else {
        return 'In Stock';
    }
}

// Format price with PHP currency symbol
function formatPrice($price) {
    return '₱' . number_format($price, 2);
}

// Get current Philippine time
function getPhilippineTime($format = 'F j, Y, g:i a') {
    return date($format);
}

// Calculate summary statistics
$totalProducts = count($filteredProducts);
$totalStock = 0;
$totalValue = 0;

// Count by status
$inStockCount = 0;
$lowStockCount = 0;
$outOfStockCount = 0;

// Count by category
$categoryCounts = [];

foreach ($filteredProducts as $product) {
    // Calculate total stock and value
    $stock = isset($product['stock']) ? $product['stock'] : 0;
    $totalStock += $stock;
    $totalValue += $product['price'] * $stock;
    
    // Count by status
    $status = getStatus($stock);
    if ($status === 'In Stock') {
        $inStockCount++;
    } elseif ($status === 'Low Stock') {
        $lowStockCount++;
    } elseif ($status === 'Out of Stock') {
        $outOfStockCount++;
    }
    
    // Count by category
    $cat = isset($product['category']) ? $product['category'] : 'Uncategorized';
    if (!isset($categoryCounts[$cat])) {
        $categoryCounts[$cat] = 0;
    }
    $categoryCounts[$cat]++;
}

// Sort categories by count (descending)
arsort($categoryCounts);

// Create new PDF document
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Mugna Admin');
$pdf->SetAuthor('Mugna');
$pdf->SetTitle('Inventory Report');
$pdf->SetSubject('Inventory Report');
$pdf->SetKeywords('Inventory, Report, Mugna');

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
$pdf->Cell(0, 10, 'Mugna Inventory Report', 0, 1, 'L');

// Add date
$pdf->SetFont('helvetica', '', 10);
$pdf->SetXY(15, 18);
$pdf->Cell(0, 10, 'Generated: ' . getPhilippineTime('F j, Y, g:i a') . ' PHT', 0, 1, 'R');

// Reset text color
$pdf->SetTextColor(0, 0, 0);

// Add some space after header
$pdf->SetY(35);

// ---------------------------------------------------------
// FILTER INFORMATION
// ---------------------------------------------------------

// Build filter description
$filterDescription = '';
$filterCount = 0;

if (!empty($category)) {
    $filterDescription .= "Category: $category";
    $filterCount++;
}

if (!empty($stockStatus)) {
    if ($filterCount > 0) $filterDescription .= ' | ';
    $statusText = '';
    if ($stockStatus === 'in-stock') $statusText = 'In Stock';
    if ($stockStatus === 'low-stock') $statusText = 'Low Stock';
    if ($stockStatus === 'out-of-stock') $statusText = 'Out of Stock';
    $filterDescription .= "Status: $statusText";
    $filterCount++;
}

if (!empty($search)) {
    if ($filterCount > 0) $filterDescription .= ' | ';
    $filterDescription .= "Search: \"$search\"";
    $filterCount++;
}

if (!empty($sortBy)) {
    if ($filterCount > 0) $filterDescription .= ' | ';
    $sortByText = 'Name (A-Z)';
    if ($sortBy === 'name-desc') $sortByText = 'Name (Z-A)';
    if ($sortBy === 'stock-asc') $sortByText = 'Stock (Low to High)';
    if ($sortBy === 'stock-desc') $sortByText = 'Stock (High to Low)';
    if ($sortBy === 'price-asc') $sortByText = 'Price (Low to High)';
    if ($sortBy === 'price-desc') $sortByText = 'Price (High to Low)';
    $filterDescription .= "Sort By: $sortByText";
    $filterCount++;
}

// Add filter description
$pdf->SetFont('helvetica', 'I', 10);
if ($filterCount > 0) {
    $pdf->Cell(0, 8, 'Filters: ' . $filterDescription, 0, 1, 'L');
} else {
    $pdf->Cell(0, 8, 'No filters applied - showing all products', 0, 1, 'L');
}

$pdf->Ln(5);

// ---------------------------------------------------------
// SUMMARY SECTION
// ---------------------------------------------------------

$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'Summary', 0, 1, 'L');

// Create summary cards with HTML
$summaryHTML = '
<table cellspacing="10" cellpadding="10" border="0">
    <tr>
        <td width="33%" style="background-color: #EFF6FF; border: 1px solid #DBEAFE; border-radius: 5px; padding: 10px;">
            <span style="font-size: 12px; color: #3B82F6; font-weight: bold;">TOTAL PRODUCTS</span><br>
            <span style="font-size: 24px; font-weight: bold;">' . $totalProducts . '</span>
        </td>
        <td width="33%" style="background-color: #F0FDF4; border: 1px solid #DCFCE7; border-radius: 5px; padding: 10px;">
            <span style="font-size: 12px; color: #10B981; font-weight: bold;">TOTAL STOCK</span><br>
            <span style="font-size: 24px; font-weight: bold;">' . $totalStock . ' units</span>
        </td>
        <td width="34%" style="background-color: #FEF2F2; border: 1px solid #FEE2E2; border-radius: 5px; padding: 10px;">
            <span style="font-size: 12px; color: #EF4444; font-weight: bold;">TOTAL VALUE</span><br>
            <span style="font-size: 24px; font-weight: bold;">' . formatPrice($totalValue) . '</span>
        </td>
    </tr>
</table>
';

$pdf->writeHTML($summaryHTML, true, false, true, false, '');

// ---------------------------------------------------------
// STATUS BREAKDOWN
// ---------------------------------------------------------

$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'Status Breakdown', 0, 1, 'L');

// Status breakdown with colored indicators
$statusHTML = '
<table cellspacing="5" cellpadding="10" border="0">
    <tr>
        <td width="33%" style="background-color: #F0FDF4; border: 1px solid #DCFCE7; border-radius: 5px; padding: 10px;">
            <span style="font-size: 20px; font-weight: bold; color: #10B981;">' . $inStockCount . '</span><br>
            <span style="font-size: 12px; color: #047857;">In Stock Products</span>
        </td>
        <td width="33%" style="background-color: #FFFBEB; border: 1px solid #FEF3C7; border-radius: 5px; padding: 10px;">
            <span style="font-size: 20px; font-weight: bold; color: #F59E0B;">' . $lowStockCount . '</span><br>
            <span style="font-size: 12px; color: #B45309;">Low Stock Products</span>
        </td>
        <td width="34%" style="background-color: #FEF2F2; border: 1px solid #FEE2E2; border-radius: 5px; padding: 10px;">
            <span style="font-size: 20px; font-weight: bold; color: #EF4444;">' . $outOfStockCount . '</span><br>
            <span style="font-size: 12px; color: #B91C1C;">Out of Stock Products</span>
        </td>
    </tr>
</table>
';

$pdf->writeHTML($statusHTML, true, false, true, false, '');

// ---------------------------------------------------------
// TOP CATEGORIES
// ---------------------------------------------------------

// Only show if we have categories
if (!empty($categoryCounts)) {
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Top Categories', 0, 1, 'L');
    
    // Get top 5 categories
    $topCategories = array_slice($categoryCounts, 0, 5, true);
    
    // Create category table
    $categoryHTML = '
    <table cellspacing="0" cellpadding="8" border="1" style="border-collapse: collapse; border-color: #E5E7EB;">
        <tr style="background-color: #F9FAFB;">
            <th width="60%" style="text-align: left; font-size: 12px; font-weight: bold; color: #4B5563;">Category</th>
            <th width="20%" style="text-align: center; font-size: 12px; font-weight: bold; color: #4B5563;">Count</th>
            <th width="20%" style="text-align: center; font-size: 12px; font-weight: bold; color: #4B5563;">Percentage</th>
        </tr>
    ';
    
    foreach ($topCategories as $category => $count) {
        $percentage = round(($count / $totalProducts) * 100);
        $categoryHTML .= '
        <tr>
            <td style="text-align: left; font-size: 11px;">' . $category . '</td>
            <td style="text-align: center; font-size: 11px;">' . $count . '</td>
            <td style="text-align: center; font-size: 11px;">' . $percentage . '%</td>
        </tr>
        ';
    }
    
    $categoryHTML .= '</table>';
    
    $pdf->writeHTML($categoryHTML, true, false, true, false, '');
}

// ---------------------------------------------------------
// PRODUCT TABLE
// ---------------------------------------------------------

$pdf->AddPage();

// Modern header for second page
$pdf->SetFillColor(37, 99, 235);
$pdf->Rect(0, 0, $pdf->getPageWidth(), 30, 'F');
$pdf->SetFont('helvetica', 'B', 20);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetXY(15, 8);
$pdf->Cell(0, 10, 'Product Inventory List', 0, 1, 'L');
$pdf->SetFont('helvetica', '', 10);
$pdf->SetXY(15, 18);
$pdf->Cell(0, 10, 'Total Products: ' . $totalProducts, 0, 1, 'R');
$pdf->SetTextColor(0, 0, 0);
$pdf->SetY(35);

// Create modern product table
$tableHTML = '
<table cellspacing="0" cellpadding="8" border="1" style="border-collapse: collapse; border-color: #E5E7EB;">
    <thead>
        <tr style="background-color: #2563EB; color: white;">
            <th width="8%" style="text-align: center; font-size: 11px; font-weight: bold;">ID</th>
            <th width="32%" style="text-align: left; font-size: 11px; font-weight: bold;">Product</th>
            <th width="20%" style="text-align: left; font-size: 11px; font-weight: bold;">Category</th>
            <th width="15%" style="text-align: right; font-size: 11px; font-weight: bold;">Price</th>
            <th width="10%" style="text-align: center; font-size: 11px; font-weight: bold;">Stock</th>
            <th width="15%" style="text-align: center; font-size: 11px; font-weight: bold;">Status</th>
        </tr>
    </thead>
    <tbody>
';

// Counter for alternating row colors
$rowCount = 0;

foreach ($filteredProducts as $product) {
    // Set alternating row colors
    $bgColor = ($rowCount % 2 === 0) ? '#FFFFFF' : '#F9FAFB';
    
    $stock = isset($product['stock']) ? $product['stock'] : 0;
    
    // Determine stock status and color
    $status = getStatus($stock);
    $statusColor = '#10B981'; // Default green for In Stock
    
    if ($status === 'Low Stock') {
        $statusColor = '#F59E0B'; // Yellow for Low Stock
    } elseif ($status === 'Out of Stock') {
        $statusColor = '#EF4444'; // Red for Out of Stock
    }
    
    // Format price
    $price = isset($product['price']) ? formatPrice($product['price']) : '₱0.00';
    
    // Output row
    $tableHTML .= '
        <tr style="background-color: ' . $bgColor . ';">
            <td style="text-align: center; font-size: 10px;">' . $product['id'] . '</td>
            <td style="text-align: left; font-size: 10px; font-weight: bold;">' . $product['name'] . '</td>
            <td style="text-align: left; font-size: 10px;">' . (isset($product['category']) ? $product['category'] : 'Uncategorized') . '</td>
            <td style="text-align: right; font-size: 10px; font-weight: bold;">' . $price . '</td>
            <td style="text-align: center; font-size: 10px;">' . $stock . '</td>
            <td style="text-align: center; font-size: 10px; color: ' . $statusColor . '; font-weight: bold;">' . $status . '</td>
        </tr>
    ';
    
    $rowCount++;
}

$tableHTML .= '
    </tbody>
</table>
';

$pdf->writeHTML($tableHTML, true, false, true, false, '');

// ---------------------------------------------------------
// FOOTER
// ---------------------------------------------------------

// Add footer
$pdf->SetY(-25);
$pdf->SetFont('helvetica', 'I', 8);
$pdf->Cell(0, 5, 'This report was generated by Mugna Inventory Management System on ' . getPhilippineTime() . ' PHT.', 0, 1, 'C');
$pdf->Cell(0, 5, 'Confidential - For internal use only.', 0, 1, 'C');

// Output the PDF
$pdf->Output('inventory_report.pdf', 'I');
