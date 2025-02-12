<?php

class Admin
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Checks if a user is an admin.
     *
     * @param int $userId The user ID.
     * @return bool True if the user is an admin, false otherwise.
     */
    public function isAdmin(int $userId): bool
    {
        // Simple check - you might have a separate 'admins' table or a role in 'users'
        return $userId == 1; // Assuming admin has ID 1 in the 'users' table
    }

    /**
     * Gets all users.
     *
     * @return array An array of all users.
     */
    public function getAllUsers(): array
    {
        try {
            $stmt = $this->pdo->query("SELECT id, username, balance, referral_earnings FROM users");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error fetching all users: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Gets all pending withdrawals.
     *
     * @return array An array of all pending withdrawals.
     */
    public function getPendingWithdrawals(): array
    {
        try {
            $stmt = $this->pdo->query("SELECT w.id, w.user_id, w.amount,w.phone_number, w.status, w.request_date, u.username FROM withdrawals w JOIN users u ON w.user_id = u.id WHERE w.status = 'pending' ORDER BY w.request_date ASC");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error fetching pending withdrawals: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Approves a withdrawal.
     *
     * @param int $withdrawalId The ID of the withdrawal.
     * @param int $userId The ID of the user.
     * @param float $amount The amount to withdraw.
     * @param string $phoneNumber The phone number to send the withdrawal to.
     * @return string A message indicating the result of the operation.
     * @throws Exception If the B2C initiation fails.
     */
    public function approveWithdrawal(int $withdrawalId, int $userId, float $amount, string $phoneNumber): string
    {
        try {
            // Initiate B2C payment
            $payment = new Payment($this->pdo);
            $b2cResult = $payment->initiateB2CWithdrawal($userId, $amount, $phoneNumber);

            // Check if B2C was initiated successfully
            if (isset($b2cResult['ConversationID'])) {
                // Update withdrawal record with transaction_id from B2C response
                $stmt = $this->pdo->prepare("UPDATE withdrawals SET transaction_id = ? WHERE id = ?");
                $stmt->execute([$b2cResult['OriginatorConversationID'], $withdrawalId]);

                return "Withdrawal initiated successfully. Conversation ID: " . $b2cResult['ConversationID'];
            } else {
                // Handle case where B2C initiation failed
                throw new Exception("Failed to initiate B2C withdrawal.");
            }
        } catch (Exception $e) {
            error_log("Error approving withdrawal: " . $e->getMessage());
            return "Error processing withdrawal request: " . $e->getMessage(); // Include the exception message
        }
    }

    /**
     * Suspends a withdrawal.
     *
     * @param int $withdrawalId The ID of the withdrawal.
     * @return bool True on success, false on failure.
     */
    public function suspendWithdrawal(int $withdrawalId): bool
    {
        try {
            $stmt = $this->pdo->prepare("UPDATE withdrawals SET status = 'failed' WHERE id = ?");
            return $stmt->execute([$withdrawalId]);
        } catch (PDOException $e) {
            error_log("Error updating withdrawal status: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Approves all pending withdrawals.
     *
     * @return bool True on success, false on failure.
     */
    public function approveAllPendingWithdrawals(): bool
    {
        $this->pdo->beginTransaction();
        try {
            $pendingWithdrawals = $this->getPendingWithdrawals();
            foreach ($pendingWithdrawals as $withdrawal) {
                $phoneNumber = $withdrawal['phone_number'];
                $result = $this->approveWithdrawal($withdrawal['id'], $withdrawal['user_id'], $withdrawal['amount'], $phoneNumber);
                if (strpos($result, "Error") === 0) {  //Simplified Error checking
                    throw new Exception("Failed to approve withdrawal ID: " . $withdrawal['id'] . ".  Reason: " . $result); //Include the reason
                }
            }
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error approving all withdrawals: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Sets the initial deposit amount.
     *
     * @param float $amount The initial deposit amount.
     * @return bool True on success, false on failure.
     */
    public function setInitialDeposit(float $amount): bool
    {
        try {
            $stmt = $this->pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_name = 'initial_deposit'");
            return $stmt->execute([$amount]);
        } catch (PDOException $e) {
            error_log("Error setting initial deposit: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Gets the initial deposit amount.
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
}