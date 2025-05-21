<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Include the slides data
include('../data/slides.php');

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Function to save slides back to file
    function saveSlides($slidesArray) {
        $slidesData = "<?php\n// Sample slideshow data\n\$slides = " . var_export($slidesArray, true) . ";\n\n";
        file_put_contents('../data/slides.php', $slidesData);
    }
    
    // Function to handle image upload
    function handleImageUpload() {
        if (!isset($_FILES['slide_image']) || $_FILES['slide_image']['error'] === UPLOAD_ERR_NO_FILE) {
            return $_POST['image_path'] ?? '';
        }
        
        $file = $_FILES['slide_image'];
        
        // Check for errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error: ' . $file['error']);
        }
        
        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowedTypes)) {
            throw new Exception('Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.');
        }
        
        // Validate file size (max 5MB)
        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $maxSize) {
            throw new Exception('File size exceeds the maximum limit of 5MB.');
        }
        
        // Create upload directory if it doesn't exist
        $uploadDir = '../images/slides/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // Generate unique filename
        $filename = uniqid() . '_' . basename($file['name']);
        $targetPath = $uploadDir . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new Exception('Failed to move uploaded file.');
        }
        
        // Return relative path
        return 'images/slides/' . $filename;
    }
    
    // Add new slide
    if ($action === 'add_slide') {
        try {
            // Handle image upload
            $imagePath = handleImageUpload();
            
            // Create gradient from color inputs
            $startColor = $_POST['gradient_start'] ?? 'rgba(30, 58, 138, 0.8)';
            $endColor = $_POST['gradient_end'] ?? 'rgba(91, 33, 182, 0.8)';
            $gradient = "$startColor, $endColor";
            
            $newSlide = [
                'id' => count($slides) > 0 ? max(array_column($slides, 'id')) + 1 : 1,
                'title' => $_POST['title'] ?? '',
                'description' => $_POST['description'] ?? '',
                'cta' => $_POST['cta'] ?? '',
                'image' => $imagePath,
                'gradient' => $gradient,
            ];
            
            $slides[] = $newSlide;
            saveSlides($slides);
            
            // Redirect to prevent form resubmission
            header('Location: slideshow.php?success=added');
            exit;
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            // We'll handle this error later
        }
    }
    
    // Edit slide
    elseif ($action === 'edit_slide') {
        try {
            $slideId = (int)$_POST['slide_id'];
            
            // Handle image upload
            $imagePath = handleImageUpload();
            
            // Create gradient from color inputs
            $startColor = $_POST['gradient_start'] ?? 'rgba(30, 58, 138, 0.8)';
            $endColor = $_POST['gradient_end'] ?? 'rgba(91, 33, 182, 0.8)';
            $gradient = "$startColor, $endColor";
            
            foreach ($slides as $key => $slide) {
                if ($slide['id'] === $slideId) {
                    $slides[$key]['title'] = $_POST['title'] ?? $slide['title'];
                    $slides[$key]['description'] = $_POST['description'] ?? $slide['description'];
                    $slides[$key]['cta'] = $_POST['cta'] ?? $slide['cta'];
                    
                    // Only update image if a new one was uploaded
                    if (!empty($imagePath)) {
                        $slides[$key]['image'] = $imagePath;
                    }
                    
                    $slides[$key]['gradient'] = $gradient;
                    break;
                }
            }
            
            saveSlides($slides);
            
            // Redirect to prevent form resubmission
            header('Location: slideshow.php?success=updated');
            exit;
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            // We'll handle this error later
        }
    }
    
    // Delete slide
    elseif ($action === 'delete_slide') {
        $slideId = (int)$_POST['slide_id'];
        
        foreach ($slides as $key => $slide) {
            if ($slide['id'] === $slideId) {
                unset($slides[$key]);
                break;
            }
        }
        
        // Re-index array
        $slides = array_values($slides);
        
        saveSlides($slides);
        
        // Redirect to prevent form resubmission
        header('Location: slideshow.php?success=deleted');
        exit;
    }
    
    // Update slide order
    elseif ($action === 'update_order') {
        $slideIds = json_decode($_POST['slide_ids'], true);
        $newSlides = [];
        
        // Reorder slides based on the new order
        foreach ($slideIds as $index => $id) {
            foreach ($slides as $slide) {
                if ($slide['id'] === (int)$id) {
                    $newSlides[] = $slide;
                    break;
                }
            }
        }
        
        $slides = $newSlides;
        saveSlides($slides);
        
        // Return JSON response for AJAX request
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }
}

