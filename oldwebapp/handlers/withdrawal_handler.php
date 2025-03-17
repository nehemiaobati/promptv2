<?php

if (!validateCsrfToken()) {
    $errors[] = "Invalid CSRF token.";
    return;
}

try {
    $amount = (float)$_POST['amount'];
    $phone = sanitizeInput($_POST['phone']); // Sanitize phone number

    if ($amount <= 0) {
        throw new Exception("Invalid withdrawal amount.");
    }

    if ($amount > $user->getReferralEarnings($_SESSION['user_id'])) {
        throw new Exception("Insufficient referral earnings.");
    }

    $user->requestWithdrawal($_SESSION['user_id'], $amount, $phone);
    $success = "Withdrawal request submitted successfully.";
} catch (Exception $e) {
    $errors[] = $e->getMessage();
}

?>
