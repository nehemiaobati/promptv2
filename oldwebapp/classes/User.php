<?php

class User
{
    private PDO $pdo; // Type hint the PDO object

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Registers a new user.
     *
     * @param string $username The username.
     * @param string $password The password.
     * @return bool True on success, false on failure.
     * @throws Exception If registration fails.
     */
    public function register(string $username, string $password): bool
    {
        if (empty($username) || empty($password)) {
            throw new Exception("Username and password are required.");
        }

        if ($this->checkUsernameExists($username)) {
            throw new Exception("Username already taken.");
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        try {
            $stmt = $this->pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
            return $stmt->execute([$username, $hashedPassword]); // Directly return the result of execute()
        } catch (PDOException $e) {
            error_log("Error creating user: " . $e->getMessage());
            throw new Exception("Error creating user.");
        }
    }

    /**
     * Logs in an existing user.
     *
     * @param string $username The username.
     * @param string $password The password.
     * @return bool True on success, false on failure.
     * @throws Exception If login fails.
     */
    public function login(string $username, string $password): bool
    {
        if (empty($username) || empty($password)) {
            throw new Exception("Username and password are required.");
        }

        try {
            $stmt = $this->pdo->prepare("SELECT id, password FROM users WHERE username = ?");
            $stmt->execute([$username]);

            if ($user = $stmt->fetch()) {
                if (password_verify($password, $user['password'])) {
                    session_regenerate_id(true); // Regenerate session ID on login for security
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $username;
                    return true;
                }
            }
            throw new Exception("Invalid username or password.");
        } catch (PDOException $e) {
            error_log("Error logging in: " . $e->getMessage());
            throw new Exception("Error logging in.");
        }
    }

    /**
     * Gets the user's balance.
     *
     * @param int $userId The user ID.
     * @return float The user's balance.
     */
    public function getBalance(int $userId): float
    {
        try {
            $stmt = $this->pdo->prepare("SELECT balance FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $result = $stmt->fetch();
            return $result ? (float)$result['balance'] : 0.00;
        } catch (PDOException $e) {
            error_log("Error fetching balance: " . $e->getMessage());
            return 0.00; // Or handle the error appropriately
        }
    }

    /**
     * Gets the user's referral earnings.
     *
     * @param int $userId The user ID.
     * @return float The user's referral earnings.
     */
    public function getReferralEarnings(int $userId): float
    {
        try {
            $stmt = $this->pdo->prepare("SELECT referral_earnings FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $result = $stmt->fetch();
            return $result ? (float)$result['referral_earnings'] : 0.00;
        } catch (PDOException $e) {
            error_log("Error fetching referral earnings: " . $e->getMessage());
            return 0.00;
        }
    }

    /**
     * Gets the user's referrals.
     *
     * @param int $userId The user ID.
     * @return array An array of the user's referrals.
     */
    public function getReferrals(int $userId): array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT u.username, r.status FROM referrals r LEFT JOIN users u ON r.referred_id = u.id WHERE r.referrer_id = ? AND r.referral_tier = 1");
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error fetching referrals: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Adds a new referral.
     *
     * @param int $referrerId The ID of the referrer.
     * @param int $referredId The ID of the referred user.
     * @param int $referralTier The referral tier.
     * @return bool True on success, false on failure.
     */
    public function addReferral(int $referrerId, int $referredId, int $referralTier): bool
    {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO referrals (referrer_id, referred_id, referral_tier) VALUES (?, ?, ?)");
            return $stmt->execute([$referrerId, $referredId, $referralTier]);
        } catch (PDOException $e) {
            error_log("Error adding referral: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Updates the status of a referral.
     *
     * @param int $referralId The ID of the referral.
     * @param string $status The new status.
     * @return bool True on success, false on failure.
     */
    public function updateReferralStatus(int $referralId, string $status): bool
    {
        try {
            $stmt = $this->pdo->prepare("UPDATE referrals SET status = ? WHERE id = ?");
            return $stmt->execute([$status, $referralId]);
        } catch (PDOException $e) {
            error_log("Error updating referral status: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Adds referral earnings to a user's account.
     *
     * @param int $userId The ID of the user.
     * @param float $amount The amount of referral earnings to add.
     * @return bool True on success, false on failure.
     */
    public function addReferralEarnings(int $userId, float $amount): bool
    {
        try {
            $stmt = $this->pdo->prepare("UPDATE users SET referral_earnings = referral_earnings + ? WHERE id = ?");
            return $stmt->execute([$amount, $userId]);
        } catch (PDOException $e) {
            error_log("Error adding referral earnings: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Requests a withdrawal for a user.
     *
     * @param int $userId The ID of the user.
     * @param float $amount The amount to withdraw.
     * @param string $phoneNumber The phone number to send the withdrawal to.
     * @return bool True on success, false on failure.
     */
    public function requestWithdrawal(int $userId, float $amount, string $phoneNumber): bool
    {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO withdrawals (user_id, amount, phone_number) VALUES (?, ?, ?)");
            return $stmt->execute([$userId, $amount, $phoneNumber]);
        } catch (PDOException $e) {
            error_log("Error requesting withdrawal: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Gets the withdrawal requests for a user.
     *
     * @param int $userId The ID of the user.
     * @return array An array of the user's withdrawal requests.
     */
    public function getWithdrawalRequests(int $userId): array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM withdrawals WHERE user_id = ? ORDER BY request_date DESC");
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error fetching withdrawal requests: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Gets the initial deposit amount from the settings table.
     *
     * @return float The initial deposit amount.
     */
    public function getInitialDepositAmount(): float
    {
        try {
            $stmt = $this->pdo->prepare("SELECT setting_value FROM settings WHERE setting_name = 'initial_deposit'");
            $stmt->execute();
            $result = $stmt->fetch();
            return $result ? (float)$result['setting_value'] : 0.00;
        } catch (PDOException $e) {
            error_log("Error fetching initial deposit amount: " . $e->getMessage());
            return 0.00;
        }
    }

    /**
     * Checks the status of a transaction.
     *
     * @param int $userId The ID of the user.
     * @param string $merchantRequestId The merchant request ID.
     * @return string|null The status of the transaction, or null if not found.
     */
    public function checkTransactionStatus(int $userId, string $merchantRequestId): ?string
    {
        try {
            $stmt = $this->pdo->prepare("SELECT status FROM transactions WHERE user_id = ? AND merchant_request_id = ?");
            $stmt->execute([$userId, $merchantRequestId]);
            $result = $stmt->fetch();
            return $result ? $result['status'] : null;
        } catch (PDOException $e) {
            error_log("Error checking transaction status: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Checks if a username already exists.
     *
     * @param string $username The username to check.
     * @return bool True if the username exists, false otherwise.
     * @throws Exception If there is an error checking the username.
     */
    private function checkUsernameExists(string $username): bool
    {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$username]);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log("Error checking username: " . $e->getMessage());
            throw new Exception("Error checking username.");
        }
    }

    /**
     * Processes referrals for a user.
     *
     * @param int $userId The ID of the user.
     * @return void
     */
    public function processReferrals(int $userId): void
    {
        $initialDepositAmount = $this->getInitialDepositAmount();
        $currentUserBalance = $this->getBalance($userId);

        // Check if the user making the deposit has met the initial deposit requirement
        if ($currentUserBalance >= $initialDepositAmount) {

            // Get referral details for the current user (referred_id)
            $referralDetails = $this->pdo->prepare("SELECT id, referrer_id, referral_tier FROM referrals WHERE referred_id = ? AND status = 'pending'");
            $referralDetails->execute([$userId]);
            $referral = $referralDetails->fetch();

            if ($referral) {
                // Referral exists (user was referred by someone)
                $referrerId = $referral['referrer_id']; // First-tier referrer

                // Get the current balance of the first-tier referrer
                $referrerBalance = $this->getBalance($referrerId);

                // Check if the first-tier referrer's balance is also equal to or greater than the initial deposit
                if ($referrerBalance >= $initialDepositAmount) {

                    // Update first-tier referral status to 'successful'
                    $updateReferral = $this->pdo->prepare("UPDATE referrals SET status = 'successful' WHERE id = ?");
                    $updateReferral->execute([$referral['id']]);

                    // Calculate and add referral earnings (first tier)
                    $referralEarnings = $initialDepositAmount * 0.3;
                    $this->addReferralEarnings($referrerId, $referralEarnings);

                    // Check for a second-tier referrer
                    $secondTierReferrer = $this->pdo->prepare("SELECT id, referrer_id FROM referrals WHERE referred_id = ? AND referral_tier = 1 AND status = 'pending'");
                    $secondTierReferrer->execute([$referrerId]);
                    $secondTierReferral = $secondTierReferrer->fetch();

                    if ($secondTierReferral) {
                        // Get the current balance of the second-tier referrer
                        $secondTierReferrerId = $secondTierReferral['referrer_id'];
                        $secondTierReferrerBalance = $this->getBalance($secondTierReferrerId);

                        // Check if the second-tier referrer's balance is also equal to or greater than the initial deposit
                        if ($secondTierReferrerBalance >= $initialDepositAmount) {

                            // Add second-tier referral (tier 2)
                            $this->addReferral($secondTierReferral['referrer_id'], $userId, 2);

                            // Calculate and add referral earnings (second tier)
                            $secondTierEarnings = $initialDepositAmount * 0.1;
                            $this->addReferralEarnings($secondTierReferral['referrer_id'], $secondTierEarnings);

                            // Update second-tier referral status to 'successful'
                            $updateSecondTier = $this->pdo->prepare("UPDATE referrals SET status = 'successful' WHERE referred_id = ? AND referral_tier = 2");
                            $updateSecondTier->execute([$userId]);
                        }
                    }
                }
            }
        }
    }
}