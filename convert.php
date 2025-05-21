<?php
$hashedPassword = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($_POST["password"])) {
    $password = $_POST["password"];
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Password Hasher</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 50px;
        }
        form {
            max-width: 400px;
        }
        input[type="password"], input[type="submit"] {
            padding: 10px;
            margin-top: 10px;
            width: 100%;
        }
        .result {
            margin-top: 20px;
            padding: 10px;
            background-color: #f3f3f3;
            border-left: 5px solid #007BFF;
            word-wrap: break-word;
        }
    </style>
</head>
<body>

<h2>Password Hasher</h2>

<form method="POST" action="">
    <label for="password">Enter Password:</label><br>
    <input type="password" name="password" required><br>
    <input type="submit" value="Convert to Hash">
</form>

<?php if (!empty($hashedPassword)): ?>
    <div class="result">
        <strong>Hashed Password:</strong><br>
        <?= htmlspecialchars($hashedPassword) ?>
    </div>
<?php endif; ?>

</body>
</html>
