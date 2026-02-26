-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 25, 2026 at 07:37 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `network_platform`
--

-- --------------------------------------------------------

--
-- Table structure for table `investment_requests`
--

CREATE TABLE `investment_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `network` varchar(20) NOT NULL,
  `tx_hash` varchar(255) NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `admin_note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `investment_requests`
--

INSERT INTO `investment_requests` (`id`, `user_id`, `amount`, `network`, `tx_hash`, `status`, `admin_note`, `created_at`, `processed_at`) VALUES
(1, 24, 15000.00, 'TRC20', 'TXYZ1234567890abcdefghijklmnopqrstuvwxz', 'approved', NULL, '2026-02-25 09:33:40', '2026-02-25 09:33:51'),
(2, 24, 15000.00, 'TRC20', 'TXYZ1234567890abcdefghijklmnopqrstuvwxz', 'approved', NULL, '2026-02-25 09:33:58', '2026-02-25 09:34:16'),
(3, 24, 15000.00, 'TRC20', 'TXYZ1234567890abcdefghijklmnopqrstuvwxz', 'rejected', NULL, '2026-02-25 09:34:25', '2026-02-25 09:41:50');

-- --------------------------------------------------------

--
-- Table structure for table `milestone_rewards`
--

CREATE TABLE `milestone_rewards` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `milestone_code` varchar(50) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('daily_interest', '1.0'),
('last_cron_run', '2026-02-24'),
('min_withdrawal', '10.0'),
('site_name', 'Network Platform');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `amount` decimal(15,2) DEFAULT NULL,
  `type` enum('deposit','daily_profit','referral_bonus','withdrawal') DEFAULT NULL,
  `level` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `user_id`, `amount`, `type`, `level`, `description`, `created_at`) VALUES
(1, 8, 210.00, 'withdrawal', NULL, 'User requested withdrawal', '2026-02-23 19:34:42'),
(2, 20, 10.00, 'referral_bonus', 1, 'Level 1 bonus from User ID 21', '2026-02-24 16:03:54'),
(3, 8, 20.00, 'daily_profit', NULL, 'Daily 1% profit', '2026-02-24 19:17:02'),
(4, 20, 23.00, 'deposit', NULL, 'User wallet top-up', '2026-02-24 20:22:10'),
(5, 16, 123.00, 'deposit', NULL, 'User wallet top-up', '2026-02-25 08:21:13'),
(6, 16, 120.00, 'deposit', NULL, 'Activation credited', '2026-02-25 08:33:29'),
(7, 16, 200.00, 'deposit', NULL, 'Activation credited', '2026-02-25 08:33:45'),
(8, 20, 200.00, 'deposit', NULL, 'Activation credited', '2026-02-25 08:35:52'),
(9, 24, 15000.00, 'deposit', NULL, 'Crypto Deposit Approved credited', '2026-02-25 09:33:51'),
(10, 21, 750.00, 'referral_bonus', 1, 'Level 1 bonus from User ID 24', '2026-02-25 09:33:51'),
(11, 20, 600.00, 'referral_bonus', 2, 'Level 2 bonus from User ID 24', '2026-02-25 09:33:51'),
(12, 24, 15000.00, 'deposit', NULL, 'Crypto Deposit Approved credited', '2026-02-25 09:34:15'),
(13, 21, 750.00, 'referral_bonus', 1, 'Level 1 bonus from User ID 24', '2026-02-25 09:34:16'),
(14, 20, 600.00, 'referral_bonus', 2, 'Level 2 bonus from User ID 24', '2026-02-25 09:34:16');

-- --------------------------------------------------------

--
-- Table structure for table `transaction_logs`
--

CREATE TABLE `transaction_logs` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action_type` enum('add','subtract') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `reason` varchar(255) DEFAULT 'Manual Adjustment',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transaction_logs`
--

