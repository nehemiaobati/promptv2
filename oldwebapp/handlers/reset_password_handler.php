<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ .'/../functions.php';
require_once __DIR__ . '/../classes/User.php';

$pdo = getPDOConnection();
$user = new User($pdo);

$errors = [];
$success = "";

if (isset($_POST['reset_password']) && isset($_GET['token'])) {
    // Handle password reset form submission
    try {
        $token = $_GET['token'];
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirm_password'];

        // Basic validation
        if (empty($password) || empty($confirmPassword)) {
            throw new Exception("Please fill in all fields.");
        }

        if ($password !== $confirmPassword) {
            throw new Exception("Passwords do not match.");
        }

        if (strlen($password) < 6) {
            throw new Exception("Password must be at least 6 characters long.");
        }

        // Validate token and reset password
        if ($user->resetPassword($token, $password)) {
            $success = "Your password has been reset successfully. You can now <a href='../index.php'>login</a> with your new password.";
        } else {
            throw new Exception("Failed to reset password. Please try again.");
        }
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}

?>
