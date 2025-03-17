<div class="row justify-content-center">
    <div class="col-md-4">
        <div class="form-container">
            <div id="signin-form">
                <h2 class="text-center mb-4">Sign In</h2>
                <form method="post">
                    <?php echo generateCsrfTokenInput(); ?>
                    <div class="form-group">
                        <label for="username">Username:</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password:</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                <button type="submit" class="btn btn-primary btn-block" name="signin">Sign In</button>
                </form>
<?php
$ref = isset($_GET['ref']) ? $_GET['ref'] : '';
$signup_url = "index.php?action=signup";
if (!empty($ref)) {
    $signup_url .= "&ref=" . urlencode($ref);
}
?>
<p class="mt-3 text-center">Don't have an account? <a href="<?php echo $signup_url; ?>">Sign Up</a></p>
                <p class="text-center"><a href= "templates/forgot_password.php">Forgot Password?</a></p>
            </div>
        </div>
    </div>
</div>
