<div class="container mt-4">
    <!-- Navigation Links for Admin -->
    <div class="mb-3">
        <a href="privacy.php" class="text-muted">Privacy Policy</a> |
        <a href="terms.php" class="text-muted">Terms and Conditions</a>
    </div>
<h2>Admin Panel</h2>

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

<!-- All Users -->
<h3>All Users</h3>
<table class="table table-striped">
    <thead>
        <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Balance</th>
            <th>Referral Earnings</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $users = $admin->getAllUsers();
        foreach ($users as $user) : ?>
            <tr>
                <td><?php echo $user['id']; ?></td>
                <td><?php echo htmlspecialchars($user['username']); ?></td>
                <td><?php echo number_format($user['balance'], 2); ?></td>
                <td><?php echo number_format($user['referral_earnings'], 2); ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<!-- Pending Withdrawals -->
<h3>Pending Withdrawals</h3>
<form method="post">
    <button type="submit" class="btn btn-success" name="approve_all_withdrawals">Approve All</button>
</form>
<div class="table-responsive">
    <table class="table table-striped mt-3">
        <thead>
            <tr>
                <th>ID</th>
                <th>User ID</th>
                <th>Username</th>
                <th>Amount</th>
                <th>Phone Number</th>
                <th>Status</th>
                <th>Request Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $withdrawals = $admin->getPendingWithdrawals();
            foreach ($withdrawals as $withdrawal) : ?>
                <tr>
                    <td><?php echo $withdrawal['id']; ?></td>
                    <td><?php echo $withdrawal['user_id']; ?></td>
                    <td><?php echo htmlspecialchars($withdrawal['username']); ?></td>
                    <td><?php echo number_format($withdrawal['amount'], 2); ?></td>
                    <td><?php echo htmlspecialchars($withdrawal['phone_number']); ?></td>
                    <td><?php echo htmlspecialchars($withdrawal['status']); ?></td>
                    <td><?php echo $withdrawal['request_date']; ?></td>
                    <td>
                        <form method="post">
                            <input type="hidden" name="withdrawal_id" value="<?php echo $withdrawal['id']; ?>">
                            <input type="hidden" name="user_id" value="<?php echo $withdrawal['user_id']; ?>">
                            <input type="hidden" name="amount" value="<?php echo $withdrawal['amount']; ?>">
                            <input type="hidden" name="phone_number" value="<?php echo $withdrawal['phone_number']; ?>">
                            <button type="submit" class="btn btn-success btn-sm" name="approve_withdrawal">Approve</button>
                            <button type="submit" class="btn btn-danger btn-sm" name="suspend_withdrawal">Suspend</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Set Initial Deposit -->
<h3>Set Initial Deposit for Referral Earnings</h3>
<form method="post">
    <div class="form-group">
        <label for="initial_deposit">Initial Deposit Amount (KES):</label>
        <input type="number" step="0.01" class="form-control" id="initial_deposit" name="initial_deposit"
               value="<?php echo number_format($admin->getInitialDepositAmount(), 2); ?>" required>
    </div>
    <button type="submit" class="btn btn-primary" name="set_initial_deposit">Set Amount</button>
</form>
</div>
