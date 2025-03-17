<div class="row justify-content-center">
    <div class="col-md-4">
        <div class="form-container">
            <div id="signup-form">
                <h2 class="text-center mb-4">Create an Account</h2>
                <form method="post" id="signup-form-actual">
                    <?php echo generateCsrfTokenInput(); ?>
                    <div class="form-group">
                        <label for="username">Username:</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                        <div class="invalid-feedback">
                            Please choose a username.
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="password">Password:</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                        <div class="invalid-feedback">
                            Please choose a password.
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                        <div class="invalid-feedback">
                            Please enter a valid email address.
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password:</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        <div class="invalid-feedback">
                            Passwords must match.
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block" name="signup">Sign Up</button>
                </form>
<?php
$ref = isset($_GET['ref']) ? $_GET['ref'] : '';
$signin_url = "index.php?action=signin";
if (!empty($ref)) {
    $signin_url .= "&ref=" . urlencode($ref);
}
?>
<p class="mt-3 text-center">Already have an account? <a href="<?php echo $signin_url; ?>">Sign In</a></p>
            </div>
        </div>
    </div>
</div>
