<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Set timezone to Philippine time
date_default_timezone_set('Asia/Manila');

// Include database connection
include '../db.php';

// Get filter parameters
$search = isset($_POST['search']) ? $_POST['search'] : '';
$sortBy = isset($_POST['sort']) ? $_POST['sort'] : 'id';
$sortOrder = isset($_POST['order']) ? $_POST['order'] : 'ASC';

// Build query conditions
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(email LIKE :search OR first_name LIKE :search OR last_name LIKE :search OR phone LIKE :search)";
    $params[':search'] = "%$search%";
}

$whereClause = !empty($conditions) ? "WHERE " . implode(' AND ', $conditions) : "";

// Validate sort parameters
$allowedSortColumns = ['id', 'email', 'first_name', 'last_name', 'created_at'];
$sortBy = in_array($sortBy, $allowedSortColumns) ? $sortBy : 'id';

$allowedSortOrders = ['ASC', 'DESC'];
$sortOrder = in_array(strtoupper($sortOrder), $allowedSortOrders) ? strtoupper($sortOrder) : 'ASC';

// Get users data
$query = "SELECT * FROM users $whereClause ORDER BY $sortBy $sortOrder";
$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user statistics
$statsQuery = "SELECT 
    COUNT(*) as total_users,
    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_users_30days,
    COUNT(CASE WHEN profile_picture IS NOT NULL THEN 1 END) as users_with_profile_pic
FROM users";
$stmt = $conn->prepare($statsQuery);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Include TCPDF library
require_once('tcpdf/tcpdf.php');

// Get current Philippine time
function getPhilippineTime($format = 'F j, Y, g:i a') {
    return date($format);
}

// Custom PDF class with header and footer
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
        
        // Draw header background
        $this->Rect(0, 0, $this->getPageWidth(), 35, 'F');
        
        // Set text color to white
        $this->SetTextColor(255, 255, 255);
        
        // Set font
        $this->SetFont('helvetica', 'B', 18);
        
        // Title
        $this->SetXY(15, 8);
        $this->Cell(0, 10, 'Mugna Users Report', 0, false, 'L', 0, '', 0, false, 'M', 'M');
        
        // Date
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
        
        // Company info
        $this->Cell(0, 10, 'Mugna Admin System - ' . getPhilippineTime('Y-m-d'), 0, false, 'R', 0, '', 0, false, 'T', 'M');
    }
}

// Create new PDF document
$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Mugna Admin');
$pdf->SetAuthor('Mugna');
$pdf->SetTitle('Users Report');
$pdf->SetSubject('Users Report');
$pdf->SetKeywords('Users, Report, Mugna');

// Set default header data
$pdf->SetHeaderData('', 0, 'Mugna Users Report', 'Generated on ' . getPhilippineTime('Y-m-d H:i:s') . ' PHT');

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
$pdf->Cell(0, 10, 'User Accounts Report', 0, 1, 'L');

// Build filter description
$filterDescription = '';
$filterCount = 0;

if (!empty($search)) {
    $filterDescription .= "Search: \"$search\"";
    $filterCount++;
}

if (!empty($sortBy)) {
    if ($filterCount > 0) $filterDescription .= ' | ';
    $filterDescription .= "Sorted by: $sortBy ($sortOrder)";
    $filterCount++;
}

// Add filter description
$pdf->SetFont('helvetica', 'I', 10);
if ($filterCount > 0) {
    $pdf->Cell(0, 8, 'Filters: ' . $filterDescription, 0, 1, 'L');
} else {
    $pdf->Cell(0, 8, 'No filters applied - showing all users', 0, 1, 'L');
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

// Total Users Card
$pdf->RoundedRect($startX, $startY, $cardWidth, $cardHeight, 2, '1111', 'DF');
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetTextColor(100, 116, 139); // Text color
$pdf->SetXY($startX, $startY + 5);
$pdf->Cell($cardWidth, 5, 'TOTAL USERS', 0, 1, 'C');
$pdf->SetFont('helvetica', 'B', 16);
$pdf->SetTextColor(37, 99, 235); // Blue
$pdf->SetXY($startX, $startY + 12);
$pdf->Cell($cardWidth, 10, number_format($stats['total_users']), 0, 1, 'C');

// New Users Card
$pdf->SetFillColor(248, 250, 252);
$pdf->RoundedRect($startX + $cardWidth + $cardSpacing, $startY, $cardWidth, $cardHeight, 2, '1111', 'DF');
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetTextColor(100, 116, 139);
$pdf->SetXY($startX + $cardWidth + $cardSpacing, $startY + 5);
$pdf->Cell($cardWidth, 5, 'NEW USERS (30 DAYS)', 0, 1, 'C');
$pdf->SetFont('helvetica', 'B', 16);
$pdf->SetTextColor(16, 185, 129); // Green
$pdf->SetXY($startX + $cardWidth + $cardSpacing, $startY + 12);
$pdf->Cell($cardWidth, 10, number_format($stats['new_users_30days']), 0, 1, 'C');

// Users with Profile Pic Card
$pdf->SetFillColor(248, 250, 252);
$pdf->RoundedRect($startX + ($cardWidth + $cardSpacing) * 2, $startY, $cardWidth, $cardHeight, 2, '1111', 'DF');
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetTextColor(100, 116, 139);
$pdf->SetXY($startX + ($cardWidth + $cardSpacing) * 2, $startY + 5);
$pdf->Cell($cardWidth, 5, 'WITH PROFILE PIC', 0, 1, 'C');
$pdf->SetFont('helvetica', 'B', 16);
$pdf->SetTextColor(245, 158, 11); // Yellow
$pdf->SetXY($startX + ($cardWidth + $cardSpacing) * 2, $startY + 12);
$pdf->Cell($cardWidth, 10, number_format($stats['users_with_profile_pic']), 0, 1, 'C');

// Move position for user list
$pdf->SetY($startY + $cardHeight + 10);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, 'User List', 0, 1, 'L');

// Create the user table with modern styling
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
</style>
';

// Start table
$html = $tableStyle . '<table border="0" cellpadding="5">
    <thead>
        <tr>
            <th width="8%">ID</th>
            <th width="25%">Name</th>
            <th width="25%">Email</th>
            <th width="15%">Phone</th>
            <th width="15%">Date Joined</th>
            <th width="12%">Profile Pic</th>
        </tr>
    </thead>
    <tbody>';

// Add table rows
foreach ($users as $user) {
    $id = $user['id'];
    $name = htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
    $email = htmlspecialchars($user['email']);
    $phone = htmlspecialchars($user['phone'] ?? 'N/A');
    $dateJoined = date('M d, Y', strtotime($user['created_at']));
    $hasProfilePic = !empty($user['profile_picture']) ? 'Yes' : 'No';
    
    $html .= "<tr>
        <td>$id</td>
        <td>$name</td>
        <td>$email</td>
        <td>$phone</td>
        <td>$dateJoined</td>
        <td>$hasProfilePic</td>
    </tr>";
}

$html .= '</tbody></table>';

// Output the HTML content
$pdf->writeHTML($html, true, false, true, false, '');

// Add report generation information
$pdf->Ln(10);
$pdf->SetFont('helvetica', 'I', 8);
$pdf->Cell(0, 10, 'Report generated by: ' . ($_SESSION['username'] ?? 'Admin') . ' on ' . getPhilippineTime('Y-m-d H:i:s') . ' PHT', 0, 1, 'R');

// Close and output PDF document
$pdf->Output('users_report_' . date('Y-m-d') . '.pdf', 'I');
