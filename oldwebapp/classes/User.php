<?php
require_once  __DIR__ . '/Admin.php';

class User
{
    private PDO $pdo; // Type hint the PDO object

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Generates a password reset token for a user.
     *
     * @param string $username The username.
     * @return string|null The reset token or null if user not found.
     * @throws Exception If there is an error generating the token.
     */
    public function generatePasswordResetToken(string $username): ?string
    {
        try {
            // Check if user exists
            if (!$this->checkUsernameExists($username)) {
                return null;
            }

            // Generate a random token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Store the token in the database
            $stmt = $this->pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE username = ?");
            $stmt->execute([$token, $expires, $username]);

            return $token;
        } catch (PDOException $e) {
            error_log("Error generating reset token: " . $e->getMessage());
            throw new Exception("Error generating reset token.");
        }
    }

    /**
     * Validates a password reset token.
     *
     * @param string $token The reset token.
     * @return array|null The user data if token is valid, null otherwise.
     */
    public function validatePasswordResetToken(string $token): ?array
    {
        try {
            // Debug: Log the token being validated
            error_log("Validating token: " . $token);

            // First check if the token exists
            $checkToken = $this->pdo->prepare("SELECT id, username, reset_token_expires FROM users WHERE reset_token = ?");
            $checkToken->execute([$token]);
            $user = $checkToken->fetch();

            if (!$user) {
                error_log("Token not found in database");
                return null;
            }

            // Debug: Log the expiration time
            error_log("Token expires at: " . $user['reset_token_expires'] . ", Current time: " . date('Y-m-d H:i:s'));

            // Check if token is expired
            $currentTime = new DateTime();
            $expiryTime = new DateTime($user['reset_token_expires']);

            if ($currentTime > $expiryTime) {
                error_log("Token has expired");
                return null;
            }

            return [
                'id' => $user['id'],
                'username' => $user['username']
            ];
        } catch (PDOException $e) {
            error_log("Error validating reset token: " . $e->getMessage());
            return null;
        } catch (Exception $e) {
            error_log("General error validating token: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Resets a user's password.
     *
     * @param string $token The reset token.
     * @param string $newPassword The new password.
     * @return bool True on success, false on failure.
     * @throws Exception If there is an error resetting the password.
     */
    public function resetPassword(string $token, string $newPassword): bool
    {
        try {
            // Validate token
            $user = $this->validatePasswordResetToken($token);
            if (!$user) {
                throw new Exception("Invalid or expired reset token.");
            }

            // Hash the new password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

            // Update the password and clear the reset token
            $stmt = $this->pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?");
            return $stmt->execute([$hashedPassword, $user['id']]);
        } catch (PDOException $e) {
            error_log("Error resetting password: " . $e->getMessage());
            throw new Exception("Error resetting password.");
        }
    }

    /**
     * Registers a new user.
     *
     * @param string $username The username.
     * @param string $password The password.
     * @return bool True on success, false on failure.
     * @throws Exception If registration fails.
     */
    public function register(string $username, string $email, string $password): bool
    {
        if (empty($username) || empty($email) || empty($password)) {
            throw new Exception("Username, email and password are required.");
        }

        if ($this->checkUsernameExists($username)) {
            throw new Exception("Username already taken.");
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        try {
            $stmt = $this->pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            return $stmt->execute([$username, $email, $hashedPassword]); // Directly return the result of execute()
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
            $stmt = $this->pdo->prepare("SELECT u.username, r.status, r.referral_tier FROM referrals r LEFT JOIN users u ON r.referred_id = u.id WHERE r.referrer_id = ?");
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
     * @param int $referredId The ID of the referred user.
     * @param int $referrerId The ID of the referrer.
     * @return bool True on success, false on failure.
     */
    public function addReferral(int $referredId, int $referrerId): bool
    {
        try {
            // Check if the referral already exists
            $stmt = $this->pdo->prepare("SELECT * FROM referrals WHERE referred_id = ? AND referrer_id = ?");
            $stmt->execute([$referredId, $referrerId]);
            if ($stmt->fetch()) {
                return true; // Referral already exists, consider it a success
            }

            // Insert the referral record with default tier 1
            $stmt = $this->pdo->prepare("INSERT INTO referrals (referred_id, referrer_id, referral_tier) VALUES (?, ?, 1)");
            return $stmt->execute([$referredId, $referrerId]);
        } catch (PDOException $e) {
            error_log("Error adding referral: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Increments the referral count for a user.
     *
     * @param int $referrerId The ID of the referrer.
     * @return bool True on success, false on failure.
     */
    public function incrementReferralCount(int $referrerId): bool
    {
        try {
            // Check if the user exists
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE id = ?");
            $stmt->execute([$referrerId]);
            if (!$stmt->fetch()) {
                throw new Exception("Referrer user not found.");
            }
            // Increment the referral count for the referrer
            // In this case, we are not using referral_count, but we are using the referrals table
            return true;
        } catch (PDOException $e) {
            error_log("Error incrementing referral count: " . $e->getMessage());
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
        if (!$this->isUserEligibleForReferralBonus($userId)) {
            return;
        }

        $referral = $this->getFirstTierReferral($userId);

        if (!$referral) {
            return;
        }

        $this->processFirstTierReferral($referral);
    }

    /**
     * Checks if a user is eligible for a referral bonus.
     *
     * @param int $userId The ID of the user.
     * @return bool True if the user is eligible, false otherwise.
     */
    private function isUserEligibleForReferralBonus(int $userId): bool
    {
        $admin = new Admin($this->pdo);
        $initialDepositAmount = $admin->getInitialDepositAmount();
        $currentUserBalance = $this->getBalance($userId);
        return $currentUserBalance >= $initialDepositAmount;
    }

    /**
     * Gets the first-tier referral for a user.
     *
     * @param int $userId The ID of the user.
     * @return array|null The first-tier referral, or null if not found.
     */
    /**
     * Gets the first-tier referral for a user.
     *
     * @param int $userId The ID of the user (the referred user).
     * @return array|null The first-tier referral, or null if not found.
     */
    private function getFirstTierReferral(int $userId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT id, referrer_id, referral_tier FROM referrals WHERE referred_id = ? AND status = 'pending'");
        $stmt->execute([$userId]);
        $referral = $stmt->fetch(PDO::FETCH_ASSOC);
        return $referral ? $referral : null;
    }

    /**
     * Processes the first-tier referral bonus.
     *
     * @param array $referral The first-tier referral.
     * @return void
     */
    private function processFirstTierReferral(array $referral): void
    {
        $admin = new Admin($this->pdo);
        $initialDepositAmount = $admin->getInitialDepositAmount();
        $referrerId = $referral['referrer_id'];
        $referrerBalance = $this->getBalance($referrerId);

        if ($referrerBalance >= $initialDepositAmount) {
            // Update first-tier referral status to 'successful'
            $updateReferral = $this->pdo->prepare("UPDATE referrals SET status = 'successful' WHERE id = ?");
            $updateReferral->execute([$referral['id']]);

            // Calculate and add referral earnings (first tier)
            $referralEarnings = $initialDepositAmount * 0.3;
            $this->addReferralEarnings($referrerId, $referralEarnings);

            $this->processSecondTierReferral($referrerId, $referral['referred_id']);
        }
    }

    /**
     * Processes the second-tier referral bonus.
     *
     * @param int $referrerId The ID of the first-tier referrer.
     * @param int $userId The ID of the referred user.
     * @return void
     */
    private function processSecondTierReferral(int $referrerId, int $userId): void
    {
        $admin = new Admin($this->pdo);
        $initialDepositAmount = $admin->getInitialDepositAmount();
        $secondTierReferrer = $this->pdo->prepare("SELECT id, referrer_id FROM referrals WHERE referred_id = ? AND referral_tier = 1 AND status = 'successful'");
        $secondTierReferrer->execute([$referrerId]);
        $secondTierReferral = $secondTierReferrer->fetch();

        if ($secondTierReferral) {
            $secondTierReferrerId = $secondTierReferral['referrer_id'];
            $secondTierReferrerBalance = $this->getBalance($secondTierReferrerId);

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

    /**
     * Gets the user's email address.
     *
     * @param string $username The username.
     * @return string|null The user's email address, or null if not found.
     */
    public function getUserEmail(string $username): ?string
    {
        try {
            $stmt = $this->pdo->prepare("SELECT email FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $result = $stmt->fetch();
            return $result ? $result['email'] : null;
        } catch (PDOException $e) {
            error_log("Error fetching user email: " . $e->getMessage());
            return null;
        }
    }
}
