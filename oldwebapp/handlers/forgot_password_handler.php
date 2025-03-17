<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ .'/../functions.php';
require_once __DIR__ . '/../classes/User.php';

$pdo = getPDOConnection();
$user = new User($pdo);

$errors = [];
$success = "";

if (isset($_POST['forgot_password'])) {
    // Handle forgot password form submission
    try {
        $username = sanitizeInput($_POST['username']);

        // Basic validation
        if (empty($username)) {
            throw new Exception("Please enter your username.");
        }

        // Generate reset token
        $token = $user->generatePasswordResetToken($username);

        if ($token) {
            // Get user's email
            $email = $user->getUserEmail($username);

            if (!$email) {
                throw new Exception("Email not found for this username.");
            }

            // Construct reset link
            $resetLink = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $token;

            // Construct email subject and body
            $subject = "Password Reset Request";
            $body = "Please click the following link to reset your password: <a href='$resetLink'>$resetLink</a>";

            // Send email
            $emailResult = sendEmail($email, $subject, $body);

            if ($emailResult === 'Message has been sent') {
                $success = "A password reset link has been sent to your email address.";
            } else {
                throw new Exception("Failed to send email: " . $emailResult);
            }
        } else {
            throw new Exception("Username not found.");
        }
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}

?>
