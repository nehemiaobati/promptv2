<?php
// Database configuration
$db_host = "localhost";
$db_user = "root"; // Or your database username
$db_pass = "";     // Or your database password
$db_name = "user_auth3";

// M-Pesa API Configuration (Replace with your actual credentials)
$mpesa_consumer_key    = "cnldxAMop1mdoGS4v1SYa8jTfZ3xsS7hGta9YFzx87yHWWGI"; 
$mpesa_consumer_secret = "BH5GHoGd7aOIkw3sSudzpLXZV1HrfEwilA2WGux0WZXE4iJ5TAAvG8t6ZAa7X0Ph"; 
$mpesa_business_shortcode = 174379; 
$mpesa_business_shortcodet = 3164444;
$mpesa_passkey = "bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919";
$mpesa_callback_url = "https://afrikenkid.com/confirmation/confirmation.php"; // Update with your domain
$mpesa_account_reference = "Payment";
$mpesa_transaction_desc = "Payment";
$confirmations_dir = __DIR__ . "/confirmation/";
$b2cconfirmations_dir = __DIR__ . "/confirmation/b2cresult/";


// Session Configuration (for enhanced security)
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1); // Only if you have HTTPS