INSERT INTO `transaction_logs` (`id`, `admin_id`, `user_id`, `action_type`, `amount`, `reason`, `created_at`) VALUES
(1, 16, 16, 'add', 1.00, 'Manual Adjustment', '2026-02-23 21:16:37'),
(2, 16, 16, 'subtract', 1.00, 'Manual Adjustment', '2026-02-23 21:16:45'),
(3, 16, 16, 'add', 0.00, 'Manual Adjustment', '2026-02-23 21:33:17'),
(4, 16, 16, 'add', 1.00, 'Manual Adjustment', '2026-02-23 21:33:28'),
(5, 16, 16, 'subtract', 1.00, 'Manual Adjustment', '2026-02-23 21:33:44'),
(6, 0, 8, 'add', 20.00, 'Daily Profit', '2026-02-23 21:59:58'),
(7, 16, 8, 'add', 11.00, 'Manual Adjustment', '2026-02-24 10:45:35'),
(8, 16, 21, 'add', 12.00, 'Manual Adjustment', '2026-02-24 16:07:41'),
(9, 16, 17, 'add', 12.00, 'Manual Adjustment', '2026-02-24 19:55:29'),
(10, 16, 24, 'add', 500.00, 'Manual Adjustment', '2026-02-24 19:55:45'),
(11, 16, 23, 'add', 500.00, 'Manual Adjustment', '2026-02-24 19:55:55'),
(12, 16, 22, 'add', 100.00, 'Manual Adjustment', '2026-02-24 19:56:01'),
(13, 16, 22, 'add', 1000.00, 'Manual Adjustment', '2026-02-24 19:56:08'),
(14, 16, 20, 'add', 1000.00, 'Manual Adjustment', '2026-02-24 19:59:31'),
(15, 16, 28, 'add', 120.00, 'Manual Adjustment', '2026-02-25 09:46:01'),
(16, 16, 24, 'subtract', 12000.00, 'Manual Adjustment', '2026-02-25 09:49:40');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `referrer_id` int(11) DEFAULT NULL,
  `wallet_balance` decimal(15,2) DEFAULT 0.00,
  `investment_amount` decimal(15,2) DEFAULT 0.00,
  `user_rank` enum('Basic','Silver','Gold','Diamond') DEFAULT 'Basic',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_withdrawal_date` timestamp NULL DEFAULT NULL,
  `role` varchar(10) DEFAULT 'user',
  `status` enum('active','banned') DEFAULT 'active',
  `user_code` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `referrer_id`, `wallet_balance`, `investment_amount`, `user_rank`, `created_at`, `last_withdrawal_date`, `role`, `status`, `user_code`) VALUES
(8, 'admin', '$2y$10$ei9j7VKDLeyUM.DP.PAvZO1TiZIpqxhC18xt7//QGXe81ZoeGiB8i', 'hirani03332510535@gmail.com', NULL, 61.00, 2000.00, 'Basic', '2026-02-23 17:04:20', '2026-02-23 19:34:42', 'user', 'active', 'NP#9766226'),
(10, 'sahil', '$2y$10$vxvcbzXfkSocNZyT5AtuieZlcUyIu5.naEQQuCDnnQ2M2xb0/NfAO', 'sahilkkk@gmail.com', 8, 0.00, 0.00, 'Basic', '2026-02-23 17:16:56', NULL, 'user', 'active', 'NP#4150826'),
(11, 'lalit', '$2y$10$ISamoXZwlvPvCUehX1Z.i.77B1TnvjYtZHHQ86/HdHbgCPPNIqO76', 'lalit@gmail.com', 8, 0.00, 0.00, 'Basic', '2026-02-23 17:38:13', NULL, 'user', 'active', 'NP#9480026'),
(12, 'suneel ', '$2y$10$Phy/gIabD9txtotfTleMWeJBAJDOjrBA/gNIM0k9G.dT2fUQ9GV8i', 'suneel@gmail.com', NULL, 0.00, 0.00, 'Basic', '2026-02-23 19:19:47', NULL, 'user', 'active', 'NP#6318226'),
(13, 'lakkhan', '$2y$10$Q4N9JqMC7oiGqRPZFz.7.ewazRLm99wjmICp4PE.xvaTVvKlNez.2', 'lakhan@gmail.com', 8, 0.00, 0.00, 'Basic', '2026-02-23 19:22:52', NULL, 'user', 'active', 'NP#5094926'),
(14, 'JohnDoe', '$2y$10$8K.uS6Y1A2MvBvI1.k.K.u8R6y.k7L8m9n0p1q2r3s4t5u6v7w8x9y', 'user@test.com', NULL, 0.00, 0.00, 'Basic', '2026-02-23 19:54:44', NULL, 'user', 'active', 'NP#7664026'),
(16, 'SuperAdmin', '$2y$10$f01.Bl7dW/QIMyiUJdWuMOzSu1P/YR00HOs266fbrdWKjb5ruPTLu', 'admin@test.com', NULL, 134.00, 320.00, 'Basic', '2026-02-23 20:25:17', NULL, 'admin', 'active', 'NP#4690126'),
(17, 'kartik', '$2y$10$qcLCWupr2uENJqJwHpLPi.AyReGkIAzugbITRKeG9AmpaCJUirGAq', 'kumar@gmail.com', NULL, 112.00, 0.00, 'Basic', '2026-02-24 11:08:26', NULL, 'user', 'active', 'NP#5668026'),
(20, 'Suneel Timani', '$2y$10$8maaHiFLVC7IYd4s6pdDO.cmJKFb1P2ex42BFVKHhztxZU5Es5SF6', 'suneeltimani@gmail.com', NULL, 2033.00, 200.00, 'Basic', '2026-02-24 16:00:03', '2026-02-24 20:24:49', 'user', 'active', 'NP#7175226'),
(21, 'Lalit Singh', '$2y$10$vryPPpyA4VjrbVkGs1HZ2O9kaI9OiOLI72d4krt7gP3dy/dkBFqmO', 'lalitsingh@gmail.com', 20, 1512.00, 0.00, 'Basic', '2026-02-24 16:03:54', NULL, 'user', 'active', 'NP#6141726'),
(23, 'llalit', '$2y$10$v5NahEbx5trgMF.U6C8Th.y0kgllw3DI8SEtrLlwuLLeDPzQJoKnq', 'llalit@gmail.com', 20, 500.00, 0.00, 'Basic', '2026-02-24 19:05:57', '2026-02-24 20:19:23', 'user', 'active', 'NP#3387526'),
(24, 'muno', '$2y$10$vhYhtCNbmzE3woslREVAr.dnQcaghxC.ezy.LVleaPHNNV6yYDBUq', 'muno@gmail.com', 21, -11700.00, 30000.00, 'Diamond', '2026-02-24 19:12:32', '2026-02-25 09:29:32', 'user', 'active', 'NP#4830826'),
(25, 'timani', '$2y$10$UNoUoVbXh2ptcL4L91GwSOy4fsxk/f008w17zaM4gic9cMPqpmqDy', 'timanisuneel@gmail.com', 20, 0.00, 0.00, 'Basic', '2026-02-24 20:01:06', NULL, 'user', 'active', 'NP#1340926'),
(27, 'Suneel Timani', '$2y$10$uQpgDGeT56K8T5TwD3rDjuQVE6ogPEtc6MIbCmZRK/MBq/.OtciHG', 'ahen@gmail.com', NULL, 0.00, 0.00, 'Basic', '2026-02-25 08:18:22', NULL, 'user', 'active', 'NP#2952626'),
(28, 'Dayal', '$2y$10$puAG3tjITGWD6p1/AlAR4eeL8jTSeAKuOFb358ibl3cIaiUXrKxuG', 'dayal@gmail.com', 20, 120.00, 0.00, 'Basic', '2026-02-25 09:39:44', NULL, 'user', 'active', 'NP#9980726');

-- --------------------------------------------------------

--
-- Table structure for table `user_crypto_addresses`
--

CREATE TABLE `user_crypto_addresses` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `network` enum('TRC20','BEP20','SOLANA') NOT NULL,
  `address` varchar(255) NOT NULL,
  `status` enum('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `verified_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_crypto_addresses`
--

INSERT INTO `user_crypto_addresses` (`id`, `user_id`, `network`, `address`, `status`, `created_at`, `verified_at`) VALUES
(1, 16, 'TRC20', 'TXYZ1234567890abcdefghijklmnopqrstuvwxy', 'verified', '2026-02-25 09:20:15', '2026-02-25 09:21:00'),
(3, 20, 'BEP20', '0x1234567890abcdef1234567890abcdef12345678', 'verified', '2026-02-25 09:24:55', '2026-02-25 09:25:38'),
(4, 23, 'SOLANA', 'HN7cABqLq46Es1jh92dQQisAq662SmxELLLsHHe4YWrH', 'verified', '2026-02-25 09:26:45', '2026-02-25 09:26:51'),
(5, 24, 'TRC20', 'TXYZ1234567890abcdefghijklmnopqrstuvwxz', 'verified', '2026-02-25 09:29:11', '2026-02-25 09:29:20');

-- --------------------------------------------------------

--
-- Table structure for table `withdrawals`
--

CREATE TABLE `withdrawals` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `method` varchar(50) NOT NULL,
  `details` text NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `withdrawals`
--

INSERT INTO `withdrawals` (`id`, `user_id`, `amount`, `method`, `details`, `status`, `created_at`) VALUES
(1, 17, 120.00, 'USDT', 'other', 'pending', '2026-02-24 11:11:42'),
(2, 23, 200.00, 'Wallet', 'Withdrawal request from dashboard', 'rejected', '2026-02-24 20:19:23'),
(3, 20, 200.00, 'Wallet', 'Withdrawal request from dashboard', 'approved', '2026-02-24 20:24:49'),
(4, 24, 200.00, 'TRC20', 'TXYZ1234567890abcdefghijklmnopqrstuvwxz', 'approved', '2026-02-25 09:29:32');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `investment_requests`
--
ALTER TABLE `investment_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_status` (`user_id`,`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `milestone_rewards`
--
ALTER TABLE `milestone_rewards`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_user_milestone` (`user_id`,`milestone_code`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `transaction_logs`
--
ALTER TABLE `transaction_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `user_code` (`user_code`),
  ADD KEY `referrer_id` (`referrer_id`);

