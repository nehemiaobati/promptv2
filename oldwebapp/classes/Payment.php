<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

class Payment {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function initiateSTKPush($userId, $amount, $phone) {
        global $mpesa_consumer_key, $mpesa_consumer_secret, $mpesa_business_shortcode,$mpesa_business_shortcodet, $mpesa_passkey, $mpesa_account_reference, $mpesa_transaction_desc;

        if ($amount <= 0) {
            throw new Exception("Invalid amount.");
        }

        $phone = validatePhoneNumber($phone);
        if (!$phone) {
            throw new Exception("Invalid phone number.");
        }

        $accessToken = generateMpesaAccessToken($mpesa_consumer_key, $mpesa_consumer_secret);
        if (!$accessToken) {
            throw new Exception("Failed to generate M-Pesa access token.");
        }

        $mpesaResponse = initiateMpesaSTKPush($accessToken, $mpesa_business_shortcode,$mpesa_business_shortcodet, $mpesa_passkey, $amount, $phone, $mpesa_account_reference, $mpesa_transaction_desc);

        if (isset($mpesaResponse['ResponseCode']) && $mpesaResponse['ResponseCode'] == "0") {
            $merchantRequestID = $mpesaResponse['MerchantRequestID'];
            $checkoutRequestID = $mpesaResponse['CheckoutRequestID'];

            try {
                $stmt = $this->pdo->prepare("INSERT INTO transactions (user_id, merchant_request_id, checkout_request_id, amount, status) VALUES (?, ?, ?, ?, 'pending')");
                $stmt->execute([$userId, $merchantRequestID, $checkoutRequestID, $amount]);
                return "M-Pesa STK push initiated. Please complete the payment on your phone.";
            } catch (PDOException $e) {
                error_log("Error saving transaction: " . $e->getMessage());
                throw new Exception("Error saving transaction.");
            }
        } else {
            throw new Exception("M-Pesa STK push failed. Please try again.");
        }
    }
    public function processCallback() {
       global $confirmations_dir;
        // Get all JSON files in confirmations directory
         $files = glob($confirmations_dir . "*.json");
         // Process each file
         foreach ($files as $file) {
             $json_data = file_get_contents($file);
            // Decode the JSON data into a PHP associative array
             $data = json_decode($json_data, true);
             if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                 continue;
              }
              if (isset($data['Body']['stkCallback'])) {
                 $stkCallback = $data['Body']['stkCallback'];
                 $merchantRequestID = $stkCallback['MerchantRequestID'];
                 $resultCode = $stkCallback['ResultCode'];

                 if ($resultCode == 0) {
                     // Payment successful
                     $mpesaReceiptNumber = null;
                     if (isset($stkCallback['CallbackMetadata']['Item'])) {
                         foreach ($stkCallback['CallbackMetadata']['Item'] as $item) {
                             if ($item['Name'] == 'MpesaReceiptNumber') {
                                 $mpesaReceiptNumber = $item['Value'];
                                 break;
                             }
                         }
                     }

                     try {
                         $this->pdo->beginTransaction();

                         // Update transaction status
                         $stmt = $this->pdo->prepare("UPDATE transactions SET mpesa_receipt_number = ?, status = 'success' WHERE merchant_request_id = ?");
                         $stmt->execute([$mpesaReceiptNumber, $merchantRequestID]);

                         // Get transaction details
                         $stmt = $this->pdo->prepare("SELECT amount, user_id FROM transactions WHERE merchant_request_id = ?");
                         $stmt->execute([$merchantRequestID]);
                         $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
                         $amount = $transaction['amount'];
                         $userId = $transaction['user_id'];

                         // Update user balance
                         $stmt = $this->pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                         $stmt->execute([$amount, $userId]);

                         $this->pdo->commit();

                         // Delete the processed JSON file
                         unlink($file);
                     } catch (PDOException $e) {
                         $this->pdo->rollBack();
                         error_log("Error processing M-Pesa callback: " . $e->getMessage());
                     }
                 } else {
                     // Payment failed
                     try {
                         // Update transaction status
                         $stmt = $this->pdo->prepare("UPDATE transactions SET status = 'failed' WHERE merchant_request_id = ?");
                         $stmt->execute([$merchantRequestID]);

                         // Delete the processed JSON file
                         unlink($file);
                     } catch (PDOException $e) {
                         error_log("Error processing M-Pesa callback: " . $e->getMessage());
                     }
                 }
             }
         }
     }
    public function initiateB2CWithdrawal($userId, $amount, $phoneNumber) {
        global $mpesa_consumer_key, $mpesa_consumer_secret,$mpesa_business_shortcode,$mpesa_passkey;
        $accessToken = generateMpesaAccessToken($mpesa_consumer_key, $mpesa_consumer_secret);
        if (!$accessToken) {
            throw new Exception("Failed to generate M-Pesa access token.");
        }
        $shortcode = $mpesa_business_shortcode;
        $curl = curl_init();
        $timestamp = date('YmdHis');
        $password = base64_encode($shortcode . $mpesa_passkey . $timestamp);
        $transactionRef = "TR" . $timestamp . "_" . $userId; // Unique transaction reference

        curl_setopt_array(
            $curl,
            array(
                CURLOPT_URL => 'https://sandbox.safaricom.co.ke/mpesa/b2c/v1/paymentrequest', // Sandbox URL
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $accessToken
                ),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode(
                    array(
                        'InitiatorName' => 'testapi',
                        'SecurityCredential' => 'EsJocK7+NjqZPC3I3EO+TbvS+xVb9TymWwaKABoaZr/Z/n0UysSs..', // Use your security credential
                        'CommandID' => 'BusinessPayment',
                        'Amount' => $amount,
                        'PartyA' => $mpesa_business_shortcode,
                        'PartyB' => $phoneNumber,
                        'Remarks' => 'Referral Earnings Withdrawal',
                        'QueueTimeOutURL' => 'https://afrikenkid.com/confirmation/b2ctimeout.php',
                        'ResultURL' => 'https://afrikenkid.com/confirmation/b2cresult.php', // Update to your domain
                        'Occasion' => 'Referral Withdrawal',
                        'OriginatorConversationID' => $transactionRef
                    )
                )
            )
        );

        $response = curl_exec($curl);

        if ($response === false) {
            $error = curl_error($curl);
            curl_close($curl);
            throw new Exception("B2C API request failed: " . $error);
        }
        $responseCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $decodedResponse = json_decode($response, true);
        if ($responseCode != 200 || (isset($decodedResponse['ResponseCode']) && $decodedResponse['ResponseCode'] != "0")) {
            error_log("B2C API request failed: " . $response);
            throw new Exception("Failed to initiate B2C withdrawal. Error code: " . $responseCode . ", Response: " . $response);
        }
        
        return array(
            'ConversationID' => $decodedResponse['ConversationID'],
            'OriginatorConversationID' => $decodedResponse['OriginatorConversationID'],
             'ResponseDescription' => $decodedResponse['ResponseDescription']
        );
    }
 
 

    
    // ... (initiateSTKPush, processCallback, initiateB2CWithdrawal, processB2CTimeoutCallback - these methods remain unchanged) ...
     
    public function processB2CResultCallback() {
        global $b2cconfirmations_dir;
        // Get all JSON files in confirmations directory
        $files = glob($b2cconfirmations_dir . "*.json");
        // Process each file
        foreach ($files as $file) {
            $json_data = file_get_contents($file);
            // Decode the JSON data into a PHP associative array
            $data = json_decode($json_data, true);
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                continue;
            }
            if (isset($data['Result'])) {
                $result = $data['Result'];
                $resultType = $result['ResultType'];
                $resultCode = $result['ResultCode'];
                $originatorConversationID = $result['OriginatorConversationID'];

                if ($resultCode == 0 && $resultType == 0) {
                    // B2C Payment successful
                    try {
                        $this->pdo->beginTransaction();

                        // Update withdrawal status to 'approved'
                        $stmt = $this->pdo->prepare("UPDATE withdrawals SET status = 'approved', transaction_id = ? WHERE transaction_id = ?");
                        $stmt->execute([$result['TransactionID'], $originatorConversationID]);

                        // Find user_id by transaction_id (OriginatorConversationID)
                        $stmt = $this->pdo->prepare("SELECT user_id, amount FROM withdrawals WHERE transaction_id = ?");
                        $stmt->execute([$originatorConversationID]);
                        $withdrawal = $stmt->fetch(PDO::FETCH_ASSOC);
                        $userId = $withdrawal['user_id'];
                        $amount = $withdrawal['amount'];

                        // Deduct the withdrawn amount from referral_earnings
                        $stmt = $this->pdo->prepare("UPDATE users SET referral_earnings = referral_earnings - ? WHERE id = ?");
                        $stmt->execute([$amount, $userId]);

                        $this->pdo->commit();

                        // Delete the processed JSON file
                        unlink($file);
                    } catch (PDOException $e) {
                        $this->pdo->rollBack();
                        error_log("Error processing B2C result callback: " . $e->getMessage());
                    }
                } else {
                    // B2C Payment failed - update withdrawal status to 'failed'
                    try {
                        // Update withdrawal status to 'failed'
                        $stmt = $this->pdo->prepare("UPDATE withdrawals SET status = 'failed' WHERE transaction_id = ?");
                        $stmt->execute([$originatorConversationID]);

                        // Delete the processed JSON file
                        unlink($file);
                    } catch (PDOException $e) {
                        error_log("Error processing B2C result callback: " . $e->getMessage());
                    }
                }
            }
        }
    }
    public function processB2CTimeoutCallback() {
         global $confirmations_dir;
         // Get all JSON files in confirmations directory
         $files = glob($confirmations_dir . "*.json");
         // Process each file
         foreach ($files as $file) {
             $json_data = file_get_contents($file);
             // Decode the JSON data into a PHP associative array
             $data = json_decode($json_data, true);
             if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                 continue;
              }
              if (isset($data['Result'])) {
                 $result = $data['Result'];
                 $resultType = $result['ResultType'];
                 $resultCode = $result['ResultCode'];
                 $originatorConversationID = $result['OriginatorConversationID'];
                // B2C Payment failed - update withdrawal status to 'failed'
                 try {
                     // Update withdrawal status to 'failed'
                     $stmt = $this->pdo->prepare("UPDATE withdrawals SET status = 'failed' WHERE transaction_id = ?");
                     $stmt->execute([$originatorConversationID]);

                     // Delete the processed JSON file
                     unlink($file);
                 } catch (PDOException $e) {
                     error_log("Error processing B2C result callback: " . $e->getMessage());
                 }
             }
         }
     }
}