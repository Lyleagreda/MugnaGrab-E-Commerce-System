<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Set timezone to Philippine time
date_default_timezone_set('Asia/Manila');

// Include the products data from data file
include('../data/products.php');

// Include TCPDF library
require_once('tcpdf/tcpdf.php');

// Get filter parameters
$category = isset($_POST['category']) ? $_POST['category'] : '';
$status = isset($_POST['status']) ? $_POST['status'] : '';
$priceRange = isset($_POST['price_range']) ? $_POST['price_range'] : '';
$search = isset($_POST['search']) ? $_POST['search'] : '';

// Filter products based on parameters
$filteredProducts = $products;

// Category filter
if (!empty($category)) {
    $filteredProducts = array_filter($filteredProducts, function($product) use ($category) {
        return isset($product['category']) && strtolower($product['category']) === strtolower($category);
    });
}

// Status filter
if (!empty($status)) {
    $filteredProducts = array_filter($filteredProducts, function($product) use ($status) {
        $productStatus = getStatus($product['stock'] ?? 0);
        return strtolower($productStatus) === strtolower($status);
    });
}

// Price range filter
if (!empty($priceRange)) {
    $filteredProducts = array_filter($filteredProducts, function($product) use ($priceRange) {
        $price = $product['price'];
        
        if (strpos($priceRange, '-') !== false) {
            list($min, $max) = explode('-', $priceRange);
            return $price >= floatval($min) && $price <= floatval($max);
        } elseif (strpos($priceRange, '+') !== false) {
            $min = floatval(str_replace('+', '', $priceRange));
            return $price >= $min;
        }
        
        return true;
    });
}

// Search filter
if (!empty($search)) {
    $filteredProducts = array_filter($filteredProducts, function($product) use ($search) {
        $searchTerm = strtolower($search);
        return strpos(strtolower($product['name']), $searchTerm) !== false || 
               (isset($product['category']) && strpos(strtolower($product['category']), $searchTerm) !== false) ||
               (isset($product['description']) && strpos(strtolower($product['description']), $searchTerm) !== false);
    });
}

// After filtering products and before creating the PDF document, add these calculations:

// Calculate summary statistics
$totalProducts = count($filteredProducts);
$totalStock = 0;
$totalValue = 0;

// Count by status
$activeCount = 0;
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
    if ($status === 'Active') {
        $activeCount++;
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

// Get status based on stock
function getStatus($stock) {
    if ($stock <= 0) {
        return 'Out of Stock';
    } elseif ($stock <= 20) {
        return 'Low Stock';
    } else {
        return 'Active';
    }
}

// Update the formatPrice function to handle very large numbers
function formatPrice($price) {
    return '₱' . number_format($price, 2);
}

// Get current Philippine time
function getPhilippineTime($format = 'F j, Y, g:i a') {
    return date($format);
}

// Increase the header height to show full content
class MYPDF extends TCPDF {
    public function Header() {
        // Get the current page break margin
        $bMargin = $this->getBreakMargin();
        
        // Get current auto-page-break mode
        $auto_page_break = $this->AutoPageBreak;
        
        // Disable auto-page-break
        $this->SetAutoPageBreak(false, 0);
        
        // Set background color
        $this->SetFillColor(37, 99, 235); // Blue background
        
        // Draw header background - increase height from 30 to 35
        $this->Rect(0, 0, $this->getPageWidth(), 35, 'F');
        
        // Set text color to white
        $this->SetTextColor(255, 255, 255);
        
        // Set font
        $this->SetFont('helvetica', 'B', 18);
        
        // Title - adjust Y position
        $this->SetXY(15, 8);
        $this->Cell(0, 10, 'Mugna Products Report', 0, false, 'L', 0, '', 0, false, 'M', 'M');
        
        // Date - adjust Y position with Philippine time
        $this->SetFont('helvetica', '', 10);
        $this->SetXY(15, 20);
        $this->Cell(0, 10, 'Generated: ' . getPhilippineTime('F j, Y, g:i a') . ' PHT', 0, false, 'R', 0, '', 0, false, 'M', 'M');
        
        // Restore auto-page-break status
        $this->SetAutoPageBreak($auto_page_break, $bMargin);
        
        // Set the starting point for the page content
        $this->setPageMark();
        
        // Reset text color
        $this->SetTextColor(0, 0, 0);
    }
    
    public function Footer() {
        // Position at 15 mm from bottom
        $this->SetY(-15);
        
        // Set font
        $this->SetFont('helvetica', 'I', 8);
        
        // Draw line
        $this->Line(15, $this->GetY(), $this->getPageWidth() - 15, $this->GetY());
        
        // Page number
        $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'L', 0, '', 0, false, 'T', 'M');
        
        // Company info with Philippine time
        $this->Cell(0, 10, 'Mugna Admin System - ' . getPhilippineTime('Y-m-d'), 0, false, 'R', 0, '', 0, false, 'T', 'M');
    }
}

