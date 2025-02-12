<?php
// Set session ini settings (if not set in php.ini)
//ini_set('session.cookie_httponly', 1);
//ini_set('session.use_only_cookies', 1);
// ini_set('session.cookie_secure', 1); // Only if you have HTTPS

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
// Handle M-Pesa Callbacks
$payment->processCallback();
$payment->processB2CResultCallback(); //process b2c result callbacks
$payment->processB2CTimeoutCallback(); //process b2c timeout callbacks
// Handle Signup
if (isset($_POST['signup'])) {
    try {
        // Check if a referral code was provided in the URL
        $referrerId = isset($_GET['ref']) ? (int)$_GET['ref'] : null;

        $user->register(sanitizeInput($_POST['username']), $_POST['password']);

        // If registration is successful, log in the new user
        $user->login(sanitizeInput($_POST['username']), $_POST['password']);

        // Add the referral if a referral code was used
        if ($referrerId) {
            $referredId = $_SESSION['user_id']; // The newly registered user
            $user->addReferral($referrerId, $referredId, 1); // Assuming direct referral (tier 1)
        }

        $success = "Signup successful! Please sign in.";
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}

// Handle Signin
if (isset($_POST['signin'])) {
    try {
        $user->login(sanitizeInput($_POST['username']), $_POST['password']);
        $success = "Sign in successful!";

        // Process referrals after successful login
        if (isset($_SESSION['user_id'])) {
            $user->processReferrals($_SESSION['user_id']);
        }

    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}

// Set Admin Status in Session
if (isset($_SESSION['user_id'])) {
    $_SESSION['is_admin'] = $admin->isAdmin($_SESSION['user_id']);
}

// Handle Payment



// Handle Payment
if (isset($_POST['charge']) && isset($_SESSION['user_id'])) {
    try {
        $amount = (float)$_POST['amount'];
        $phone = $_POST['phone'];
        $userId = $_SESSION['user_id']; // User making the deposit

        // Get the initial deposit amount
        $initialDepositAmount = $user->getInitialDepositAmount();

        // Proceed with the STK push (initiate payment)
        $message = $payment->initiateSTKPush($userId, $amount, $phone);
        $success = $message; // STK push initiated

        // Get the current balance of the user making the deposit
        $currentUserBalance = $user->getBalance($userId);

        // Check if the user making the deposit has met the initial deposit requirement
        if ($currentUserBalance >= $initialDepositAmount) {

            // Get referral details for the current user (referred_id)
            $referralDetails = $pdo->prepare("SELECT id, referrer_id, referral_tier FROM referrals WHERE referred_id = ? AND status = 'pending'");
            $referralDetails->execute([$userId]);
            $referral = $referralDetails->fetch(PDO::FETCH_ASSOC);

            if ($referral) {
                // Referral exists (user was referred by someone)
                $referrerId = $referral['referrer_id']; // First-tier referrer

                // Get the current balance of the first-tier referrer
                $referrerBalance = $user->getBalance($referrerId);

                // Check if the first-tier referrer's balance is also equal to or greater than the initial deposit
                if ($referrerBalance >= $initialDepositAmount) {

                    // Update first-tier referral status to 'successful'
                    $updateReferral = $pdo->prepare("UPDATE referrals SET status = 'successful' WHERE id = ?");
                    $updateReferral->execute([$referral['id']]);

                    // Calculate and add referral earnings (first tier)
                    $referralEarnings = $initialDepositAmount * 0.3;
                    $user->addReferralEarnings($referrerId, $referralEarnings);

                    // Check for a second-tier referrer
                    $secondTierReferrer = $pdo->prepare("SELECT id, referrer_id FROM referrals WHERE referred_id = ? AND referral_tier = 1 AND status = 'pending'");
                    $secondTierReferrer->execute([$referrerId]);
                    $secondTierReferral = $secondTierReferrer->fetch(PDO::FETCH_ASSOC);

                    if ($secondTierReferral) {
                        // Get the current balance of the second-tier referrer
                        $secondTierReferrerId = $secondTierReferral['referrer_id'];
                        $secondTierReferrerBalance = $user->getBalance($secondTierReferrerId);

                        // Check if the second-tier referrer's balance is also equal to or greater than the initial deposit
                        if ($secondTierReferrerBalance >= $initialDepositAmount) {

                            // Add second-tier referral (tier 2)
                            $user->addReferral($secondTierReferral['referrer_id'], $userId, 2);

                            // Calculate and add referral earnings (second tier)
                            $secondTierEarnings = $initialDepositAmount * 0.1;
                            $user->addReferralEarnings($secondTierReferral['referrer_id'], $secondTierEarnings);

                            // Update second-tier referral status to 'successful'
                            $updateSecondTier = $pdo->prepare("UPDATE referrals SET status = 'successful' WHERE referred_id = ? AND referral_tier = 2");
                            $updateSecondTier->execute([$userId]);
                        }
                    }
                }
            }
        }
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}

// ... (Rest of your index.php code) ...


// Handle Withdrawal Request
if (isset($_POST['withdraw']) && isset($_SESSION['user_id'])) {
    try {
        $amount = (float)$_POST['amount'];
        $phone = $_POST['phone'];
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
                <input type="number" step="0.01" class="form-control" id="amount" name="amount" required>
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
                <input type="number" step="0.01" class="form-control" id="withdraw_amount" name="amount" required>
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
    <div class="row">
        <div class="col-md-6">
            <div class="form-container">
                <h2 class="text-center mb-4">Sign Up</h2>
                <form method="post">
                    <div class="form-group">
                        <label for="username">Username:</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password:</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block" name="signup">Sign Up</button>
                </form>
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-container">
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
            </div>
        </div>
    </div>
<?php endif; ?>

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

<?php require_once 'includes/footer.php'; ?>