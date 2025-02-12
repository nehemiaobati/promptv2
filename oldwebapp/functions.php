<?php

require_once __DIR__ . '/config.php';

/**
 * Establishes a PDO database connection.
 *
 * @return PDO The PDO connection object.
 * @throws PDOException If the connection fails.
 */
function getPDOConnection(): PDO
{
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Set default fetch mode
    ];

    try {
        return new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        die("Database connection failed. Please check logs."); // Consider a friendlier error page in production
    }
}

/**
 * Sanitizes user input to prevent XSS.
 *
 * @param string $input The input string to sanitize.
 * @return string The sanitized string.
 */
function sanitizeInput(string $input): string
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Validates Kenyan phone numbers (254...).
 *
 * @param string $phone The phone number to validate.
 * @return string|false The validated phone number (254...) or false if invalid.
 */
function validatePhoneNumber(string $phone)
{
    $phone = preg_replace('/[^0-9]/', '', $phone);
    return preg_match('/^254[0-9]{9}$/', $phone) ? $phone : false;
}

/**
 * Generates an M-Pesa API access token.
 *
 * @param string $consumerKey The M-Pesa consumer key.
 * @param string $consumerSecret The M-Pesa consumer secret.
 * @return string|null The access token or null on failure.
 */
function generateMpesaAccessToken(string $consumerKey, string $consumerSecret): ?string
{
    $url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials'; // Sandbox for testing
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    $credentials = base64_encode($consumerKey . ':' . $consumerSecret);
    curl_setopt($curl, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . $credentials]);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $curl_response = curl_exec($curl);

    if ($curl_response === false) {
        error_log("M-Pesa token generation failed: " . curl_error($curl));  // Log the curl error
        curl_close($curl);
        return null;
    }

    $response = json_decode($curl_response);
    curl_close($curl);

    if (isset($response->access_token)) {
        return $response->access_token;
    }

    error_log("M-Pesa token generation failed: " . json_encode($response));
    return null;
}

/**
 * Initiates an M-Pesa STK push.
 *
 * @param string $accessToken The M-Pesa access token.
 * @param int $businessShortcode The business shortcode.
 * @param int $businessShortcodet The  business shortcode target.
 * @param string $passkey The passkey.
 * @param float $amount The amount to charge.
 * @param string $phoneNumber The phone number to charge (254...).
 * @param string $accountReference The account reference.
 * @param string $transactionDesc The transaction description.
 * @return array|null The M-Pesa response or null on failure.
 */
function initiateMpesaSTKPush(string $accessToken, int $businessShortcode, int $businessShortcodet, string $passkey, float $amount, string $phoneNumber, string $accountReference, string $transactionDesc): ?array
{
    $timestamp = date('YmdHis');
    $password = base64_encode($businessShortcode . $passkey . $timestamp);

    $url = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest'; // Sandbox for testing
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type:application/json', 'Authorization:Bearer ' . $accessToken]);

    $curl_post_data = [
        'BusinessShortCode' => $businessShortcode,
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => 'CustomerBuyGoodsOnline', // Or 'CustomerPayBillOnline'
        'Amount' => $amount,
        'PartyA' => $phoneNumber,
        'PartyB' => $businessShortcodet,
        'PhoneNumber' => $phoneNumber,
        'CallBackURL' => MPESA_CALLBACK_URL,
        'AccountReference' => $accountReference,
        'TransactionDesc' => $transactionDesc
    ];

    $data_string = json_encode($curl_post_data);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);

    $curl_response = curl_exec($curl);

    if ($curl_response === false) {
        error_log("M-Pesa STK push error: " . curl_error($curl));
        curl_close($curl);
        return null;
    }

    $response = json_decode($curl_response, true);
    curl_close($curl);

    if ($response === null || !isset($response['ResponseCode'])) {
        error_log("M-Pesa STK push error: Invalid response format.");
        return null;
    }

    if ($response['ResponseCode'] != "0") {
        error_log("M-Pesa STK push failed: " . json_encode($response));
    }

    return $response;
}