// Create new PDF document
$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Mugna Admin');
$pdf->SetAuthor('Mugna');
$pdf->SetTitle('Products Report');
$pdf->SetSubject('Products Report');
$pdf->SetKeywords('Products, Report, Mugna');

// Set default header data with Philippine time
$pdf->SetHeaderData('', 0, 'Mugna Products Report', 'Generated on ' . getPhilippineTime('Y-m-d H:i:s') . ' PHT');

// Set header and footer fonts
$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

// Set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// Set margins
$pdf->SetMargins(15, 40, 15);
$pdf->SetHeaderMargin(0);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, 25);

// Set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// Add a page
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', 'B', 14);

// Report title
$pdf->Cell(0, 10, 'Product Inventory Report', 0, 1, 'L');

// Build filter description
$filterDescription = '';
$filterCount = 0;

if (!empty($category)) {
    $filterDescription .= "Category: $category";
    $filterCount++;
}

if (!empty($status)) {
    if ($filterCount > 0) $filterDescription .= ' | ';
    $filterDescription .= "Status: $status";
    $filterCount++;
}

if (!empty($priceRange)) {
    if ($filterCount > 0) $filterDescription .= ' | ';
    
    if (strpos($priceRange, '-') !== false) {
        list($min, $max) = explode('-', $priceRange);
        $filterDescription .= "Price: ₱$min - ₱$max";
    } elseif (strpos($priceRange, '+') !== false) {
        $min = str_replace('+', '', $priceRange);
        $filterDescription .= "Price: ₱$min+";
    }
    
    $filterCount++;
}

if (!empty($search)) {
    if ($filterCount > 0) $filterDescription .= ' | ';
    $filterDescription .= "Search: \"$search\"";
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

// Add summary cards
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, 'Summary', 0, 1, 'L');

// Create summary cards with modern styling
$pdf->SetFillColor(248, 250, 252); // Light gray background
$pdf->SetDrawColor(226, 232, 240); // Border color
$pdf->SetLineWidth(0.3);

// Summary cards row
$cardWidth = 45;
$cardHeight = 30;
$cardSpacing = 10;
$startX = $pdf->GetX();
$startY = $pdf->GetY();

// Total Products Card
$pdf->RoundedRect($startX, $startY, $cardWidth, $cardHeight, 2, '1111', 'DF');
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetTextColor(100, 116, 139); // Text color
$pdf->SetXY($startX, $startY + 5);
$pdf->Cell($cardWidth, 5, 'TOTAL PRODUCTS', 0, 1, 'C');
$pdf->SetFont('helvetica', 'B', 16);
$pdf->SetTextColor(37, 99, 235); // Blue
$pdf->SetXY($startX, $startY + 12);
$pdf->Cell($cardWidth, 10, $totalProducts, 0, 1, 'C');

