<?php

// Database configuration
const DB_HOST = "127.0.0.1";
const DB_USER = "root";
const DB_PASS = "";
const DB_NAME = "user_auth3";

// M-Pesa API Configuration (Replace with your actual credentials)
const MPESA_CONSUMER_KEY    = "cnldxAMop1mdoGS4v1SYa8jTfZ3xsS7hGta9YFzx87yHWWGI";
const MPESA_CONSUMER_SECRET = "BH5GHoGd7aOIkw3sSudzpLXZV1HrfEwilA2WGux0WZXE4iJ5TAAvG8t6ZAa7X0Ph";
const MPESA_BUSINESS_SHORTCODE = 174379;
const MPESA_BUSINESS_SHORTCODET = 3164444;
const MPESA_PASSKEY = "bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919";
const MPESA_CALLBACK_URL = "https://afrikenkid.com/confirmation/confirmation.php"; // Update with your domain
const MPESA_ACCOUNT_REFERENCE = "Payment";
const MPESA_TRANSACTION_DESC = "Payment";
const CONFIRMATIONS_DIR = __DIR__ . "/confirmation/";
const B2CCONFIRMATIONS_DIR = __DIR__ . "/confirmation/b2cresult/";

// M-Pesa API URLs
const MPESA_B2C_API_URL = 'https://sandbox.safaricom.co.ke/mpesa/b2c/v1/paymentrequest';
const MPESA_SANDBOX_URL = 'https://sandbox.safaricom.co.ke';
const MPESA_PRODUCTION_URL = 'https://api.safaricom.co.ke';
const MPESA_B2C_SECURITY_CREDENTIAL = '{{MPESA_B2C_SECURITY_CREDENTIAL}}';

// Session Configuration (for enhanced security) - Consider setting these in php.ini
//ini_set('session.cookie_httponly', 1); // Already done via ini_set previously
//ini_set('session.use_only_cookies', 1);  // Already done via ini_set previously
//ini_set('session.cookie_secure', 1);   // Only if you have HTTPS,  Already done via ini_set previously

const GMAIL_USERNAME = "nehemiahobati5@gmail.com"; // Replace with your Gmail address
const GMAIL_PASSWORD = "wuyccewlxxcildml"; // Replace with your App Password
