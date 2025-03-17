<?php

require_once 'config.php';
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

// Check if the user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !$admin->isAdmin($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Handle Set Initial Deposit
if (isset($_POST['set_initial_deposit'])) {
    try {
        $newInitialDeposit = (float)$_POST['initial_deposit'];
        if ($newInitialDeposit <= 0) {
            throw new Exception("Invalid initial deposit amount.");
        }
        if ($admin->setInitialDeposit($newInitialDeposit)) {
            $success = "Initial deposit amount updated successfully.";
        } else {
            throw new Exception("Failed to update initial deposit amount.");
        }
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}

// Handle Approve Withdrawal
if (isset($_POST['approve_withdrawal'])) {
    try {
        $withdrawalId = (int)$_POST['withdrawal_id'];
        $userId = (int)$_POST['user_id'];
        $amount = (float)$_POST['amount'];
        $phoneNumber = sanitizeInput($_POST['phone_number']); // Sanitize phone number

        $message = $admin->approveWithdrawal($withdrawalId, $userId, $amount, $phoneNumber);
        $success = $message;

    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}

// Handle Suspend Withdrawal
if (isset($_POST['suspend_withdrawal'])) {
    try {
        $withdrawalId = (int)$_POST['withdrawal_id'];
        if ($admin->suspendWithdrawal($withdrawalId)) {
            $success = "Withdrawal suspended successfully.";
        } else {
            throw new Exception("Failed to suspend withdrawal.");
        }
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}

// Handle Approve All Pending Withdrawals
if (isset($_POST['approve_all_withdrawals'])) {
    try {
        if ($admin->approveAllPendingWithdrawals()) {
            $success = "All pending withdrawals approved successfully.";
        } else {
            throw new Exception("Failed to approve all pending withdrawals.");
        }
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}

require_once 'includes/header.php';

?>

<?php require_once 'templates/admin_panel.php'; ?>

<?php require_once 'includes/footer.php'; ?>
