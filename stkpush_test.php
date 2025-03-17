<?php

require_once 'oldwebapp/config.php';
require_once 'oldwebapp/classes/Payment.php';

// Database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Create a new Payment object
$payment = new Payment($pdo);

// Simulate POST data
$_POST['amount'] = 100; // Amount to be transacted
$_POST['phone'] = "254794587533"; // Customer's phone number

// Get user ID (replace with a valid user ID from your database)
$userId = 1; // Example user ID

try {
    $amount = (float)$_POST['amount'];
    $phone = (string)$_POST['phone']; // Sanitize phone number is already done in initiateSTKPush
    //$userId = $_SESSION['user_id']; // Use a valid user ID

    if ($amount < 50) {
        throw new Exception("Minimum payment amount is KES 50.");
    }

    print(generateMpesaAccessToken(MPESA_CONSUMER_KEY, MPESA_CONSUMER_SECRET));
    
    $message = $payment->initiateSTKPush($userId, $amount, $phone);
    $success = $message;
    echo "STK Push initiated. Check your phone.\n";
    echo "Response: " . $success . "\n";
    

} catch (Exception $e) {
    $errors[] = $e->getMessage();
    echo "Error: " . $e->getMessage() . "\n";
}

?>
