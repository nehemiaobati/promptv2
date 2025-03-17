-- Database: user_auth

-- Create the users table
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `balance` decimal(10,2) DEFAULT 0.00,
  `referral_earnings` decimal(10,2) DEFAULT 0.00,
  `role` enum('user','admin') DEFAULT 'user',
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_token_expires` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create the transactions table
CREATE TABLE `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `merchant_request_id` varchar(255) NOT NULL,
  `checkout_request_id` varchar(255) NOT NULL,
  `mpesa_receipt_number` varchar(255) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('pending','success','failed') NOT NULL,
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create the referrals table
CREATE TABLE `referrals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `referred_id` int(11) NOT NULL,
  `referrer_id` int(11) NOT NULL,
  `referral_tier` int(11) DEFAULT 1,
  `status` enum('pending','successful') DEFAULT 'pending',
  `referral_date` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `referred_id` (`referred_id`),
  KEY `referrer_id` (`referrer_id`),
  CONSTRAINT `referrals_ibfk_1` FOREIGN KEY (`referred_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `referrals_ibfk_2` FOREIGN KEY (`referrer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create the withdrawals table
CREATE TABLE `withdrawals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `status` enum('pending','approved','failed') DEFAULT 'pending',
  `request_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `transaction_id` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `withdrawals_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create the settings table
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_name` varchar(255) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_name` (`setting_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert initial settings (e.g., initial deposit amount)
INSERT INTO `settings` (`setting_name`, `setting_value`) VALUES
('initial_deposit', '100.00');

-- Add an admin user (optional, for initial setup)
-- Replace 'adminuser' and 'adminpassword' with your desired credentials
INSERT INTO `users` (`username`, `password`, `role`) VALUES
('adminuser', '$2y$10$YOUR_HASHED_PASSWORD_HERE', 'admin');
-- Note: You need to replace '$2y$10$YOUR_HASHED_PASSWORD_HERE' with a real hashed password.
-- You can generate a hashed password using PHP's password_hash() function.
-- Example:
-- $hashedPassword = password_hash('adminpassword', PASSWORD_DEFAULT);
-- echo $hashedPassword;
-- Then copy the output and paste it into the query above.

ALTER TABLE users ADD email VARCHAR(255) NOT NULL;