<?php
class User {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // existing code
    public function register($username, $password) {
        if (empty($username) || empty($password)) {
            throw new Exception("Username and password are required.");
        }

        if ($this->checkUsernameExists($username)) {
            throw new Exception("Username already taken.");
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        try {
            $stmt = $this->pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
            $stmt->execute([$username, $hashedPassword]);
            return true;
        } catch (PDOException $e) {
            error_log("Error creating user: " . $e->getMessage());
            throw new Exception("Error creating user.");
        }
    }

    public function login($username, $password) {
        if (empty($username) || empty($password)) {
            throw new Exception("Username and password are required.");
        }

        try {
            $stmt = $this->pdo->prepare("SELECT id, password FROM users WHERE username = ?");
            $stmt->execute([$username]);

            if ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (password_verify($password, $user['password'])) {
                    // Regenerate session ID on login for security
                    session_regenerate_id(true);

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

    public function getBalance($userId) {
        try {
            $stmt = $this->pdo->prepare("SELECT balance FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['balance'] : 0;
        } catch (PDOException $e) {
            error_log("Error fetching balance: " . $e->getMessage());
            return 0; // Or handle the error appropriately
        }
    }

    public function getReferralEarnings($userId) {
        try {
            $stmt = $this->pdo->prepare("SELECT referral_earnings FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['referral_earnings'] : 0;
        } catch (PDOException $e) {
            error_log("Error fetching referral earnings: " . $e->getMessage());
            return 0;
        }
    }

    public function getReferrals($userId) {
        try {
            $stmt = $this->pdo->prepare("SELECT u.username, r.status FROM referrals r LEFT JOIN users u ON r.referred_id = u.id WHERE r.referrer_id = ? AND r.referral_tier = 1");
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching referrals: " . $e->getMessage());
            return [];
        }
    }

    public function addReferral($referrerId, $referredId, $referralTier) {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO referrals (referrer_id, referred_id, referral_tier) VALUES (?, ?, ?)");
            $stmt->execute([$referrerId, $referredId, $referralTier]);
            return true;
        } catch (PDOException $e) {
            error_log("Error adding referral: " . $e->getMessage());
            return false;
        }
    }

    public function updateReferralStatus($referralId, $status) {
        try {
            $stmt = $this->pdo->prepare("UPDATE referrals SET status = ? WHERE id = ?");
            $stmt->execute([$status, $referralId]);
            return true;
        } catch (PDOException $e) {
            error_log("Error updating referral status: " . $e->getMessage());
            return false;
        }
    }

    public function addReferralEarnings($userId, $amount) {
        try {
            $stmt = $this->pdo->prepare("UPDATE users SET referral_earnings = referral_earnings + ? WHERE id = ?");
            $stmt->execute([$amount, $userId]);
            return true;
        } catch (PDOException $e) {
            error_log("Error adding referral earnings: " . $e->getMessage());
            return false;
        }
    }

    public function requestWithdrawal($userId, $amount, $phoneNumber) {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO withdrawals (user_id, amount, phone_number) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $amount, $phoneNumber]);
            return true;
        } catch (PDOException $e) {
            error_log("Error requesting withdrawal: " . $e->getMessage());
            return false;
        }
    }

    public function getWithdrawalRequests($userId) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM withdrawals WHERE user_id = ? ORDER BY request_date DESC");
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching withdrawal requests: " . $e->getMessage());
            return [];
        }
    }
    public function getInitialDepositAmount() {
        try {
            $stmt = $this->pdo->prepare("SELECT setting_value FROM settings WHERE setting_name = 'initial_deposit'");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? (float)$result['setting_value'] : 0;
        } catch (PDOException $e) {
            error_log("Error fetching initial deposit amount: " . $e->getMessage());
            return 0;
        }
    }

    public function checkTransactionStatus($userId, $merchantRequestId) {
        try {
            $stmt = $this->pdo->prepare("SELECT status FROM transactions WHERE user_id = ? AND merchant_request_id = ?");
            $stmt->execute([$userId, $merchantRequestId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['status'] : null;
        } catch (PDOException $e) {
            error_log("Error checking transaction status: " . $e->getMessage());
            return null;
        }
    }

    private function checkUsernameExists($username) {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$username]);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log("Error checking username: " . $e->getMessage());
            throw new Exception("Error checking username.");
        }
    }



    public function processReferrals($userId) {
        $initialDepositAmount = $this->getInitialDepositAmount();
        $currentUserBalance = $this->getBalance($userId);

        // Check if the user making the deposit has met the initial deposit requirement
        if ($currentUserBalance >= $initialDepositAmount) {

            // Get referral details for the current user (referred_id)
            $referralDetails = $this->pdo->prepare("SELECT id, referrer_id, referral_tier FROM referrals WHERE referred_id = ? AND status = 'pending'");
            $referralDetails->execute([$userId]);
            $referral = $referralDetails->fetch(PDO::FETCH_ASSOC);

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
                    $secondTierReferral = $secondTierReferrer->fetch(PDO::FETCH_ASSOC);

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