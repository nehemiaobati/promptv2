<div class="container mt-4">

    <!-- Display Errors and Success Messages -->
    <?php displayMessages($errors, $success); ?>

    <?php if (isset($_SESSION['username'])): ?>
        <div class="jumbotron text-center">
            <h1 class="display-4">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
            <p class="lead">Current Balance: KES <?php echo number_format($user->getBalance($_SESSION['user_id']), 2); ?></p>
            <p class="lead">Referral Earnings: KES <?php echo number_format($user->getReferralEarnings($_SESSION['user_id']), 2); ?></p>

            <!-- Referral Link -->
            <p class="mt-3">
                Your Referral Link:
                <span class="text-primary" id="referralLink" style="word-break: break-all;"><?php echo "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/index.php?ref=" . $_SESSION['user_id']; ?></span>

                <button class="btn btn-outline-secondary btn-sm" onclick="copyReferralLink()">Copy</button>
            </p>
             <script>
                function copyReferralLink() {
                    var referralLink = document.getElementById("referralLink");
                    var textArea = document.createElement("textarea");
                    textArea.value = referralLink.textContent;
                    document.body.appendChild(textArea);
                    textArea.select();
                    navigator.clipboard.writeText(textArea.value);
                    textArea.remove();
                    alert("Link copied to clipboard!");
                }
            </script>

            <!-- Payment Form -->
            <form method="post" class="mt-4">
                <?php echo generateCsrfTokenInput(); ?>
                <div class="form-group">
                    <label for="amount">Amount (KES):</label>
                    <input type="number"  step="0.01" class="form-control" id="amount" name="amount" required>
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
                <?php echo generateCsrfTokenInput(); ?>
                <div class="form-group">
                    <label for="withdraw_amount">Amount (KES):</label>
                    <input type="number"  step="0.01" class="form-control" id="withdraw_amount" name="withdraw_amount" required>
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
    <?php elseif (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
        <!-- Admin Content -->
        <?php require_once 'templates/admin_panel.php'; ?>
    <?php else: ?>
        <!-- Signin/Signup Templates -->
        <?php require_once 'templates/signin_template.php'; ?>
        <?php //require_once 'templates/signup_template.php'; ?>
    <?php endif; ?>

</div>
