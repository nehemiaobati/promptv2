<?php

require_once __DIR__ . '/../config.php';
session_start();

require_once __DIR__ .'/../functions.php';
require_once __DIR__ . '/../classes/User.php';

$pdo = getPDOConnection();
$user = new User($pdo);

require_once __DIR__ . '/../handlers/forgot_password_handler.php';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container mt-4">
    <!-- Display Errors and Success Messages -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger mt-3">
            <?php foreach ($errors as $error): ?>
                <p><?php echo $error; ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success mt-3">
            <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="text-center">Forgot Password</h3>
                </div>
                <div class="card-body">
                    <p class="text-center">Enter your username below and we'll send you a link to reset your password.</p>
                    <form method="post">
                        <div class="form-group">
                            <label for="username">Username:</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block" name="forgot_password">Reset Password</button>
                    </form>
                    <p class="mt-3 text-center">Remember your password? <a href="../index.php">Sign In</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
