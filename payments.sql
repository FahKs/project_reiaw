-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 17, 2024 at 04:44 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dbcon`
--

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `slip_image` blob NOT NULL,
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `payment_method` enum('credit_card','promtpay') NOT NULL,
  `amount` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `order_id`, `slip_image`, `payment_date`, `payment_method`, `amount`) VALUES
(35, 57, '', '2024-10-11 18:43:39', '', 500.00),
(36, 57, '', '2024-10-11 18:43:40', '', 500.00),
(37, 57, '', '2024-10-11 18:43:58', '', 500.00),
(38, 58, '', '2024-10-11 21:17:58', '', 500.00),
(39, 59, '', '2024-10-12 05:47:32', '', 500.00),
(40, 60, '', '2024-10-12 06:13:34', '', 500.00),
(41, 60, '', '2024-10-12 06:13:51', '', 500.00),
(42, 61, '', '2024-10-12 07:13:01', '', 3000.00),
(43, 61, '', '2024-10-12 07:19:03', '', 3000.00),
(44, 62, '', '2024-10-13 09:18:53', '', 950.00),
(45, 62, '', '2024-10-13 09:18:54', '', 950.00),
(46, 62, '', '2024-10-13 09:19:39', '', 950.00),
(47, 62, '', '2024-10-13 09:20:44', '', 950.00),
(48, 62, '', '2024-10-13 09:21:23', '', 950.00),
(49, 62, '', '2024-10-13 09:21:42', '', 950.00),
(50, 63, '', '2024-10-15 17:56:18', '', 500.00),
(51, 63, '', '2024-10-15 17:56:19', '', 500.00),
(52, 68, '', '2024-10-16 16:31:05', '', 500.00),
(53, 81, '', '2024-10-17 08:22:25', '', 10.00),
(54, 81, '', '2024-10-17 08:22:49', '', 10.00),
(55, 81, '', '2024-10-17 08:23:32', '', 10.00),
(56, 83, '', '2024-10-17 11:59:07', '', 10.00),
(57, 83, '', '2024-10-17 13:01:52', '', 10.00),
(58, 84, '', '2024-10-17 13:03:38', '', 10.00),
(59, 86, '', '2024-10-17 13:28:41', '', 10.00);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `order_id` (`order_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
