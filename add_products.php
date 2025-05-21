<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Display JavaScript alert if not logged in
    echo "<script>alert('You are not logged in. Please log in to access your account.');</script>";
    
    // Redirect after alert
    echo "<script>window.location.href = 'index.php';</script>";
    exit;  // Stop further execution of the script
}

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo "<script>alert('You do not have permission to access this page.');</script>";
    echo "<script>window.location.href = '../index.php';</script>";
    exit;
}

// Set page title for sidebar highlighting
$pageTitle = "Products";

// Include the products file to access the existing data
include('../data/products.php');

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $name = trim($_POST['name']);
    $price = floatval($_POST['price']);
    $rating = floatval($_POST['rating']);
    $reviews = intval($_POST['reviews']);
    $category = trim($_POST['category']);
    $sale = isset($_POST['sale']) ? true : false;
    $oldPrice = isset($_POST['oldPrice']) && !empty($_POST['oldPrice']) ? floatval($_POST['oldPrice']) : null;
    $new = isset($_POST['new']) ? true : false;
    $stock = intval($_POST['stock']);
    $description = trim($_POST['description']);

    // Validate data
    if (empty($name) || $price <= 0 || $rating < 0 || $rating > 5 || $reviews < 0) {
        $errorMessage = "Please fill in all required fields with valid values.";
    } else {
        // Handle image upload
        $imagePath = '';
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
                $errorMessage = "Failed to upload image. Please try again.";
            }
        } else {
            $imagePath = 'images/products/default-product.jpg';
        }
        
        if (empty($errorMessage)) {
            // Add new product to the array
            $newProduct = [
                'id' => count($products) + 1, // Simple increment for ID
                'name' => $name,
                'category' => $category,
                'price' => $price,
                'rating' => $rating,
                'reviews' => $reviews,
                'image' => $imagePath,
                'sale' => $sale,
                'oldPrice' => $oldPrice,
                'new' => $new,
                'stock' => $stock,
                'description' => $description
            ];

            // Add the new product to the existing $products array
            $products[] = $newProduct;

            // Save the updated products array back to the products.php file
            $updatedData = "<?php\n// Sample product data\n\$products = " . var_export($products, true) . ";\n";

            // Write the updated data back to the file
            $result = file_put_contents('../data/products.php', $updatedData);
            if ($result === false) {
                $errorMessage = "Failed to write data to the file. Please check file permissions.";
            } else {
                $successMessage = "Product added successfully!";
                
                // Clear form data after successful submission
                $_POST = array();
            }
        }
    }
}