// Get success message from URL parameter
$successMessage = '';
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'added':
            $successMessage = 'Slide added successfully!';
            break;
        case 'updated':
            $successMessage = 'Slide updated successfully!';
            break;
        case 'deleted':
            $successMessage = 'Slide deleted successfully!';
            break;
    }
}

// Page title
$pageTitle = "Slideshow";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Homepage Slideshow - Mugna Admin</title>
    <link rel="stylesheet" href="css/admin-styles.css">
    <link rel="shortcut icon" href="../images/mugna-icon.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <style>
        /* Enhanced Slideshow Styles */
        .slideshow-preview {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .preview-container {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            height: 300px;
            background-color: #1e293b;
        }
        
        .preview-slide {
            position: relative;
            height: 100%;
            transition: opacity 0.3s ease;
        }
        
        .preview-slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .preview-content {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 2rem;
            background: linear-gradient(to right, var(--gradient, rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.3)));
        }
        
        .preview-content h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: white;
            margin-bottom: 0.5rem;
        }
        
        .preview-content p {
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 1rem;
            max-width: 500px;
        }
        
        .preview-controls {
            position: absolute;
            bottom: 1rem;
            left: 0;
            right: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
        }
        
        .preview-arrows {
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            transform: translateY(-50%);
            display: flex;
            justify-content: space-between;
            padding: 0 1rem;
            pointer-events: none;
        }
        
        .preview-arrow {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            pointer-events: auto;
        }
        
        .preview-arrow:hover {
            background-color: rgba(255, 255, 255, 0.3);
        }
        
        .preview-dots {
            display: flex;
            gap: 0.5rem;
        }
        
        .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.5);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .dot.active {
            background-color: white;
            width: 24px;
            border-radius: 4px;
        }
        
        /* Table Styles */
        .table-card {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        
        .table-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .table-title i {
            color: #2563eb;
        }
        
        .slideshow-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .slideshow-table th {
            background-color: #f8fafc;
            color: #64748b;
            font-weight: 600;
            text-align: left;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .slideshow-table td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }
        
        .slideshow-table tr:last-child td {
            border-bottom: none;
        }
        
        .slideshow-table tr:hover td {
            background-color: #f8fafc;
        }
        
        .drag-handle {
            cursor: move;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #94a3b8;
        }
        
        .drag-handle i {
            transition: color 0.3s ease;
        }
        
        .drag-handle:hover i {
            color: #2563eb;
        }
        
        .slide-thumbnail {
            width: 80px;
            height: 45px;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .slide-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .description-cell {
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .table-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-icon {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f1f5f9;
            border: none;
            color: #64748b;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-icon:hover {
            background-color: #e2e8f0;
        }
        
        .btn-icon.edit:hover {
            background-color: rgba(37, 99, 235, 0.1);
            color: #2563eb;
        }
        
        .btn-icon.delete:hover {
            background-color: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            width: 90%;
            max-width: 600px;
            animation: modalFadeIn 0.3s;
        }
        
        @keyframes modalFadeIn {
            from {opacity: 0; transform: translateY(-50px);}
            to {opacity: 1; transform: translateY(0);}
        }
        
        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #94a3b8;
            cursor: pointer;
            transition: color 0.3s ease;
        }
        
        .modal-close:hover {
            color: #64748b;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #334155;
        }
        
        .form-group input[type="text"],
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            outline: none;
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .required {
            color: #ef4444;
        }
        
        .input-hint {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 0.25rem;
        }
        
        /* Modern File Upload */
        .file-upload-container {
            margin-bottom: 1rem;
        }
        
        .file-upload-area {
            border: 2px dashed #cbd5e1;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .file-upload-area:hover, .file-upload-area.dragover {
            border-color: #2563eb;
            background-color: rgba(37, 99, 235, 0.05);
        }
        
        .file-upload-icon {
            font-size: 2.5rem;
            color: #2563eb;
            margin-bottom: 1rem;
        }
        
        .file-upload-text {
            margin-bottom: 1rem;
        }
        
        .file-upload-text h4 {
            font-weight: 500;
            color: #334155;
            margin-bottom: 0.5rem;
        }
        
        .file-upload-text p {
            color: #64748b;
            font-size: 0.875rem;
        }
        
        .file-upload-btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            background-color: #2563eb;
            color: white;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .file-upload-btn:hover {
            background-color: #1d4ed8;
        }
        
        .file-upload-input {
            display: none;
        }
        
        .file-preview {
            margin-top: 1rem;
            display: none;
        }
        
        .file-preview-content {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background-color: #f8fafc;
            border-radius: 8px;
        }
        
        .file-preview-image {
            width: 100px;
            height: 60px;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .file-preview-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .file-preview-info {
            flex: 1;
        }
        
        .file-preview-name {
            font-weight: 500;
            color: #334155;
            margin-bottom: 0.25rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 300px;
        }
        
        .file-preview-size {
            color: #64748b;
            font-size: 0.75rem;
        }
        
        .file-preview-remove {
            background: none;
            border: none;
            color: #ef4444;
            cursor: pointer;
            font-size: 1.25rem;
            transition: all 0.3s ease;
        }
        
        .file-preview-remove:hover {
            color: #dc2626;
        }
        
        /* Modern Color Picker */
        .color-picker-container {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .color-picker-group {
            flex: 1;
        }
        
        .color-picker-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #334155;
            font-size: 0.875rem;
        }
        
        .color-picker-input {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .color-picker-input input[type="color"] {
            width: 40px;
            height: 40px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            padding: 0;
            background: none;
        }
        
        .color-picker-input input[type="color"]::-webkit-color-swatch-wrapper {
            padding: 0;
        }
        
        .color-picker-input input[type="color"]::-webkit-color-swatch {
            border: none;
            border-radius: 6px;
        }
        
        .color-picker-input input[type="text"] {
            flex: 1;
            padding: 0.5rem 0.75rem;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 0.875rem;
        }
        
        .color-picker-input input[type="range"] {
            flex: 1;
            margin-top: 0.5rem;
        }
        
        .gradient-preview {
            height: 40px;
            border-radius: 8px;
            margin-top: 0.5rem;
            background: linear-gradient(to right, #1e3a8a, #7e22ce);
        }
        
        /* Alert Messages */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .alert-success {
            background-color: rgba(16, 185, 129, 0.1);
            color: #10b981;
            border-left: 4px solid #10b981;
        }
        
        .alert-danger {
            background-color: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border-left: 4px solid #ef4444;
        }
        
        .alert i {
            font-size: 1.25rem;
        }
        
        /* Sortable Styles */
        .sortable-ghost {
            background-color: #f1f5f9;
            opacity: 0.5;
        }
        
        .sortable-chosen {
            background-color: #f8fafc;
        }
        
        .sortable-drag {
            opacity: 0.8;
        }
        
        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .preview-container {
                height: 250px;
            }
            
            .modal-content {
                width: 95%;
                margin: 10% auto;
            }
            
            .color-picker-container {
                flex-direction: column;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <?php include 'includes/header.php'; ?>

            <!-- Slideshow Content -->
            <div class="content-wrapper">
                <div class="content-header">
                    <h1>Homepage Slideshow</h1>
                    <button class="btn-primary" id="addSlideBtn">
                        <i class="fas fa-plus"></i> Add New Slide
                    </button>
                </div>

                <?php if (!empty($successMessage)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo $successMessage; ?></span>
                </div>
                <?php endif; ?>

                <?php if (isset($errorMessage)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo $errorMessage; ?></span>
                </div>
                <?php endif; ?>

                <div class="slideshow-preview">
                    <h2>Preview</h2>
                    <div class="preview-container">
                        <div class="preview-slide">
                            <!-- Preview content will be loaded dynamically -->
                        </div>
                        <div class="preview-arrows">
                            <button class="preview-arrow prev">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <button class="preview-arrow next">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                        <div class="preview-controls">
                            <div class="preview-dots">
                                <?php foreach ($slides as $index => $slide): ?>
                                <span class="dot <?php echo $index === 0 ? 'active' : ''; ?>" data-index="<?php echo $index; ?>"></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Slides Table -->
                <div class="table-card">
                    <div class="table-header">
                        <h2 class="table-title">
                            <i class="fas fa-images"></i> Manage Slides
                        </h2>
                    </div>
                    <div class="table-responsive">
                        <table class="slideshow-table">
                            <thead>
                                <tr>
                                    <th width="80">Order</th>
                                    <th width="100">Image</th>
                                    <th>Title</th>
                                    <th>Description</th>
                                    <th>Button Text</th>
                                    <th width="120">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="sortable">
                                <?php if (count($slides) > 0): ?>
                                    <?php foreach ($slides as $index => $slide): ?>
                                    <tr data-id="<?php echo $slide['id']; ?>">
                                        <td class="drag-handle">
                                            <i class="fas fa-grip-vertical"></i>
                                            <span><?php echo $index + 1; ?></span>
                                        </td>
                                        <td>
                                            <div class="slide-thumbnail">
                                                <img src="../<?php echo $slide['image']; ?>" alt="<?php echo $slide['title']; ?>">
                                            </div>
                                        </td>
                                        <td><?php echo $slide['title']; ?></td>
                                        <td class="description-cell"><?php echo $slide['description']; ?></td>
                                        <td><?php echo $slide['cta']; ?></td>
                                        <td>
                                            <div class="table-actions">
                                                <button class="btn-icon edit edit-slide-btn" data-id="<?php echo $slide['id']; ?>" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn-icon delete delete-slide-btn" data-id="<?php echo $slide['id']; ?>" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">No slides found. Add your first slide!</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Add/Edit Slide Modal -->
    <div id="slideModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Add New Slide</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <form id="slideForm" method="post" action="slideshow.php" enctype="multipart/form-data">
                    <input type="hidden" name="action" id="formAction" value="add_slide">
                    <input type="hidden" name="slide_id" id="slideId" value="">
                    <input type="hidden" name="image_path" id="imagePath" value="">
                    
                    <div class="form-group">
                        <label for="title">Title <span class="required">*</span></label>
                        <input type="text" id="title" name="title" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description <span class="required">*</span></label>
                        <textarea id="description" name="description" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="cta">Button Text <span class="required">*</span></label>
                        <input type="text" id="cta" name="cta" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Slide Image <span class="required">*</span></label>
                        <div class="file-upload-container">
                            <div class="file-upload-area" id="fileUploadArea">
                                <div class="file-upload-icon">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                </div>
                                <div class="file-upload-text">
                                    <h4>Drag & drop image here</h4>
                                    <p>or</p>
                                </div>
                                <label for="slide_image" class="file-upload-btn">Browse Files</label>
                                <input type="file" id="slide_image" name="slide_image" class="file-upload-input" accept="image/*">
                            </div>
                            <div class="file-preview" id="filePreview">
                                <div class="file-preview-content">
                                    <div class="file-preview-image">
                                        <img src="/placeholder.svg" alt="Preview" id="filePreviewImage">
                                    </div>
                                    <div class="file-preview-info">
                                        <div class="file-preview-name" id="filePreviewName">image.jpg</div>
                                        <div class="file-preview-size" id="filePreviewSize">0 KB</div>
                                    </div>
                                    <button type="button" class="file-preview-remove" id="filePreviewRemove">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            <p class="input-hint">Supported formats: JPG, PNG, GIF, WebP. Max size: 5MB.</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Gradient Colors</label>
                        <div class="color-picker-container">
                            <div class="color-picker-group">
                                <label class="color-picker-label">Start Color</label>
                                <div class="color-picker-input">
                                    <input type="color" id="gradientStartColor" value="#1e3a8a">
                                    <input type="text" id="gradient_start" name="gradient_start" value="rgba(30, 58, 138, 0.8)">
                                </div>
                                <input type="range" id="gradientStartOpacity" min="0" max="100" value="80">
                            </div>
                            <div class="color-picker-group">
                                <label class="color-picker-label">End Color</label>
                                <div class="color-picker-input">
                                    <input type="color" id="gradientEndColor" value="#7e22ce">
                                    <input type="text" id="gradient_end" name="gradient_end" value="rgba(91, 33, 182, 0.8)">
                                </div>
                                <input type="range" id="gradientEndOpacity" min="0" max="100" value="80">
                            </div>
                        </div>
                        <div class="gradient-preview" id="gradientPreview"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline modal-close-btn">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveSlideBtn">Save Slide</button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h3 class="modal-title">Confirm Deletion</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this slide? This action cannot be undone.</p>
                <div id="deleteSlideInfo" style="margin-top: 1rem; padding: 1rem; background-color: #f8fafc; border-radius: 8px; border-left: 4px solid #ef4444;">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div class="slide-thumbnail" id="deleteSlideImage">
                            <img src="/placeholder.svg" alt="Slide to delete">
                        </div>
                        <div>
                            <div id="deleteSlideTitle" style="font-weight: 600; color: #1e293b;"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline modal-close-btn">Cancel</button>
                <form action="slideshow.php" method="post">
                    <input type="hidden" name="action" value="delete_slide">
                    <input type="hidden" name="slide_id" id="deleteSlideId">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-trash"></i> Delete Slide
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="js/admin.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Slideshow preview functionality
            const previewSlide = document.querySelector('.preview-slide');
            const previewDots = document.querySelectorAll('.preview-dots .dot');
            const prevArrow = document.querySelector('.preview-arrow.prev');
            const nextArrow = document.querySelector('.preview-arrow.next');
            const slides = <?php echo json_encode($slides); ?>;
            let currentSlideIndex = 0;
            
            // Function to update the preview slide
            function updatePreviewSlide(index) {
                if (!previewSlide || !slides[index]) return;
                
                const slide = slides[index];
                const gradientStyle = slide.gradient ? 
                    `linear-gradient(to right, ${slide.gradient})` : 
                    'linear-gradient(to right, rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.3))';
                
                // Update slide content
                previewSlide.innerHTML = `
                    <img src="../${slide.image}" alt="${slide.title}" style="width: 100%; height: 100%; object-fit: cover;">
                    <div class="preview-content" style="background: ${gradientStyle}">
                        <h3>${slide.title}</h3>
                        <p>${slide.description}</p>
                        <button class="btn-light">${slide.cta}</button>
                    </div>
                `;
                
                // Update active dot
                previewDots.forEach((dot, i) => {
                    dot.classList.toggle('active', i === index);
                });
                
                // Update current slide index
                currentSlideIndex = index;
            }
            
            // Initialize preview with first slide
            if (slides.length > 0) {
                updatePreviewSlide(0);
            }
            
            // Add click event to dots
            previewDots.forEach((dot, index) => {
                dot.addEventListener('click', function() {
                    updatePreviewSlide(index);
                });
            });
            
            // Add click event to prev arrow
            if (prevArrow) {
                prevArrow.addEventListener('click', function() {
                    const newIndex = (currentSlideIndex - 1 + slides.length) % slides.length;
                    updatePreviewSlide(newIndex);
                });
            }
            
            // Add click event to next arrow
            if (nextArrow) {
                nextArrow.addEventListener('click', function() {
                    const newIndex = (currentSlideIndex + 1) % slides.length;
                    updatePreviewSlide(newIndex);
                });
            }
            
            // Initialize Sortable for slide reordering
            const slidesTable = document.querySelector('.slideshow-table tbody.sortable');
            if (slidesTable && typeof Sortable !== 'undefined') {
                const sortable = new Sortable(slidesTable, {
                    handle: '.drag-handle',
                    animation: 150,
                    ghostClass: 'sortable-ghost',
                    chosenClass: 'sortable-chosen',
                    dragClass: 'sortable-drag',
                    onEnd: function(evt) {
                        // Update slide order in database
                        const slideIds = Array.from(slidesTable.querySelectorAll('tr')).map(row => row.dataset.id);
                        
                        // Update order numbers in the UI
                        slidesTable.querySelectorAll('tr').forEach((row, index) => {
                            row.querySelector('.drag-handle span').textContent = index + 1;
                        });
                        
                        // Send AJAX request to update order
                        const xhr = new XMLHttpRequest();
                        xhr.open('POST', 'slideshow.php', true);
                        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                        
                        xhr.onload = function() {
                            if (xhr.status === 200) {
                                try {
                                    const response = JSON.parse(xhr.responseText);
                                    if (response.success) {
                                        // Show success message
                                        console.log('Slide order updated successfully!');
                                    }
                                } catch (e) {
                                    console.error('Error parsing response:', e);
                                }
                            }
                        };
                        
                        xhr.onerror = function() {
                            console.error('Request failed');
                        };
                        
                        xhr.send('action=update_order&slide_ids=' + JSON.stringify(slideIds));
                    }
                });
            }
            
            // Modal functionality
            const slideModal = document.getElementById('slideModal');
            const deleteModal = document.getElementById('deleteModal');
            const modalCloseBtns = document.querySelectorAll('.modal-close, .modal-close-btn');
            
            // Open Add Slide Modal
            const addSlideBtn = document.getElementById('addSlideBtn');
            if (addSlideBtn) {
                addSlideBtn.addEventListener('click', function() {
                    // Reset form
                    document.getElementById('slideForm').reset();
                    document.getElementById('formAction').value = 'add_slide';
                    document.getElementById('slideId').value = '';
                    document.getElementById('imagePath').value = '';
                    document.getElementById('modalTitle').textContent = 'Add New Slide';
                    document.getElementById('filePreview').style.display = 'none';
                    
                    // Reset color pickers
                    document.getElementById('gradientStartColor').value = '#1e3a8a';
                    document.getElementById('gradientEndColor').value = '#7e22ce';
                    document.getElementById('gradient_start').value = 'rgba(30, 58, 138, 0.8)';
                    document.getElementById('gradient_end').value = 'rgba(91, 33, 182, 0.8)';
                    document.getElementById('gradientStartOpacity').value = 80;
                    document.getElementById('gradientEndOpacity').value = 80;
                    updateGradientPreview();
                    
                    // Show modal
                    slideModal.style.display = 'block';
                });
            }
            
            // Open Edit Slide Modal
            const editSlideBtns = document.querySelectorAll('.edit-slide-btn');
            editSlideBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const slideId = parseInt(this.getAttribute('data-id'));
                    const slide = slides.find(s => s.id === slideId);
                    
                    if (slide) {
                        // Fill form with slide data
                        document.getElementById('formAction').value = 'edit_slide';
                        document.getElementById('slideId').value = slide.id;
                        document.getElementById('title').value = slide.title;
                        document.getElementById('description').value = slide.description;
                        document.getElementById('cta').value = slide.cta;
                        document.getElementById('imagePath').value = slide.image;
                        document.getElementById('modalTitle').textContent = 'Edit Slide';
                        
                        // Parse gradient colors
                        let startColor = 'rgba(30, 58, 138, 0.8)';
                        let endColor = 'rgba(91, 33, 182, 0.8)';
                        
                        if (slide.gradient) {
                            const colors = slide.gradient.split(',').map(c => c.trim());
                            if (colors.length >= 2) {
                                startColor = colors[0];
                                endColor = colors[1];
                            }
                        }
                        
                        // Set gradient colors
                        document.getElementById('gradient_start').value = startColor;
                        document.getElementById('gradient_end').value = endColor;
                        
                        // Set color picker values
                        setColorPickerFromRgba(document.getElementById('gradientStartColor'), document.getElementById('gradientStartOpacity'), startColor);
                        setColorPickerFromRgba(document.getElementById('gradientEndColor'), document.getElementById('gradientEndOpacity'), endColor);
                        
                        // Update gradient preview
                        updateGradientPreview();
                        
                        // Show image preview
                        const filePreview = document.getElementById('filePreview');
                        const filePreviewImage = document.getElementById('filePreviewImage');
                        const filePreviewName = document.getElementById('filePreviewName');
                        
                        filePreviewImage.src = '../' + slide.image;
                        filePreviewName.textContent = slide.image.split('/').pop();
                        filePreview.style.display = 'block';
                        
                        // Show modal
                        slideModal.style.display = 'block';
                    }
                });
            });
            
            // Open Delete Slide Modal
            const deleteSlideBtns = document.querySelectorAll('.delete-slide-btn');
            deleteSlideBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const slideId = parseInt(this.getAttribute('data-id'));
                    const slide = slides.find(s => s.id === slideId);
                    
                    if (slide) {
                        // Fill delete confirmation with slide data
                        document.getElementById('deleteSlideId').value = slide.id;
                        document.getElementById('deleteSlideTitle').textContent = slide.title;
                        document.getElementById('deleteSlideImage').querySelector('img').src = '../' + slide.image;
                        
                        // Show modal
                        deleteModal.style.display = 'block';
                    }
                });
            });
            
            // Close Modals
            modalCloseBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    slideModal.style.display = 'none';
                    deleteModal.style.display = 'none';
                });
            });
            
            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                if (event.target === slideModal) {
                    slideModal.style.display = 'none';
                }
                if (event.target === deleteModal) {
                    deleteModal.style.display = 'none';
                }
            });
            
            // Modern File Upload
            const fileUploadArea = document.getElementById('fileUploadArea');
            const fileInput = document.getElementById('slide_image');
            const filePreview = document.getElementById('filePreview');
            const filePreviewImage = document.getElementById('filePreviewImage');
            const filePreviewName = document.getElementById('filePreviewName');
            const filePreviewSize = document.getElementById('filePreviewSize');
            const filePreviewRemove = document.getElementById('filePreviewRemove');
            
            // File input change event
            fileInput.addEventListener('change', function(e) {
                handleFileSelect(e.target.files);
            });
            
            // Drag and drop events
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                fileUploadArea.addEventListener(eventName, preventDefaults, false);
            });
            
            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            ['dragenter', 'dragover'].forEach(eventName => {
                fileUploadArea.addEventListener(eventName, highlight, false);
            });
            
            ['dragleave', 'drop'].forEach(eventName => {
                fileUploadArea.addEventListener(eventName, unhighlight, false);
            });
            
            function highlight() {
                fileUploadArea.classList.add('dragover');
            }
            
            function unhighlight() {
                fileUploadArea.classList.remove('dragover');
            }
            
            fileUploadArea.addEventListener('drop', handleDrop, false);
            
            function handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                handleFileSelect(files);
            }
            
            function handleFileSelect(files) {
                if (files.length === 0) return;
                
                const file = files[0];
                
                // Check file type
                if (!file.type.match('image.*')) {
                    alert('Please select an image file (JPG, PNG, GIF, WebP).');
                    return;
                }
                
                // Check file size (max 5MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert('File size exceeds the maximum limit of 5MB.');
                    return;
                }
                
                // Update file preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    filePreviewImage.src = e.target.result;
                    filePreviewName.textContent = file.name;
                    filePreviewSize.textContent = formatFileSize(file.size);
                    filePreview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
            
            // Remove file
            filePreviewRemove.addEventListener('click', function() {
                fileInput.value = '';
                filePreview.style.display = 'none';
            });
            
            // Format file size
            function formatFileSize(bytes) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            }
            
            // Color Picker Functionality
            const gradientStartColor = document.getElementById('gradientStartColor');
            const gradientEndColor = document.getElementById('gradientEndColor');
            const gradientStartInput = document.getElementById('gradient_start');
            const gradientEndInput = document.getElementById('gradient_end');
            const gradientStartOpacity = document.getElementById('gradientStartOpacity');
            const gradientEndOpacity = document.getElementById('gradientEndOpacity');
            const gradientPreview = document.getElementById('gradientPreview');
            
            // Update gradient preview
            function updateGradientPreview() {
                const startColor = gradientStartInput.value;
                const endColor = gradientEndInput.value;
                gradientPreview.style.background = `linear-gradient(to right, ${startColor}, ${endColor})`;
            }
            
            // Convert hex to rgba
            function hexToRgba(hex, opacity) {
                hex = hex.replace('#', '');
                const r = parseInt(hex.substring(0, 2), 16);
                const g = parseInt(hex.substring(2, 4), 16);
                const b = parseInt(hex.substring(4, 6), 16);
                return `rgba(${r}, ${g}, ${b}, ${opacity / 100})`;
            }
            
            // Set color picker from rgba
            function setColorPickerFromRgba(colorInput, opacityInput, rgba) {
                const match = rgba.match(/rgba?$$(\d+),\s*(\d+),\s*(\d+)(?:,\s*([0-9.]+))?$$/);
                if (match) {
                    const r = parseInt(match[1]);
                    const g = parseInt(match[2]);
                    const b = parseInt(match[3]);
                    const a = match[4] ? parseFloat(match[4]) : 1;
                    
                    // Convert RGB to HEX
                    const hex = '#' + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
                    colorInput.value = hex;
                    opacityInput.value = Math.round(a * 100);
                }
            }
            
            // Update rgba when color changes
            gradientStartColor.addEventListener('input', function() {
                gradientStartInput.value = hexToRgba(this.value, gradientStartOpacity.value);
                updateGradientPreview();
            });
            
            gradientEndColor.addEventListener('input', function() {
                gradientEndInput.value = hexToRgba(this.value, gradientEndOpacity.value);
                updateGradientPreview();
            });
            
            // Update rgba when opacity changes
            gradientStartOpacity.addEventListener('input', function() {
                gradientStartInput.value = hexToRgba(gradientStartColor.value, this.value);
                updateGradientPreview();
            });
            
            gradientEndOpacity.addEventListener('input', function() {
                gradientEndInput.value = hexToRgba(gradientEndColor.value, this.value);
                updateGradientPreview();
            });
            
            // Initialize gradient preview
            updateGradientPreview();
            
            // Save slide
            const saveSlideBtn = document.getElementById('saveSlideBtn');
            if (saveSlideBtn) {
                saveSlideBtn.addEventListener('click', function() {
                    // Validate form
                    const form = document.getElementById('slideForm');
                    if (form.checkValidity()) {
                        form.submit();
                    } else {
                        // Trigger browser's native validation
                        const submitBtn = document.createElement('button');
                        submitBtn.type = 'submit';
                        form.appendChild(submitBtn);
                        submitBtn.click();
                        submitBtn.remove();
                    }
                });
            }
        });
    </script>
</body>
</html>
