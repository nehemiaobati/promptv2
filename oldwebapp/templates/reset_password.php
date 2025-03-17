<?php

require_once __DIR__ . '/../config.php';
session_start();

require_once __DIR__ .'/../functions.php';
require_once __DIR__ . '/../classes/User.php';

$pdo = getPDOConnection();
$user = new User($pdo);

require_once __DIR__ . '/../handlers/reset_password_handler.php';

require_once  __DIR__ . '/../includes/header.php';
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
    <?php else: ?>
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="text-center">Reset Your Password</h3>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <div class="form-group">
                                <label for="password">New Password:</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password:</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            <button type="submit" class="btn btn-primary btn-block" name="reset_password">Reset Password</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
