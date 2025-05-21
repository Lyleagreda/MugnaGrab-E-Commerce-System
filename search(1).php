<?php
require_once 'data/products.php'; // Loads the $products array

$searchQuery = isset($_GET['q']) ? strtolower(trim($_GET['q'])) : '';
$filteredProducts = [];

if ($searchQuery !== '') {
    foreach ($products as $product) {
        if (
            str_contains(strtolower($product['name']), $searchQuery) ||
            str_contains(strtolower($product['category']), $searchQuery) ||
            str_contains(strtolower($product['description']), $searchQuery)
        ) {
            $filteredProducts[] = $product;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Search Results</title>
    <link rel="stylesheet" href="your-styles.css"> <!-- Link your CSS here -->
    <link rel="shortcut icon" href="./images/mugna-icon.png" type="image/x-icon">
</head>
<body>

<!-- You can include your site header/navigation here if needed -->

<div class="container">
    <h2>Search Results for "<?= htmlspecialchars($searchQuery) ?>"</h2>

    <?php if (empty($filteredProducts)): ?>
        <p>No products found.</p>
    <?php else: ?>
        <div class="product-list">
            <?php foreach ($filteredProducts as $product): ?>
                <div class="product-card">
                    <img src="<?= $product['image'] ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                    <h3><?= htmlspecialchars($product['name']) ?></h3>
                    <p>₱<?= number_format($product['price'], 2) ?></p>
                    <p><?= htmlspecialchars($product['category']) ?></p>
                    <p><?= htmlspecialchars($product['description']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
