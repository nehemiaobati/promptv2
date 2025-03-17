<?php
require_once 'classes/Admin.php';

if (!validateCsrfToken()) {
    $errors[] = "Invalid CSRF token.";
    return;
}

try {
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];

    // Basic validation
    if (empty($username) || empty($password)) {
        throw new Exception("Please fill in all fields.");
    }

    $user->login($username, $password);
    $success = "Sign in successful!";
    $_SESSION['is_admin'] = $admin->isAdmin($_SESSION['user_id']); // Set admin status after login
    $user->processReferrals($_SESSION['user_id']);

    if ($_SESSION['is_admin']) {
        header("Location: admin.php");
        exit();
    } else {
        header("Location: index.php"); // Redirect to index.php after successful sign-in
        exit();
    }

} catch (Exception $e) {
    $errors[] = $e->getMessage();
}

?>
