-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 24, 2026 at 06:36 PM
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
('last_cron_run', '2026-01-01'),
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
(2, 20, 10.00, 'referral_bonus', 1, 'Level 1 bonus from User ID 21', '2026-02-24 16:03:54');

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
(8, 16, 21, 'add', 12.00, 'Manual Adjustment', '2026-02-24 16:07:41');

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
  `status` enum('active','banned') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `referrer_id`, `wallet_balance`, `investment_amount`, `user_rank`, `created_at`, `last_withdrawal_date`, `role`, `status`) VALUES
(8, 'admin', '$2y$10$ei9j7VKDLeyUM.DP.PAvZO1TiZIpqxhC18xt7//QGXe81ZoeGiB8i', 'hirani03332510535@gmail.com', NULL, 41.00, 2000.00, 'Basic', '2026-02-23 17:04:20', '2026-02-23 19:34:42', 'user', 'active'),
(10, 'sahil', '$2y$10$vxvcbzXfkSocNZyT5AtuieZlcUyIu5.naEQQuCDnnQ2M2xb0/NfAO', 'sahilkkk@gmail.com', 8, 0.00, 0.00, 'Basic', '2026-02-23 17:16:56', NULL, 'user', 'active'),
(11, 'lalit', '$2y$10$ISamoXZwlvPvCUehX1Z.i.77B1TnvjYtZHHQ86/HdHbgCPPNIqO76', 'lalit@gmail.com', 8, 0.00, 0.00, 'Basic', '2026-02-23 17:38:13', NULL, 'user', 'active'),
(12, 'suneel ', '$2y$10$Phy/gIabD9txtotfTleMWeJBAJDOjrBA/gNIM0k9G.dT2fUQ9GV8i', 'suneel@gmail.com', NULL, 0.00, 0.00, 'Basic', '2026-02-23 19:19:47', NULL, 'user', 'banned'),
(13, 'lakkhan', '$2y$10$Q4N9JqMC7oiGqRPZFz.7.ewazRLm99wjmICp4PE.xvaTVvKlNez.2', 'lakhan@gmail.com', 8, 0.00, 0.00, 'Basic', '2026-02-23 19:22:52', NULL, 'user', 'active'),
(14, 'JohnDoe', '$2y$10$8K.uS6Y1A2MvBvI1.k.K.u8R6y.k7L8m9n0p1q2r3s4t5u6v7w8x9y', 'user@test.com', NULL, 0.00, 0.00, 'Basic', '2026-02-23 19:54:44', NULL, 'user', 'active'),
(16, 'SuperAdmin', '$2y$10$f01.Bl7dW/QIMyiUJdWuMOzSu1P/YR00HOs266fbrdWKjb5ruPTLu', 'admin@test.com', NULL, 11.00, 0.00, 'Basic', '2026-02-23 20:25:17', NULL, 'admin', 'active'),
(17, 'kartik', '$2y$10$qcLCWupr2uENJqJwHpLPi.AyReGkIAzugbITRKeG9AmpaCJUirGAq', 'kumar@gmail.com', NULL, 100.00, 0.00, 'Basic', '2026-02-24 11:08:26', NULL, 'user', 'active'),
(20, 'Suneel Timani', '$2y$10$8maaHiFLVC7IYd4s6pdDO.cmJKFb1P2ex42BFVKHhztxZU5Es5SF6', 'suneeltimani@gmail.com', NULL, 10.00, 0.00, 'Basic', '2026-02-24 16:00:03', NULL, 'user', 'active'),
(21, 'Lalit Singh', '$2y$10$vryPPpyA4VjrbVkGs1HZ2O9kaI9OiOLI72d4krt7gP3dy/dkBFqmO', 'lalitsingh@gmail.com', 20, 12.00, 0.00, 'Basic', '2026-02-24 16:03:54', NULL, 'user', 'active');

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
(1, 17, 120.00, 'USDT', 'other', 'pending', '2026-02-24 11:11:42');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `milestone_rewards`
--
ALTER TABLE `milestone_rewards`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_user_milestone` (`user_id`,`milestone_code`);

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
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `referrer_id` (`referrer_id`);

--
-- Indexes for table `withdrawals`
--
ALTER TABLE `withdrawals`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `milestone_rewards`
--
ALTER TABLE `milestone_rewards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transaction_logs`
--
ALTER TABLE `transaction_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `withdrawals`
--
ALTER TABLE `withdrawals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `milestone_rewards`
--
ALTER TABLE `milestone_rewards`
  ADD CONSTRAINT `milestone_rewards_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`referrer_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