// Total Stock Card
$pdf->SetFillColor(248, 250, 252);
$pdf->RoundedRect($startX + $cardWidth + $cardSpacing, $startY, $cardWidth, $cardHeight, 2, '1111', 'DF');
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetTextColor(100, 116, 139);
$pdf->SetXY($startX + $cardWidth + $cardSpacing, $startY + 5);
$pdf->Cell($cardWidth, 5, 'TOTAL STOCK', 0, 1, 'C');
$pdf->SetFont('helvetica', 'B', 16);
$pdf->SetTextColor(16, 185, 129); // Green
$pdf->SetXY($startX + $cardWidth + $cardSpacing, $startY + 12);
$pdf->Cell($cardWidth, 10, $totalStock . ' units', 0, 1, 'C');

// Increase the width of the Total Value card significantly to accommodate very large numbers
// In the summary cards section:
// Total Value Card - increase width from 45+15 to 80
$pdf->SetFillColor(248, 250, 252);
$pdf->RoundedRect($startX + ($cardWidth + $cardSpacing) * 2, $startY, 80, $cardHeight, 2, '1111', 'DF');
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetTextColor(100, 116, 139);
$pdf->SetXY($startX + ($cardWidth + $cardSpacing) * 2, $startY + 5);
$pdf->Cell(80, 5, 'TOTAL VALUE', 0, 1, 'C');
$pdf->SetFont('helvetica', 'B', 14); // Reduce font size from 16 to 14 for very large numbers
$pdf->SetTextColor(239, 68, 68); // Red
$pdf->SetXY($startX + ($cardWidth + $cardSpacing) * 2, $startY + 12);
$pdf->Cell(80, 10, formatPrice($totalValue), 0, 1, 'C');

// Move position for status breakdown
$pdf->SetY($startY + $cardHeight + 10);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, 'Status Breakdown', 0, 1, 'L');

// Create status breakdown with colored indicators
$pdf->SetFont('helvetica', '', 10);

// Status breakdown table
$statusTable = '<table cellpadding="5" cellspacing="0" border="0" style="border-collapse: collapse;">
    <tr>
        <td width="33%" style="border: 1px solid #e2e8f0; background-color: #f0fdf4; color: #166534;">
            <span style="font-size: 14px; font-weight: bold;">' . $activeCount . '</span><br>
            <span style="color: #10b981;">Active Products</span>
        </td>
        <td width="33%" style="border: 1px solid #e2e8f0; background-color: #fefce8; color: #854d0e;">
            <span style="font-size: 14px; font-weight: bold;">' . $lowStockCount . '</span><br>
            <span style="color: #f59e0b;">Low Stock Products</span>
        </td>
        <td width="34%" style="border: 1px solid #e2e8f0; background-color: #fef2f2; color: #991b1b;">
            <span style="font-size: 14px; font-weight: bold;">' . $outOfStockCount . '</span><br>
            <span style="color: #ef4444;">Out of Stock Products</span>
        </td>
    </tr>
</table>';

$pdf->writeHTML($statusTable, true, false, true, false, '');

// Add space
$pdf->Ln(10);

// Create the product table with modern styling
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, 'Product List', 0, 1, 'L');

// Modern table styling
$tableStyle = '
<style>
    table {
        border-collapse: collapse;
        width: 100%;
        margin-bottom: 20px;
    }
    th {
        background-color: #2563eb;
        color: white;
        font-weight: bold;
        padding: 8px;
        text-align: left;
        font-size: 11px;
    }
    td {
        border-bottom: 1px solid #e2e8f0;
        padding: 8px;
        font-size: 10px;
    }
    tr:nth-child(even) {
        background-color: #f8fafc;
    }
    .status-active {
        color: #10b981;
        font-weight: bold;
    }
    .status-low {
        color: #f59e0b;
        font-weight: bold;
    }
    .status-out {
        color: #ef4444;
        font-weight: bold;
    }
    .price {
        font-weight: bold;
    }
