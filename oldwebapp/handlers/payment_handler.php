<?php

if (!validateCsrfToken()) {
    $errors[] = "Invalid CSRF token.";
    return;
}

try {
    $amount = (float)$_POST['amount'];
    $phone = sanitizeInput($_POST['phone']); // Sanitize phone number
    $userId = $_SESSION['user_id'];

    if ($amount < 50) {
        throw new Exception("Minimum payment amount is KES 50.");
    }

    $message = $payment->initiateSTKPush($userId, $amount, $phone);
    $success = $message;

} catch (Exception $e) {
    $errors[] = $e->getMessage();
}

?>
