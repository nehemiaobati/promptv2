<?php
class Admin {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function isAdmin($userId) {
        // Simple check - you might have a separate 'admins' table or a role in 'users'
        return $userId == 1; // Assuming admin has ID 1 in the 'users' table
    }

    public function getAllUsers() {
        try {
            $stmt = $this->pdo->query("SELECT id, username, balance, referral_earnings FROM users");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching all users: " . $e->getMessage());
            return [];
        }
    }

    public function getPendingWithdrawals() {
        try {
            $stmt = $this->pdo->query("SELECT w.id, w.user_id, w.amount,w.phone_number, w.status, w.request_date, u.username FROM withdrawals w JOIN users u ON w.user_id = u.id WHERE w.status = 'pending' ORDER BY w.request_date ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching pending withdrawals: " . $e->getMessage());
            return [];
        }
    }

    public function approveWithdrawal($withdrawalId, $userId, $amount, $phoneNumber) {
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
                return "Failed to initiate B2C withdrawal.";
            }
        } catch (Exception $e) {
            error_log("Error approving withdrawal: " . $e->getMessage());
            return "Error processing withdrawal request.";
        }
    }

    public function suspendWithdrawal($withdrawalId) {
        try {
            $stmt = $this->pdo->prepare("UPDATE withdrawals SET status = 'failed' WHERE id = ?");
            $stmt->execute([$withdrawalId]);
            return true;
        } catch (PDOException $e) {
            error_log("Error updating withdrawal status: " . $e->getMessage());
            return false;
        }
    }
     public function approveAllPendingWithdrawals() {
         $this->pdo->beginTransaction();
         try {
             $pendingWithdrawals = $this->getPendingWithdrawals();
             foreach ($pendingWithdrawals as $withdrawal) {
                 $phoneNumber = $withdrawal['phone_number']; 
                 $result = $this->approveWithdrawal($withdrawal['id'], $withdrawal['user_id'], $withdrawal['amount'], $phoneNumber);
                if (!$result) {
                     throw new Exception("Failed to approve withdrawal ID: " . $withdrawal['id']);
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

    public function setInitialDeposit($amount) {
        try {
            $stmt = $this->pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_name = 'initial_deposit'");
            $stmt->execute([$amount]);
            return true;
        } catch (PDOException $e) {
            error_log("Error setting initial deposit: " . $e->getMessage());
            return false;
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

}