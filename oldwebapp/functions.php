<?php

require_once __DIR__ . '/config.php';

// Establishes a PDO database connection
function getPDOConnection() {
    global $db_host, $db_user, $db_pass, $db_name;
    try {
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        die("Database connection failed. Please check logs.");
    }
}

// Sanitizes user input to prevent XSS
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Validates Kenyan phone numbers (254...)
function validatePhoneNumber($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    return preg_match('/^254[0-9]{9}$/', $phone) ? $phone : false;
}


// Generates an M-Pesa API access token
function generateMpesaAccessToken($consumerKey, $consumerSecret) {
    $url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials'; // Sandbox for testing
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    $credentials = base64_encode($consumerKey . ':' . $consumerSecret);
    curl_setopt($curl, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . $credentials]);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $curl_response = curl_exec($curl);
    $response = json_decode($curl_response);
    curl_close($curl);

    if (isset($response->access_token)) {
        return $response->access_token;
    }

    error_log("M-Pesa token generation failed: " . json_encode($response));
    return null;
}

// Initiates an M-Pesa STK push
function initiateMpesaSTKPush($accessToken, $businessShortcode,$businessShortcodet, $passkey, $amount, $phoneNumber, $accountReference, $transactionDesc) {
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
        'CallBackURL' => 'https://afrikenkid.com/confirmation/confirmation.php',
        'AccountReference' => $accountReference,
        'TransactionDesc' => $transactionDesc
    ];

    $data_string = json_encode($curl_post_data);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);

    $curl_response = curl_exec($curl);
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