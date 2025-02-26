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

// Handle Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

// M-Pesa Callbacks (Process these *before* any output)
$payment->processCallback();
$payment->processB2CResultCallback();
$payment->processB2CTimeoutCallback();

// Handle Signup
if (isset($_POST['signup'])) {
    try {
        $username = sanitizeInput($_POST['username']);
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirm_password'];

        // Basic validation
        if (empty($username) || empty($password) || empty($confirmPassword)) {
            throw new Exception("Please fill in all fields.");
        }

        if ($password !== $confirmPassword) {
            throw new Exception("Passwords do not match.");
        }

        if (strlen($password) < 6) {
            throw new Exception("Password must be at least 6 characters long.");
        }

        $referrerId = isset($_GET['ref']) ? (int)$_GET['ref'] : null;
        $user->register($username, $password);
        $user->login($username, $password);

        if ($referrerId) {
            $user->addReferral($_SESSION['user_id'], $referrerId, 1);
        }

        $success = "Signup successful! You are now logged in.";
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}

// Handle Signin
if (isset($_POST['signin'])) {
    try {
        $username = sanitizeInput($_POST['username']);
        $password = $_POST['password'];

        // Basic validation
        if (empty($username) || empty($password)) {
            throw new Exception("Please fill in all fields.");
        }

        $user->login($username, $password);
        $success = "Sign in successful!";
        $user->processReferrals($_SESSION['user_id']);

    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}

// Set Admin Status in Session
if (isset($_SESSION['user_id'])) {
    $_SESSION['is_admin'] = $admin->isAdmin($_SESSION['user_id']);
}

// Handle Payment
if (isset($_POST['charge']) && isset($_SESSION['user_id'])) {
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
}

// Handle Withdrawal Request
if (isset($_POST['withdraw']) && isset($_SESSION['user_id'])) {
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
}

require_once 'includes/header.php';

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

    <?php if (isset($_SESSION['username'])): ?>
        <div class="jumbotron text-center">
            <h1 class="display-4">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
            <p class="lead">Current Balance: KES <?php echo number_format($user->getBalance($_SESSION['user_id']), 2); ?></p>
            <p class="lead">Referral Earnings: KES <?php echo number_format($user->getReferralEarnings($_SESSION['user_id']), 2); ?></p>

            <!-- Referral Link -->
            <p class="mt-3">Your Referral Link: <span class="text-primary"><?php echo "https://your-domain.com/index.php?ref=" . $_SESSION['user_id']; ?></span></p>

            <!-- Payment Form -->
            <form method="post" class="mt-4">
                <div class="form-group">
                    <label for="amount">Amount (KES):</label>
                    <input type="number" min="50" step="0.01" class="form-control" id="amount" name="amount" required>
                </div>
                <div class="form-group">
                    <label for="phone">Phone Number (254...):</label>
                    <input type="text" class="form-control" id="phone" name="phone" required>
                </div>
                <button type="submit" class="btn btn-success" name="charge">Make Payment</button>
            </form>
            <!-- Withdrawal Form -->
            <h3 class="mt-5">Withdraw Referral Earnings</h3>
            <form method="post" class="mt-4">
                <div class="form-group">
                    <label for="withdraw_amount">Amount (KES):</label>
                    <input type="number"  step="0.01" class="form-control" id="withdraw_amount" name="amount" required>
                </div>
                <div class="form-group">
                    <label for="phone">Phone Number (254...):</label>
                    <input type="text" class="form-control" id="phone" name="phone" required>
                </div>
                <button type="submit" class="btn btn-success" name="withdraw">Request Withdrawal</button>
            </form>

            <!-- Display Withdrawal Requests -->
            <h3 class="mt-5">Withdrawal Requests</h3>
            <?php
            $withdrawals = $user->getWithdrawalRequests($_SESSION['user_id']);
            if (count($withdrawals) > 0) : ?>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Request Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($withdrawals as $withdrawal) : ?>
                            <tr>
                                <td><?php echo number_format($withdrawal['amount'], 2); ?></td>
                                <td><?php echo $withdrawal['status']; ?></td>
                                <td><?php echo $withdrawal['request_date']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p>No withdrawal requests found.</p>
            <?php endif; ?>
            <!-- Display Referrals -->
            <h3 class="mt-5">Your Referrals</h3>
            <?php
            $referrals = $user->getReferrals($_SESSION['user_id']);
            if (count($referrals) > 0) : ?>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($referrals as $referral) : ?>
                            <tr>
                                <td><?php echo htmlspecialchars($referral['username']); ?></td>
                                <td><?php echo $referral['status']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p>No referrals found.</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- Signup/Signin Form -->
        <div class="row justify-content-center">
             <div class="col-md-4">
                <div class="form-container p-4 border rounded">
                  
                    <div id="signup-form">
                        <h2 class="text-center mb-4">Create an Account</h2>
                        <form method="post" id="signup-form-actual">
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
                                <label for="confirm_password">Confirm Password:</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                <div class="invalid-feedback">
                                    Passwords must match.
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary btn-block" name="signup">Sign Up</button>
                        </form>
                         <p class="mt-3 text-center">Already have an account? <a href="#" id="show-signin">Sign In</a></p>
                     </div>

                        <div id="signin-form" style="display:none;">
                            <h2 class="text-center mb-4">Sign In</h2>
                            <form method="post">
                                <div class="form-group">
                                    <label for="username">Username:</label>
                                    <input type="text" class="form-control" id="username" name="username" required>
                                </div>
                                <div class="form-group">
                                    <label for="password">Password:</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                                <button type="submit" class="btn btn-success btn-block" name="signin">Sign In</button>
                            </form>
                           <p class="mt-3 text-center">Don't have an account? <a href="#" id="show-signup">Sign Up</a></p>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const signupForm = document.getElementById('signup-form');
                    const signinForm = document.getElementById('signin-form');
                    const signupFormActual = document.getElementById('signup-form-actual')
                    const showSignup = document.getElementById('show-signup');
                    const showSignin = document.getElementById('show-signin');

                    // Function to switch between forms
                    function switchForms(showForm, hideForm, showButton, hideButton) {
                        showForm.style.display = 'block';
                        hideForm.style.display = 'none';
                    }

                    // Event listeners for buttons
                     showSignup.addEventListener('click', function(e) {
                      e.preventDefault();
                        switchForms(signupForm, signinForm, showSignup, showSignin);
                    });
                    showSignin.addEventListener('click', function(e) {
                      e.preventDefault();
                        switchForms(signinForm, signupForm, showSignin, showSignup);
                    });
                    // make signup form to be shown by default
                      switchForms(signupForm, signinForm, showSignup, showSignin);

                    // Validation for signup form
                    signupFormActual.addEventListener('submit', function(e) {
                        let isValid = true;

                        // Username validation
                        if (signupFormActual.elements.username.value.trim() === "") {
                            signupFormActual.elements.username.classList.add('is-invalid');
                            isValid = false;
                        } else {
                            signupFormActual.elements.username.classList.remove('is-invalid');
                        }

                        // Password validation
                        if (signupFormActual.elements.password.value.trim() === "") {
                            signupFormActual.elements.password.classList.add('is-invalid');
                            isValid = false;
                        } else {
                            signupFormActual.elements.password.classList.remove('is-invalid');
                        }

                        // Password confirmation validation
                        if (signupFormActual.elements.confirm_password.value.trim() === "" || signupFormActual.elements.confirm_password.value.trim() !== signupFormActual.elements.password.value.trim()) {
                            signupFormActual.elements.confirm_password.classList.add('is-invalid');
                            isValid = false;
                        } else {
                            signupFormActual.elements.confirm_password.classList.remove('is-invalid');
                        }

                        if (!isValid) {
                            e.preventDefault();
                        }
                    });
                });
            </script>
        
    <?php endif; ?>
        <!-- Footer -->
        <footer class="mt-5 py-3 bg-light">
            <div class="container text-center">
                <a href="privacy.php" class="text-muted">Privacy Policy</a> |
                <a href="terms.php" class="text-muted">Terms and Conditions</a>
            </div>
        </footer>

</div>

<?php require_once 'includes/footer.php'; ?>
