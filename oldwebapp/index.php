<?php
require_once 'config.php';
require_once 'functions.php';
session_start();

require_once 'functions.php';
require_once 'classes/User.php';
require_once 'classes/Payment.php';
require_once 'classes/Admin.php';

$pdo = getPDOConnection();
$user = new User($pdo);
$payment = new Payment($pdo);
$admin = new Admin($pdo);

$errors = [];
$success = "";

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

// M-Pesa Callbacks (Process these *before* any output)
$payment->processCallback();
$payment->processB2CResultCallback();
$payment->processB2CTimeoutCallback();

// Handle Signup
if (isset($_POST['signup'])) {
    include 'handlers/signup_handler.php';
}

// Handle Signin
if (isset($_POST['signin'])) {
    include 'handlers/signin_handler.php';
}

// Handle Payment
if (isset($_POST['charge']) && isset($_SESSION['user_id'])) {
    include 'handlers/payment_handler.php';
}

// Handle Withdrawal Request
if (isset($_POST['withdraw']) && isset($_SESSION['user_id'])) {
    include 'handlers/withdrawal_handler.php';
}

// Set Admin Status in Session
if (isset($_SESSION['user_id'])) {
    $_SESSION['is_admin'] = $admin->isAdmin($_SESSION['user_id']);
}

require_once 'includes/header.php';

// Display content based on authentication status
if (isset($_GET['action']) && $_GET['action'] == 'signin') {
    include 'templates/signin_template.php';
} elseif (isset($_GET['action']) && $_GET['action'] == 'signup') {
    include 'templates/signup_template.php';
} elseif (isset($_GET['action']) && $_GET['action'] == 'privacy') {
    include 'templates/privacy.php';
} elseif (isset($_GET['action']) && $_GET['action'] == 'terms') {
    include 'templates/terms.php';
} else {
    include 'templates/content_template.php';
}

require_once 'includes/footer.php';

?>
