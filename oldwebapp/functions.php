<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// Database connection function
function getPDOConnection()
{
    $host = DB_HOST;
    $db = DB_NAME;
    $user = DB_USER;
    $pass = DB_PASS;
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
    } catch (\PDOException $e) {
        throw new \PDOException($e->getMessage(), (int)$e->getCode());
    }
    return $pdo;
}

// Input sanitization function
function sanitizeInput(string $input)
{
    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input);
    return $input;
}

// Phone number validation function
/**
 * @param string $phone
 * @return string|bool
 */
function validatePhoneNumber(string $phone)
{
    // Remove any non-digit characters
    $phone = preg_replace('/\D/', '', $phone);

    // Check if the phone number starts with 254 and is 12 digits long
    if (substr($phone, 0, 3) == '254' && strlen($phone) == 12) {
        return $phone;
    } else {
        return false;
    }
}

// M-Pesa access token generation function
/**
 * @param string $consumerKey
 * @param string $consumerSecret
 * @return string|bool
 */
function generateMpesaAccessToken(string $consumerKey, string $consumerSecret)
{
    $url = MPESA_SANDBOX_URL . '/oauth/v1/generate?grant_type=client_credentials';
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . base64_encode($consumerKey . ':' . $consumerSecret)));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    $curl_response = curl_exec($curl);
    $response = json_decode($curl_response);
    if (isset($response->access_token)) {
        return $response->access_token;
    } else {
        return false;
    }
}

// M-Pesa STK push initiation function
/**
 * @param string $accessToken
 * @param int $businessShortCode
 * @param int $businessShortCodeT
 * @param string $passkey
 * @param float $amount
 * @param string $phoneNumber
 * @param string $accountReference
 * @param string $transactionDesc
 * @return array|bool
 */
function initiateMpesaSTKPush(
    string $accessToken,
    int $businessShortCode,
    int $businessShortCodeT,
    string $passkey,
    float $amount,
    string $phoneNumber,
    string $accountReference,
    string $transactionDesc
) {
    $url = MPESA_SANDBOX_URL . '/mpesa/stkpush/v1/processrequest';
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type:application/json', 'Authorization:Bearer ' . $accessToken)); //setting custom header

    $timestamp = date('YmdHis');
    $password = base64_encode($businessShortCode . $passkey . $timestamp);

    $curl_post_data = array(
        'BusinessShortCode' => $businessShortCode,
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => 'CustomerBuyGoodsOnline',
        'Amount' => $amount,
        'PartyA' => $phoneNumber,
        'PartyB' => $businessShortCodeT,
        'PhoneNumber' => $phoneNumber,
        'CallBackURL' => MPESA_CALLBACK_URL,
        'AccountReference' => $accountReference,
        'TransactionDesc' => $transactionDesc
    );

    $data_string = json_encode($curl_post_data);

    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);

    $curl_response = curl_exec($curl);
    $response = json_decode($curl_response, true);
    return $response;
}

function sendEmail($recipient, $subject, $body, $attachment = null) {
    $mail = new PHPMailer(true);

    try {
        //Server settings
        $mail->SMTPDebug = 0;                      //Enable verbose debug output
        $mail->isSMTP();                                            //Send using SMTP
        $mail->Host       = 'smtp.gmail.com';                     //Set the SMTP server to send through
        $mail->SMTPAuth   = true;                                   //Enable SMTP authentication
        $mail->Username   = GMAIL_USERNAME;                     //SMTP username
        $mail->Password   = GMAIL_PASSWORD;                               //SMTP password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;            //Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` encouraged
        $mail->Port       = 587;                                    //TCP port to connect to, use 465 for `PHPMailer::ENCRYPTION_SMTPS` above

        //Recipients
        $mail->setFrom(GMAIL_USERNAME, 'Afrikenkid');
        $mail->addAddress($recipient);     //Add a recipient

        //Attachments
        if ($attachment) {
            $mail->addAttachment($attachment);         //Add attachments
        }

        //Content
        $mail->isHTML(true);                                  //Set email format to HTML
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);

        $mail->send();
        return 'Message has been sent';
    } catch (Exception $e) {
        return "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
    }
}

function displayMessages($errors = [], $success = "") {
    if (!empty($errors)) {
        echo '<div class="alert alert-danger">';
        foreach ($errors as $error) {
            echo '<p>' . htmlspecialchars($error) . '</p>';
        }
        echo '</div>';
    }
    if (!empty($success)) {
        echo '<div class="alert alert-success">';
        echo '<p>' . htmlspecialchars($success) . '</p>';
        echo '</div>';
    }
}

function generateCsrfTokenInput() {
    return '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
}

function validateCsrfToken() {
    return isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token'];
}