// Categories for dropdown
$categories = [
    'Mens Bag & Accessories',
    'Women Accessories',
    'Womens Bags',
    'Sports & Travel',
    'Hobbies & Stationery',
    'Mobile Accessories',
    'Laptops & Computers'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product - Mugna Admin</title>
    <link rel="stylesheet" href="css/admin-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .text-danger {
            color: #ef4444;
        }
        
        .form-container {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--gray-700);
        }
        
        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group input[type="file"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--border-radius);
            font-size: 0.875rem;
            background-color: white;
        }
        
        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .checkbox-group label {
            margin-bottom: 0;
            cursor: pointer;
        }
        
        .image-preview {
            width: 100%;
            height: 200px;
            border: 1px dashed var(--gray-300);
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            overflow: hidden;
            background-color: var(--gray-100);
        }
        
        .image-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
        .image-preview-text {
            color: var(--gray-500);
            font-size: 0.875rem;
        }
        
        .alert {
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .alert-success {
            background-color: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        
        .alert-danger {
            background-color: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid var(--gray-200);
        }
        
        .form-section {
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .form-section-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .file-input-wrapper {
            position: relative;
        }
        
        .custom-file-input {
            display: inline-block;
            padding: 0.75rem 1rem;
            background-color: var(--gray-100);
            color: var(--gray-700);
            border: 1px solid var(--gray-300);
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 0.5rem;
        }
        
        .custom-file-input:hover {
            background-color: var(--gray-200);
        }
        
        .custom-file-input i {
            margin-right: 0.5rem;
        }
        
        .file-input-wrapper input[type="file"] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .required-note {
            font-size: 0.75rem;
            color: var(--gray-500);
            margin-bottom: 1rem;
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

            <!-- Content -->
            <div class="content-wrapper">
                <div class="content-header">
                    <h1>Add New Product</h1>
                    <a href="products.php" class="btn-outline">
                        <i class="fas fa-arrow-left"></i> Back to Products
                    </a>
                </div>

                <?php if (!empty($successMessage)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $successMessage; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($errorMessage)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $errorMessage; ?>
                </div>
                <?php endif; ?>

                <div class="form-container">
                    <p class="required-note"><span class="text-danger">*</span> indicates required fields</p>
                    
                    <form action="add_products.php" method="POST" enctype="multipart/form-data" id="productForm">
                        <div class="form-section">
                            <h3 class="form-section-title">Basic Information</h3>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="name">Product Name <span class="text-danger">*</span></label>
                                    <input type="text" id="name" name="name" value="<?php echo $_POST['name'] ?? ''; ?>" placeholder="Enter product name" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="category">Category <span class="text-danger">*</span></label>
                                    <select id="category" name="category" required>
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category; ?>" <?php echo (isset($_POST['category']) && $_POST['category'] == $category) ? 'selected' : ''; ?>>
                                            <?php echo $category; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="description">Product Description</label>
                                <textarea id="description" name="description" placeholder="Enter detailed product description"><?php echo $_POST['description'] ?? ''; ?></textarea>
                            </div>
                        </div>

                        <div class="form-section">
                            <h3 class="form-section-title">Pricing & Inventory</h3>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="price">Price (₱) <span class="text-danger">*</span></label>
                                    <input type="number" id="price" name="price" step="0.01" min="0" value="<?php echo $_POST['price'] ?? ''; ?>" placeholder="0.00" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="oldPrice">Old Price (₱) - if on sale</label>
                                    <input type="number" id="oldPrice" name="oldPrice" step="0.01" min="0" value="<?php echo $_POST['oldPrice'] ?? ''; ?>" placeholder="0.00">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="stock">Stock Quantity <span class="text-danger">*</span></label>
                                    <input type="number" id="stock" name="stock" min="0" value="<?php echo $_POST['stock'] ?? '10'; ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label>Product Status</label>
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="sale" name="sale" <?php echo (isset($_POST['sale'])) ? 'checked' : ''; ?>>
                                        <label for="sale">On Sale</label>
                                    </div>
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="new" name="new" <?php echo (isset($_POST['new'])) ? 'checked' : ''; ?>>
                                        <label for="new">New Product</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h3 class="form-section-title">Ratings & Reviews</h3>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="rating">Rating <span class="text-danger">*</span></label>
                                    <input type="number" id="rating" name="rating" step="0.1" min="0" max="5" value="<?php echo $_POST['rating'] ?? '4.5'; ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="reviews">Number of Reviews <span class="text-danger">*</span></label>
                                    <input type="number" id="reviews" name="reviews" min="0" value="<?php echo $_POST['reviews'] ?? '0'; ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h3 class="form-section-title">Product Image</h3>
                            <div class="form-group">
                                <label for="image">Product Image <span class="text-danger">*</span></label>
                                <div class="image-preview" id="imagePreview">
                                    <span class="image-preview-text">Image preview will appear here</span>
                                </div>
                                <div class="file-input-wrapper">
                                    <label class="custom-file-input" for="image">
                                        <i class="fas fa-upload"></i> Choose Image
                                    </label>
                                    <input type="file" id="image" name="image" accept="image/*" required>
                                </div>
                                <p class="input-hint" id="selectedFileName">No file selected</p>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="btn-outline" id="resetButton">
                                <i class="fas fa-undo"></i> Reset Form
                            </button>
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-plus"></i> Add Product
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script src="js/admin.js"></script>
    <script>
        // Image preview functionality
        const imageInput = document.getElementById('image');
        const selectedFileName = document.getElementById('selectedFileName');
        
        imageInput.addEventListener('change', function(e) {
            const preview = document.getElementById('imagePreview');
            const previewText = preview.querySelector('.image-preview-text');
            
            // Update selected file name
            if (this.files && this.files[0]) {
                selectedFileName.textContent = this.files[0].name;
            } else {
                selectedFileName.textContent = 'No file selected';
            }
            
            if (previewText) {
                previewText.remove();
            }
            
            // Remove any existing preview image
            const existingImg = preview.querySelector('img');
            if (existingImg) {
                existingImg.remove();
            }
            
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    preview.appendChild(img);
                }
                
                reader.readAsDataURL(this.files[0]);
            } else {
                const text = document.createElement('span');
                text.className = 'image-preview-text';
                text.textContent = 'Image preview will appear here';
                preview.appendChild(text);
            }
        });
        
        // Enhanced form reset functionality
        document.getElementById('resetButton').addEventListener('click', function() {
            // Show confirmation dialog
            if (confirm('Are you sure you want to reset the form? All entered data will be lost.')) {
                resetForm();
            }
        });
        
        function resetForm() {
            // Get the form element
            const form = document.getElementById('productForm');
            
            // Reset the form (clears all inputs)
            form.reset();
            
            // Reset image preview
            const preview = document.getElementById('imagePreview');
            const existingImg = preview.querySelector('img');
            if (existingImg) {
                existingImg.remove();
            }
            
            // Add back the preview text
            if (!preview.querySelector('.image-preview-text')) {
                const text = document.createElement('span');
                text.className = 'image-preview-text';
                text.textContent = 'Image preview will appear here';
                preview.appendChild(text);
            }
            
            // Reset file name display
            selectedFileName.textContent = 'No file selected';
            
            // Set focus to the first input field
            document.getElementById('name').focus();
        }
        
        // Form validation enhancement
        document.getElementById('productForm').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            const price = parseFloat(document.getElementById('price').value);
            const category = document.getElementById('category').value;
            const stock = parseInt(document.getElementById('stock').value);
            const image = document.getElementById('image').files;
            
            let isValid = true;
            let errorMessage = '';
            
            if (name === '') {
                errorMessage += 'Product name is required.\n';
                isValid = false;
            }
            
            if (isNaN(price) || price <= 0) {
                errorMessage += 'Please enter a valid price.\n';
                isValid = false;
            }
            
            if (category === '') {
                errorMessage += 'Please select a category.\n';
                isValid = false;
            }
            
            if (isNaN(stock) || stock < 0) {
                errorMessage += 'Please enter a valid stock quantity.\n';
                isValid = false;
            }
            
            if (image.length === 0) {
                errorMessage += 'Please select a product image.\n';
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fix the following errors:\n\n' + errorMessage);
            }
        });
    </script>
</body>
</html>
