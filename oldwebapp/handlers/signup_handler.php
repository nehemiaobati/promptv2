<?php
// handlers/signup_handler.php

if (!validateCsrfToken()) {
    $errors[] = "Invalid CSRF token.";
    return;
}

try {
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];

    // Basic validation
    if (empty($username) || empty($email) || empty($password) || empty($confirmPassword)) {
        throw new Exception("Please fill in all fields.");
    }

    if ($password !== $confirmPassword) {
        throw new Exception("Passwords do not match.");
    }

    if (strlen($password) < 6) {
        throw new Exception("Password must be at least 6 characters long.");
    }

    $referrerId = isset($_GET['ref']) ? (int)$_GET['ref'] : null;

    // Register the new user
    $user_id = $user->register($username, $email, $password);

    // Log in the new user
    $user->login($username, $password);

    // Correct referral logic
    if ($referrerId) {
        // Add the referral, passing the correct IDs
        $user->addReferral($_SESSION['user_id'], $referrerId); // $_SESSION['user_id'] is the referred user, $referrerId is the referrer

        // Update the referrer's referral count
        $user->incrementReferralCount($referrerId);
    }

    $success = "Signup successful! You are now logged in.";
    header("Location: index.php"); // Redirect to index.php after successful signup
    exit();
} catch (Exception $e) {
    $errors[] = $e->getMessage();
}

?>
