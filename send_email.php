<?php
require 'oldwebapp/config.php';
require 'oldwebapp/functions.php';

$recipient = 'nehemiahobati@gmail.com';
$subject = 'Test Email';
$body = 'This is a test email sent using the sendEmail function.';

$result = sendEmail($recipient, $subject, $body);

echo $result;
?>