</style>
';

// Start table
$html = $tableStyle . '<table border="0" cellpadding="5">
    <thead>
        <tr>
            <th width="8%">ID</th>
            <th width="25%">Product</th>
            <th width="15%">Category</th>
            <th width="12%">Price</th>
            <th width="10%">Stock</th>
            <th width="15%">Status</th>
            <th width="15%">Sale</th>
        </tr>
    </thead>
    <tbody>';

// Add table rows
foreach ($filteredProducts as $product) {
    $id = $product['id'];
    $name = htmlspecialchars($product['name']);
    $category = isset($product['category']) ? htmlspecialchars($product['category']) : 'Uncategorized';
    $price = formatPrice($product['price']);
    $stock = isset($product['stock']) ? $product['stock'] : 0;
    $status = getStatus($stock);
    $sale = isset($product['sale']) && $product['sale'] ? 'Yes' : 'No';
    
    // Set status class
    $statusClass = '';
    if ($status === 'Active') {
        $statusClass = 'status-active';
    } elseif ($status === 'Low Stock') {
        $statusClass = 'status-low';
    } elseif ($status === 'Out of Stock') {
        $statusClass = 'status-out';
    }
    
    $html .= "<tr>
        <td>$id</td>
        <td>$name</td>
        <td>$category</td>
        <td class=\"price\">$price</td>
        <td>$stock</td>
        <td class=\"$statusClass\">$status</td>
        <td>$sale</td>
    </tr>";
}

$html .= '</tbody></table>';

// Output the HTML content
$pdf->writeHTML($html, true, false, true, false, '');

// Add a new page for charts and additional data
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'Data Visualization', 0, 1, 'L');

// Create pie chart data for status distribution
$statusData = [
    ['Status', 'Count'],
    ['Active', $activeCount],
    ['Low Stock', $lowStockCount],
    ['Out of Stock', $outOfStockCount]
];

// Create pie chart for status distribution
$chartWidth = 180;
$chartHeight = 110; // Increase height from 90 to 110
$chartX = 15;
$chartY = $pdf->GetY() + 10;

// Draw chart title
$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(0, 10, 'Product Status Distribution', 0, 1, 'L');

// Draw chart background
$pdf->SetFillColor(248, 250, 252);
$pdf->RoundedRect($chartX, $chartY, $chartWidth, $chartHeight, 2, '1111', 'DF');

// Draw pie chart manually with standard TCPDF methods
$centerX = $chartX + 45;
$centerY = $chartY + $chartHeight/2;
$radius = 35;