--
-- Indexes for table `user_crypto_addresses`
--
ALTER TABLE `user_crypto_addresses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_user_network` (`user_id`,`network`),
  ADD UNIQUE KEY `uniq_network_address` (`network`,`address`),
  ADD KEY `idx_status_created` (`status`,`created_at`);

--
-- Indexes for table `withdrawals`
--
ALTER TABLE `withdrawals`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `investment_requests`
--
ALTER TABLE `investment_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `milestone_rewards`
--
ALTER TABLE `milestone_rewards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `transaction_logs`
--
ALTER TABLE `transaction_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `user_crypto_addresses`
--
ALTER TABLE `user_crypto_addresses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `withdrawals`
--
ALTER TABLE `withdrawals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`referrer_id`) REFERENCES `users` (`id`);

--
-- Table structure for table `login_attempts`
--
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `ip_address` varchar(64) NOT NULL,
  `is_success` tinyint(1) NOT NULL DEFAULT 0,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_email_time` (`email`,`attempted_at`),
  KEY `idx_ip_time` (`ip_address`,`attempted_at`),
  KEY `idx_success_time` (`is_success`,`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `wallet_ledger`
--
CREATE TABLE IF NOT EXISTS `wallet_ledger` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `delta_amount` decimal(15,2) NOT NULL,
  `balance_before` decimal(15,2) NOT NULL,
  `balance_after` decimal(15,2) NOT NULL,
  `entry_type` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` bigint(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_created` (`user_id`,`created_at`),
  KEY `idx_entry_type` (`entry_type`),
  CONSTRAINT `wallet_ledger_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
