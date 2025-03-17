<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

class Payment
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Initiates an M-Pesa STK push.
     *
     * @param int $userId The ID of the user.
     * @param float $amount The amount to charge.
     * @param string $phone The phone number to charge (254...).
     * @return string A message indicating the result of the operation.
     * @throws Exception If the STK push fails.
     */
    public function initiateSTKPush(int $userId, float $amount, string $phone): string
    {
        if ($amount <= 0) {
            throw new Exception("Invalid amount.");
        }

        $phone = validatePhoneNumber($phone);
        if (!$phone) {
            throw new Exception("Invalid phone number.");
        }

        $accessToken = generateMpesaAccessToken(MPESA_CONSUMER_KEY, MPESA_CONSUMER_SECRET);
        if (!$accessToken) {
            throw new Exception("Failed to generate M-Pesa access token.");
        }

        $mpesaResponse = initiateMpesaSTKPush(
            $accessToken,
            MPESA_BUSINESS_SHORTCODE,
            MPESA_BUSINESS_SHORTCODET,
            MPESA_PASSKEY,
            $amount,
            $phone,
            MPESA_ACCOUNT_REFERENCE,
            MPESA_TRANSACTION_DESC
        );

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

    /**
     * Processes the M-Pesa callback.
     *
     * @return void
     */
    public function processCallback(): void
    {
        // Get all JSON files in confirmations directory
        $files = glob(CONFIRMATIONS_DIR . "*.json");

        // Process each file
        foreach ($files as $file) {
            $json_data = file_get_contents($file);

            // Decode the JSON data into a PHP associative array
            $data = json_decode($json_data, true);

            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                continue; // Skip if JSON is invalid
            }

            if (isset($data['Body']['stkCallback']) && isset($data['Body']['stkCallback']['MerchantRequestID']) && isset($data['Body']['stkCallback']['ResultCode'])) {
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
                        $transaction = $stmt->fetch();
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

    /**
     * Initiates a B2C withdrawal.
     *
     * @param int $userId The ID of the user.
     * @param float $amount The amount to withdraw.
     * @param string $phoneNumber The phone number to send the withdrawal to.
     * @return array An array containing the ConversationID and OriginatorConversationID.
     * @throws Exception If the B2C initiation fails.
     */
    public function initiateB2CWithdrawal(int $userId, float $amount, string $phoneNumber): array
    {
        global $mpesa_passkey;

        $accessToken = generateMpesaAccessToken(MPESA_CONSUMER_KEY, MPESA_CONSUMER_SECRET);
        if (!$accessToken) {
            throw new Exception("Failed to generate M-Pesa access token.");
        }
        $shortcode = MPESA_BUSINESS_SHORTCODE;
        $curl = curl_init();
        $timestamp = date('YmdHis');
        $password = base64_encode($shortcode . $mpesa_passkey . $timestamp);
        $transactionRef = "TR" . $timestamp . "_" . $userId; // Unique transaction reference

        curl_setopt_array(
            $curl,
            array(
                CURLOPT_URL => MPESA_B2C_API_URL, // Sandbox URL
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $accessToken
                ),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode(
                    array(
                        'InitiatorName' => 'testapi',
                        'SecurityCredential' => MPESA_B2C_SECURITY_CREDENTIAL, // Use your security credential
                        'CommandID' => 'BusinessPayment',
                        'Amount' => $amount,
                        'PartyA' => MPESA_BUSINESS_SHORTCODE,
                        'PartyB' => $phoneNumber,
                        'Remarks' => 'Referral Earnings Withdrawal',
                        'QueueTimeOutURL' => MPESA_CALLBACK_URL,
                        'ResultURL' => MPESA_CALLBACK_URL, // Update to your domain.  Consider separate URL
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


    /**
     * Processes the B2C result callback.
     *
     * @return void
     */
    public function processB2CResultCallback(): void
    {
        // Get all JSON files in confirmations directory
        $files = glob(B2CCONFIRMATIONS_DIR . "*.json");

        // Process each file
        foreach ($files as $file) {
            $json_data = file_get_contents($file);

            // Decode the JSON data into a PHP associative array
            $data = json_decode($json_data, true);

            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                continue;  // Skip if JSON is invalid
            }

            if (isset($data['Result']) && isset($data['Result']['ResultType']) && isset($data['Result']['ResultCode']) && isset($data['Result']['OriginatorConversationID'])) {
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
                        $withdrawal = $stmt->fetch();
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

    /**
     * Processes the B2C timeout callback.
     *
     * @return void
     */
    public function processB2CTimeoutCallback(): void
    {
        // Get all JSON files in confirmations directory
        $files = glob(CONFIRMATIONS_DIR . "*.json");

        // Process each file
        foreach ($files as $file) {
            $json_data = file_get_contents($file);

            // Decode the JSON data into a PHP associative array
            $data = json_decode($json_data, true);

            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                continue; // Skip if JSON is invalid
            }

           if (isset($data['Result']) && isset($data['Result']['ResultType']) && isset($data['Result']['ResultCode']) && isset($data['Result']['OriginatorConversationID'])) {
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