// Calculate total for percentages
$total = $activeCount + $lowStockCount + $outOfStockCount;
if ($total > 0) {
    // Define colors
    $colors = [
        [16, 185, 129],  // Green for Active
        [245, 158, 11],  // Yellow for Low Stock
        [239, 68, 68]    // Red for Out of Stock
    ];
    
    // Draw pie slices
    $startAngle = 0;
    $values = [$activeCount, $lowStockCount, $outOfStockCount];
    
    for ($i = 0; $i < count($values); $i++) {
        if ($values[$i] > 0) {
            $pdf->SetFillColor($colors[$i][0], $colors[$i][1], $colors[$i][2]);
            
            // Calculate angles
            $endAngle = $startAngle + ($values[$i] / $total) * 360;
            
            // Convert to radians and draw sector using standard TCPDF methods
            $startAngleRad = deg2rad($startAngle);
            $endAngleRad = deg2rad($endAngle);
            
            // Create array of points for sector
            $points = [];
            $points[] = $centerX;
            $points[] = $centerY;
            
            for ($angle = $startAngleRad; $angle <= $endAngleRad; $angle += 0.2) {
                $points[] = $centerX + ($radius * cos($angle));
                $points[] = $centerY + ($radius * sin($angle));
            }
            
            $points[] = $centerX + ($radius * cos($endAngleRad));
            $points[] = $centerY + ($radius * sin($endAngleRad));
            $points[] = $centerX;
            $points[] = $centerY;
            
            // Draw sector
            $pdf->SetLineWidth(0.1);
            $pdf->SetDrawColor(255, 255, 255);
            $pdf->Polygon($points, 'DF', [], [255, 255, 255]);
            
            $startAngle = $endAngle;
        }
    }
    
    // Adjust legend positioning to prevent text overlap
    $legendX = $centerX + $radius + 25; // Increase spacing from 20 to 25
    $legendY = $centerY - 30; // Adjust vertical position

    // Increase spacing between legend items
    $pdf->SetFont('helvetica', '', 10);
    
    // Active legend
    $pdf->SetFillColor(16, 185, 129);
    $pdf->Rect($legendX, $legendY, 10, 10, 'F');
    $pdf->SetXY($legendX + 15, $legendY);
    $pdf->Cell(60, 10, 'Active: ' . $activeCount . ' (' . round(($activeCount / $total) * 100) . '%)', 0, 1, 'L');

    // Low Stock legend - increase vertical spacing from 15 to 20
    $pdf->SetFillColor(245, 158, 11);
    $pdf->Rect($legendX, $legendY + 20, 10, 10, 'F');
    $pdf->SetXY($legendX + 15, $legendY + 20);
    $pdf->Cell(60, 10, 'Low Stock: ' . $lowStockCount . ' (' . round(($lowStockCount / $total) * 100) . '%)', 0, 1, 'L');

    // Out of Stock legend - increase vertical spacing from 30 to 40
    $pdf->SetFillColor(239, 68, 68);
    $pdf->Rect($legendX, $legendY + 40, 10, 10, 'F');
    $pdf->SetXY($legendX + 15, $legendY + 40);
    $pdf->Cell(60, 10, 'Out of Stock: ' . $outOfStockCount . ' (' . round(($outOfStockCount / $total) * 100) . '%)', 0, 1, 'L');
    }

// Adjust the position for category breakdown to account for taller chart
$pdf->SetY($chartY + $chartHeight + 15); // Increase spacing from 10 to 15

// Move to position for category breakdown
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, 'Top Categories', 0, 1, 'L');

// Create category breakdown table
$pdf->SetFont('helvetica', '', 10);

// Limit to top 5 categories
$topCategories = array_slice($categoryCounts, 0, 5, true);

$categoryTable = '<table cellpadding="5" cellspacing="0" border="0" style="width: 100%; border-collapse: collapse;">
    <tr style="background-color: #f1f5f9;">
        <th style="border: 1px solid #e2e8f0; text-align: left; width: 60%;">Category</th>
        <th style="border: 1px solid #e2e8f0; text-align: center; width: 20%;">Count</th>
        <th style="border: 1px solid #e2e8f0; text-align: center; width: 20%;">Percentage</th>
    </tr>';

foreach ($topCategories as $category => $count) {
    $percentage = round(($count / $totalProducts) * 100);
    $categoryTable .= '<tr>
        <td style="border: 1px solid #e2e8f0;">' . $category . '</td>
        <td style="border: 1px solid #e2e8f0; text-align: center;">' . $count . '</td>
        <td style="border: 1px solid #e2e8f0; text-align: center;">' . $percentage . '%</td>
    </tr>';
}

$categoryTable .= '</table>';

$pdf->writeHTML($categoryTable, true, false, true, false, '');

// Add report generation information with Philippine time
$pdf->Ln(10);
$pdf->SetFont('helvetica', 'I', 8);
$pdf->Cell(0, 10, 'Report generated by: ' . ($_SESSION['username'] ?? 'Admin') . ' on ' . getPhilippineTime('Y-m-d H:i:s') . ' PHT', 0, 1, 'R');

// Close and output PDF document
$pdf->Output('products_report_' . date('Y-m-d') . '.pdf', 'I');
