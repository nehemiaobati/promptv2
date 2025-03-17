<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Afrikenkid</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="/styles/styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
</head>
<body>
    <header class="header">
        <img src="images/logo.png" alt="AFRIKENKID Logo" style="max-height: 50px;">
        <h1>AFRIKENKID</h1>
        <?php if (isset($_SESSION['username'])): ?>
            <a href="index.php?logout=1" class="logout-link">Logout</a>
        <?php endif; ?>
    </header>
    
