-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 15, 2025 at 05:41 AM
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
-- Database: `tiffinly`
--

-- --------------------------------------------------------

--
-- Table structure for table `addresses`
--

CREATE TABLE `addresses` (
  `address_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `address_type` enum('home','work') NOT NULL,
  `line1` varchar(255) NOT NULL,
  `line2` varchar(255) DEFAULT NULL,
  `city` varchar(100) NOT NULL,
  `state` varchar(100) NOT NULL,
  `pincode` varchar(10) NOT NULL,
  `landmark` varchar(255) DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `addresses`
--

INSERT INTO `addresses` (`address_id`, `user_id`, `address_type`, `line1`, `line2`, `city`, `state`, `pincode`, `landmark`, `is_default`) VALUES
(6, 9, 'home', 'Mavelil House', 'Kidangoor South P.O.', 'Kottayam', 'Kerala', '686583', '', 1),
(7, 9, 'work', 'Madonna Hostel', 'Marian college', 'Kuttikkanam', 'Kerala', '686531', '', 1),
(8, 21, 'home', 'Atremis arcade', 'Kurupanthara', 'Ettumanoor', 'Kerala', '687543', '', 1),
(9, 21, 'work', 'Cognizant', 'Infopark', 'Kakkanad', 'Kerala', '689076', '', 1),
(10, 22, 'home', 'Kuku\'s Nest', 'Kalamassery', 'Ernakulam', 'Kerala', '687724', '', 1),
(11, 23, 'home', 'Thamarasseriyil', 'Thalayolaparamb', 'Ettumanoor', 'Kerala', '686521', '', 1);

-- --------------------------------------------------------

--
-- Table structure for table `auth_tokens`
--

CREATE TABLE `auth_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `selector` varchar(32) NOT NULL,
  `validator` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `deliveries`
--

CREATE TABLE `deliveries` (
  `delivery_id` int(11) NOT NULL,
  `subscription_id` int(11) NOT NULL,
  `delivery_date` date NOT NULL,
  `status` enum('scheduled','out_for_delivery','delivered','cancelled') NOT NULL DEFAULT 'scheduled',
  `payment_status` enum('unpaid','paid') NOT NULL DEFAULT 'unpaid',
  `delivery_time` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `deliveries`
--

INSERT INTO `deliveries` (`delivery_id`, `subscription_id`, `delivery_date`, `status`, `payment_status`, `delivery_time`) VALUES
(314, 64, '2025-09-01', 'scheduled', 'paid', '08:00:00'),
(315, 64, '2025-09-02', 'scheduled', 'paid', '08:00:00'),
(316, 64, '2025-09-03', 'scheduled', 'paid', '08:00:00'),
(317, 64, '2025-09-04', 'scheduled', 'paid', '08:00:00'),
(318, 64, '2025-09-05', 'scheduled', 'paid', '08:00:00'),
(319, 64, '2025-09-08', 'scheduled', 'paid', '08:00:00'),
(320, 64, '2025-09-09', 'scheduled', 'paid', '08:00:00'),
(321, 64, '2025-09-10', 'scheduled', 'paid', '08:00:00'),
(322, 64, '2025-09-11', 'scheduled', 'paid', '08:00:00'),
(323, 64, '2025-09-12', 'scheduled', 'paid', '08:00:00'),
(324, 64, '2025-09-15', 'scheduled', 'unpaid', '08:00:00'),
(325, 64, '2025-09-16', 'scheduled', 'unpaid', '08:00:00'),
(326, 64, '2025-09-17', 'scheduled', 'unpaid', '08:00:00'),
(327, 64, '2025-09-18', 'scheduled', 'unpaid', '08:00:00'),
(328, 64, '2025-09-19', 'scheduled', 'unpaid', '08:00:00'),
(329, 64, '2025-09-22', 'scheduled', 'unpaid', '08:00:00'),
(330, 64, '2025-09-23', 'scheduled', 'unpaid', '08:00:00'),
(331, 64, '2025-09-24', 'scheduled', 'unpaid', '08:00:00'),
(332, 64, '2025-09-25', 'scheduled', 'unpaid', '08:00:00'),
(333, 64, '2025-09-26', 'scheduled', 'unpaid', '08:00:00'),
(345, 66, '2025-09-03', 'scheduled', 'paid', '08:00:00'),
(346, 66, '2025-09-04', 'scheduled', 'paid', '08:00:00'),
(347, 66, '2025-09-05', 'scheduled', 'paid', '08:00:00'),
(348, 66, '2025-09-08', 'scheduled', 'paid', '08:00:00'),
(349, 66, '2025-09-09', 'scheduled', 'paid', '08:00:00'),
(350, 66, '2025-09-10', 'scheduled', 'paid', '08:00:00'),
(351, 66, '2025-09-11', 'scheduled', 'paid', '08:00:00'),
(352, 66, '2025-09-12', 'scheduled', 'paid', '08:00:00'),
(353, 66, '2025-09-15', 'scheduled', 'paid', '08:00:00'),
(354, 66, '2025-09-16', 'scheduled', 'unpaid', '08:00:00'),
(376, 69, '2025-09-04', 'scheduled', 'paid', '08:00:00'),
(377, 69, '2025-09-05', 'scheduled', 'paid', '08:00:00'),
(378, 69, '2025-09-06', 'scheduled', 'paid', '08:00:00'),
(379, 69, '2025-09-08', 'scheduled', 'paid', '08:00:00'),
(380, 69, '2025-09-09', 'scheduled', 'paid', '08:00:00'),
(381, 69, '2025-09-10', 'scheduled', 'paid', '08:00:00'),
(382, 69, '2025-09-11', 'scheduled', 'paid', '08:00:00'),
(383, 83, '2025-09-15', 'scheduled', 'unpaid', '08:00:00'),
(384, 83, '2025-09-16', 'scheduled', 'unpaid', '08:00:00'),
(385, 83, '2025-09-17', 'scheduled', 'unpaid', '08:00:00'),
(386, 83, '2025-09-18', 'scheduled', 'unpaid', '08:00:00'),
(387, 83, '2025-09-19', 'scheduled', 'unpaid', '08:00:00'),
(388, 83, '2025-09-22', 'scheduled', 'unpaid', '08:00:00'),
(389, 83, '2025-09-23', 'scheduled', 'unpaid', '08:00:00'),
(390, 83, '2025-09-24', 'scheduled', 'unpaid', '08:00:00'),
(391, 83, '2025-09-25', 'scheduled', 'unpaid', '08:00:00'),
(392, 83, '2025-09-26', 'scheduled', 'unpaid', '08:00:00'),
(393, 84, '2025-09-15', 'scheduled', 'unpaid', '08:00:00'),
(394, 84, '2025-09-16', 'scheduled', 'unpaid', '08:00:00'),
(395, 84, '2025-09-17', 'scheduled', 'unpaid', '08:00:00'),
(396, 84, '2025-09-18', 'scheduled', 'unpaid', '08:00:00'),
(397, 84, '2025-09-19', 'scheduled', 'unpaid', '08:00:00'),
(398, 84, '2025-09-22', 'scheduled', 'unpaid', '08:00:00'),
(399, 84, '2025-09-23', 'scheduled', 'unpaid', '08:00:00'),
(400, 84, '2025-09-24', 'scheduled', 'unpaid', '08:00:00'),
(401, 84, '2025-09-25', 'scheduled', 'unpaid', '08:00:00'),
(402, 84, '2025-09-26', 'scheduled', 'unpaid', '08:00:00'),
(403, 86, '2025-09-11', 'scheduled', 'unpaid', NULL),
(404, 86, '2025-09-12', 'scheduled', 'unpaid', NULL),
(405, 86, '2025-09-15', 'scheduled', 'unpaid', NULL),
(406, 86, '2025-09-16', 'scheduled', 'unpaid', NULL),
(407, 86, '2025-09-17', 'scheduled', 'unpaid', NULL),
(408, 86, '2025-09-18', 'scheduled', 'unpaid', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `delivery_assignments`
--

CREATE TABLE `delivery_assignments` (
  `assignment_id` int(11) NOT NULL,
  `subscription_id` int(11) NOT NULL,
  `delivery_date` date NOT NULL,
  `partner_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','out_for_delivery','delivered','cancelled') NOT NULL DEFAULT 'pending',
  `meal_type` varchar(20) NOT NULL,
  `meal_id` int(11) DEFAULT NULL,
  `payment_id` int(11) DEFAULT NULL,
  `payment_status` enum('unpaid','paid') NOT NULL DEFAULT 'unpaid'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `delivery_assignments`
--

INSERT INTO `delivery_assignments` (`assignment_id`, `subscription_id`, `delivery_date`, `partner_id`, `assigned_at`, `status`, `meal_type`, `meal_id`, `payment_id`, `payment_status`) VALUES
(19, 64, '2025-09-01', 16, '2025-09-01 06:50:23', 'delivered', 'breakfast', 1, 14, 'paid'),
(20, 64, '2025-09-01', 16, '2025-09-01 06:50:23', 'delivered', 'lunch', 114, 14, 'paid'),
(21, 64, '2025-09-01', 16, '2025-09-01 06:50:23', 'delivered', 'dinner', 3, 14, 'paid'),
(22, 64, '2025-09-02', 16, '2025-09-02 03:13:26', 'delivered', 'breakfast', 5, 17, 'paid'),
(23, 64, '2025-09-02', 16, '2025-09-02 03:13:26', 'delivered', 'lunch', 6, 17, 'paid'),
(24, 64, '2025-09-02', 16, '2025-09-02 03:13:26', 'delivered', 'dinner', 7, 17, 'paid'),
(25, 64, '2025-09-03', 16, '2025-09-02 03:13:26', 'delivered', 'breakfast', 9, 18, 'paid'),
(26, 64, '2025-09-03', 16, '2025-09-02 03:13:26', 'delivered', 'lunch', 10, 18, 'paid'),
(27, 64, '2025-09-03', 16, '2025-09-02 03:13:26', 'cancelled', 'dinner', 11, NULL, 'unpaid'),
(28, 64, '2025-09-04', 16, '2025-09-02 03:13:26', 'delivered', 'breakfast', 13, 19, 'paid'),
(29, 64, '2025-09-04', 16, '2025-09-02 03:13:26', 'delivered', 'lunch', 14, 19, 'paid'),
(30, 64, '2025-09-04', 16, '2025-09-02 03:13:26', 'delivered', 'dinner', 15, 19, 'paid'),
(31, 64, '2025-09-05', 16, '2025-09-02 03:13:26', 'delivered', 'breakfast', 17, 22, 'paid'),
(32, 64, '2025-09-05', 16, '2025-09-02 03:13:26', 'delivered', 'lunch', 18, 22, 'paid'),
(33, 64, '2025-09-05', 16, '2025-09-02 03:13:26', 'delivered', 'dinner', 20, 22, 'paid'),
(34, 64, '2025-09-08', 16, '2025-09-02 03:13:26', 'delivered', 'breakfast', 1, 26, 'paid'),
(35, 64, '2025-09-08', 16, '2025-09-02 03:13:26', 'delivered', 'lunch', 114, 26, 'paid'),
(36, 64, '2025-09-08', 16, '2025-09-02 03:13:26', 'delivered', 'dinner', 3, 26, 'paid'),
(37, 64, '2025-09-09', 16, '2025-09-02 03:13:26', 'delivered', 'breakfast', 5, 32, 'paid'),
(38, 64, '2025-09-09', 16, '2025-09-02 03:13:26', 'delivered', 'lunch', 6, 32, 'paid'),
(39, 64, '2025-09-09', 16, '2025-09-02 03:13:26', 'delivered', 'dinner', 7, 32, 'paid'),
(40, 64, '2025-09-10', 16, '2025-09-02 03:13:26', 'delivered', 'breakfast', 9, 35, 'paid'),
(41, 64, '2025-09-10', 16, '2025-09-02 03:13:26', 'delivered', 'lunch', 10, 35, 'paid'),
(42, 64, '2025-09-10', 16, '2025-09-02 03:13:26', 'delivered', 'dinner', 11, 35, 'paid'),
(43, 64, '2025-09-11', 16, '2025-09-02 03:13:26', 'delivered', 'breakfast', 13, 39, 'paid'),
(44, 64, '2025-09-11', 16, '2025-09-02 03:13:26', 'delivered', 'lunch', 14, 39, 'paid'),
(45, 64, '2025-09-11', 16, '2025-09-02 03:13:26', 'delivered', 'dinner', 15, 39, 'paid'),
(46, 64, '2025-09-12', 16, '2025-09-02 03:13:26', 'delivered', 'breakfast', 17, 36, 'paid'),
(47, 64, '2025-09-12', 16, '2025-09-02 03:13:26', 'delivered', 'lunch', 18, 36, 'paid'),
(48, 64, '2025-09-12', 16, '2025-09-02 03:13:26', 'delivered', 'dinner', 20, 36, 'paid'),
(49, 64, '2025-09-15', 16, '2025-09-02 03:13:26', 'delivered', 'breakfast', 1, NULL, 'unpaid'),
(50, 64, '2025-09-15', 16, '2025-09-02 03:13:26', 'pending', 'lunch', 114, NULL, 'unpaid'),
(51, 64, '2025-09-15', 16, '2025-09-02 03:13:26', 'pending', 'dinner', 3, NULL, 'unpaid'),
(52, 64, '2025-09-16', 16, '2025-09-02 03:13:26', 'pending', 'breakfast', 5, NULL, 'unpaid'),
(53, 64, '2025-09-16', 16, '2025-09-02 03:13:26', 'pending', 'lunch', 6, NULL, 'unpaid'),
(54, 64, '2025-09-16', 16, '2025-09-02 03:13:26', 'pending', 'dinner', 7, NULL, 'unpaid'),
(55, 64, '2025-09-17', 16, '2025-09-02 03:13:26', 'pending', 'breakfast', 9, NULL, 'unpaid'),
(56, 64, '2025-09-17', 16, '2025-09-02 03:13:26', 'pending', 'lunch', 10, NULL, 'unpaid'),
(57, 64, '2025-09-17', 16, '2025-09-02 03:13:26', 'pending', 'dinner', 11, NULL, 'unpaid'),
(58, 64, '2025-09-18', 16, '2025-09-02 03:13:26', 'pending', 'breakfast', 13, NULL, 'unpaid'),
(59, 64, '2025-09-18', 16, '2025-09-02 03:13:26', 'pending', 'lunch', 14, NULL, 'unpaid'),
(60, 64, '2025-09-18', 16, '2025-09-02 03:13:26', 'pending', 'dinner', 15, NULL, 'unpaid'),
(61, 64, '2025-09-19', 16, '2025-09-02 03:13:26', 'pending', 'breakfast', 17, NULL, 'unpaid'),
(62, 64, '2025-09-19', 16, '2025-09-02 03:13:26', 'pending', 'lunch', 18, NULL, 'unpaid'),
(63, 64, '2025-09-19', 16, '2025-09-02 03:13:26', 'pending', 'dinner', 20, NULL, 'unpaid'),
(64, 64, '2025-09-22', 16, '2025-09-02 03:13:26', 'pending', 'breakfast', 1, NULL, 'unpaid'),
(65, 64, '2025-09-22', 16, '2025-09-02 03:13:26', 'pending', 'lunch', 114, NULL, 'unpaid'),
(66, 64, '2025-09-22', 16, '2025-09-02 03:13:26', 'pending', 'dinner', 3, NULL, 'unpaid'),
(67, 64, '2025-09-23', 16, '2025-09-02 03:13:26', 'pending', 'breakfast', 5, NULL, 'unpaid'),
(68, 64, '2025-09-23', 16, '2025-09-02 03:13:26', 'pending', 'lunch', 6, NULL, 'unpaid'),
(69, 64, '2025-09-23', 16, '2025-09-02 03:13:26', 'pending', 'dinner', 7, NULL, 'unpaid'),
(70, 64, '2025-09-24', 16, '2025-09-02 03:13:26', 'pending', 'breakfast', 9, NULL, 'unpaid'),
(71, 64, '2025-09-24', 16, '2025-09-02 03:13:26', 'pending', 'lunch', 10, NULL, 'unpaid'),
(72, 64, '2025-09-24', 16, '2025-09-02 03:13:26', 'pending', 'dinner', 11, NULL, 'unpaid'),
(73, 64, '2025-09-25', 16, '2025-09-02 03:13:26', 'pending', 'breakfast', 13, NULL, 'unpaid'),
(74, 64, '2025-09-25', 16, '2025-09-02 03:13:26', 'pending', 'lunch', 14, NULL, 'unpaid'),
(75, 64, '2025-09-25', 16, '2025-09-02 03:13:26', 'pending', 'dinner', 15, NULL, 'unpaid'),
(76, 64, '2025-09-26', 16, '2025-09-02 03:18:28', 'pending', 'breakfast', 17, NULL, 'unpaid'),
(77, 64, '2025-09-26', 16, '2025-09-02 03:18:28', 'pending', 'lunch', 18, NULL, 'unpaid'),
(78, 64, '2025-09-26', 16, '2025-09-02 03:18:28', 'pending', 'dinner', 20, NULL, 'unpaid'),
(82, 66, '2025-09-03', 16, '2025-09-02 07:59:49', 'delivered', 'breakfast', 9, 16, 'paid'),
(83, 66, '2025-09-03', 16, '2025-09-02 07:59:49', 'delivered', 'lunch', 10, 16, 'paid'),
(84, 66, '2025-09-03', 16, '2025-09-02 07:59:49', 'delivered', 'dinner', 11, 16, 'paid'),
(85, 66, '2025-09-04', 16, '2025-09-02 07:59:49', 'delivered', 'breakfast', 13, 20, 'paid'),
(86, 66, '2025-09-04', 16, '2025-09-02 07:59:49', 'delivered', 'lunch', 14, 20, 'paid'),
(87, 66, '2025-09-04', 16, '2025-09-02 07:59:49', 'delivered', 'dinner', 15, 20, 'paid'),
(88, 66, '2025-09-05', 16, '2025-09-02 07:59:49', 'delivered', 'breakfast', 17, 23, 'paid'),
(89, 66, '2025-09-05', 16, '2025-09-02 07:59:49', 'delivered', 'lunch', 18, 23, 'paid'),
(90, 66, '2025-09-05', 16, '2025-09-02 07:59:49', 'delivered', 'dinner', 20, 23, 'paid'),
(91, 66, '2025-09-08', 16, '2025-09-02 07:59:49', 'delivered', 'breakfast', 1, 27, 'paid'),
(92, 66, '2025-09-08', 16, '2025-09-02 07:59:49', 'delivered', 'lunch', 114, 27, 'paid'),
(93, 66, '2025-09-08', 16, '2025-09-02 07:59:49', 'delivered', 'dinner', 3, 27, 'paid'),
(94, 66, '2025-09-09', 16, '2025-09-02 07:59:49', 'delivered', 'breakfast', 5, 30, 'paid'),
(95, 66, '2025-09-09', 16, '2025-09-02 07:59:49', 'delivered', 'lunch', 6, 30, 'paid'),
(96, 66, '2025-09-09', 16, '2025-09-02 07:59:49', 'delivered', 'dinner', 7, 30, 'paid'),
(97, 66, '2025-09-10', 16, '2025-09-02 07:59:49', 'delivered', 'breakfast', 9, 34, 'paid'),
(98, 66, '2025-09-10', 16, '2025-09-02 07:59:49', 'delivered', 'lunch', 10, 34, 'paid'),
(99, 66, '2025-09-10', 16, '2025-09-02 07:59:49', 'delivered', 'dinner', 11, 34, 'paid'),
(100, 66, '2025-09-11', 16, '2025-09-02 07:59:49', 'delivered', 'breakfast', 13, 38, 'paid'),
(101, 66, '2025-09-11', 16, '2025-09-02 07:59:49', 'delivered', 'lunch', 14, 38, 'paid'),
(102, 66, '2025-09-11', 16, '2025-09-02 07:59:49', 'delivered', 'dinner', 15, 38, 'paid'),
(103, 66, '2025-09-12', 16, '2025-09-02 07:59:49', 'delivered', 'breakfast', 17, 40, 'paid'),
(104, 66, '2025-09-12', 16, '2025-09-02 07:59:49', 'delivered', 'lunch', 18, 40, 'paid'),
(105, 66, '2025-09-12', 16, '2025-09-02 07:59:49', 'delivered', 'dinner', 20, 40, 'paid'),
(106, 66, '2025-09-15', 16, '2025-09-02 07:59:49', 'delivered', 'breakfast', 1, 31, 'paid'),
(107, 66, '2025-09-15', 16, '2025-09-02 07:59:49', 'pending', 'lunch', 114, NULL, 'unpaid'),
(108, 66, '2025-09-15', 16, '2025-09-02 07:59:49', 'pending', 'dinner', 3, NULL, 'unpaid'),
(109, 66, '2025-09-16', 16, '2025-09-02 07:59:49', 'pending', 'breakfast', 5, NULL, 'unpaid'),
(110, 66, '2025-09-16', 16, '2025-09-02 07:59:49', 'pending', 'lunch', 6, NULL, 'unpaid'),
(111, 66, '2025-09-16', 16, '2025-09-02 07:59:49', 'pending', 'dinner', 7, NULL, 'unpaid'),
(112, 69, '2025-09-04', 19, '2025-09-04 02:40:04', 'delivered', 'Breakfast', 67, 21, 'paid'),
(113, 69, '2025-09-04', 19, '2025-09-04 02:40:04', 'delivered', 'Lunch', 71, 21, 'paid'),
(114, 69, '2025-09-04', 19, '2025-09-04 02:40:04', 'delivered', 'Dinner', 75, 21, 'paid'),
(115, 69, '2025-09-05', 19, '2025-09-04 02:40:04', 'delivered', 'Breakfast', 79, 24, 'paid'),
(116, 69, '2025-09-05', 19, '2025-09-04 02:40:04', 'delivered', 'Lunch', 83, 24, 'paid'),
(117, 69, '2025-09-05', 19, '2025-09-04 02:40:04', 'delivered', 'Dinner', 87, 24, 'paid'),
(118, 69, '2025-09-06', 19, '2025-09-04 02:40:04', 'delivered', 'Breakfast', 91, 25, 'paid'),
(119, 69, '2025-09-06', 19, '2025-09-04 02:40:04', 'delivered', 'Lunch', 95, 25, 'paid'),
(120, 69, '2025-09-06', 19, '2025-09-04 02:40:04', 'delivered', 'Dinner', 99, 25, 'paid'),
(121, 69, '2025-09-08', 19, '2025-09-04 02:40:04', 'delivered', 'Breakfast', 31, 28, 'paid'),
(122, 69, '2025-09-08', 19, '2025-09-04 02:40:05', 'delivered', 'Lunch', 35, 28, 'paid'),
(123, 69, '2025-09-08', 19, '2025-09-04 02:40:05', 'delivered', 'Dinner', 39, 28, 'paid'),
(124, 69, '2025-09-09', 19, '2025-09-04 02:40:05', 'delivered', 'Breakfast', 43, 29, 'paid'),
(125, 69, '2025-09-09', 19, '2025-09-04 02:40:05', 'delivered', 'Lunch', 47, 29, 'paid'),
(126, 69, '2025-09-09', 19, '2025-09-04 02:40:05', 'delivered', 'Dinner', 51, 29, 'paid'),
(127, 69, '2025-09-10', 19, '2025-09-04 02:40:05', 'delivered', 'Breakfast', 55, 33, 'paid'),
(128, 69, '2025-09-10', 19, '2025-09-04 02:40:05', 'delivered', 'Lunch', 59, 33, 'paid'),
(129, 69, '2025-09-10', 19, '2025-09-04 02:40:05', 'delivered', 'Dinner', 63, 33, 'paid'),
(130, 69, '2025-09-11', 19, '2025-09-04 02:40:05', 'delivered', 'Breakfast', 67, 37, 'paid'),
(131, 69, '2025-09-11', 19, '2025-09-04 02:40:05', 'delivered', 'Lunch', 71, 37, 'paid'),
(132, 69, '2025-09-11', 19, '2025-09-04 02:40:05', 'delivered', 'Dinner', 75, 37, 'paid');

-- --------------------------------------------------------

--
-- Table structure for table `delivery_issues`
--

CREATE TABLE `delivery_issues` (
  `issue_id` int(11) NOT NULL,
  `assignment_id` int(11) NOT NULL,
  `subscription_id` int(11) NOT NULL,
  `partner_id` int(11) NOT NULL,
  `meal_type` varchar(50) DEFAULT NULL,
  `issue_type` varchar(100) DEFAULT NULL,
  `status` enum('open','resolved') NOT NULL DEFAULT 'open',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `delivery_issues`
--

INSERT INTO `delivery_issues` (`issue_id`, `assignment_id`, `subscription_id`, `partner_id`, `meal_type`, `issue_type`, `status`, `description`, `created_at`) VALUES
(1, 27, 64, 16, 'dinner', 'Customer not available', 'resolved', 'Customer not available at location', '2025-09-03 08:59:30');

-- --------------------------------------------------------

--
-- Table structure for table `delivery_partner_details`
--

CREATE TABLE `delivery_partner_details` (
  `partner_id` int(11) NOT NULL,
  `vehicle_type` varchar(50) NOT NULL,
  `vehicle_number` varchar(20) NOT NULL,
  `license_number` varchar(30) NOT NULL,
  `license_file` varchar(255) DEFAULT NULL,
  `aadhar_number` varchar(20) DEFAULT NULL,
  `availability` enum('Part-time','Full-time') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `delivery_partner_details`
--

INSERT INTO `delivery_partner_details` (`partner_id`, `vehicle_type`, `vehicle_number`, `license_number`, `license_file`, `aadhar_number`, `availability`) VALUES
(16, 'Car', 'KL08U9903', 'KL9065783212', 'assets/delivery/6898619015ec1_electronic-fingerprinting-form-cna.pdf', '456789034566', 'Full-time'),
(19, 'scooter', 'KL09h8754', 'KL8736526738', 'assets/delivery/68ab1d800deb4_download.jpg', '123477659800', 'Part-time');

-- --------------------------------------------------------

--
-- Table structure for table `delivery_preferences`
--

CREATE TABLE `delivery_preferences` (
  `preference_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `meal_type` enum('breakfast','lunch','dinner') NOT NULL,
  `address_id` int(11) NOT NULL,
  `time_slot` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `delivery_preferences`
--

INSERT INTO `delivery_preferences` (`preference_id`, `user_id`, `meal_type`, `address_id`, `time_slot`, `created_at`, `updated_at`) VALUES
(61, 9, 'breakfast', 6, '09:00 - 10:00', '2025-09-01 06:47:52', '2025-09-01 06:47:52'),
(62, 9, 'lunch', 7, '13:00 - 14:00', '2025-09-01 06:47:52', '2025-09-01 06:47:52'),
(63, 9, 'dinner', 6, '20:00 - 21:00', '2025-09-01 06:47:52', '2025-09-01 06:47:52'),
(67, 21, 'breakfast', 8, '07:00 - 08:00', '2025-09-02 07:59:07', '2025-09-02 07:59:07'),
(68, 21, 'lunch', 9, '13:00 - 14:00', '2025-09-02 07:59:07', '2025-09-02 07:59:07'),
(69, 21, 'dinner', 8, '20:00 - 21:00', '2025-09-02 07:59:07', '2025-09-02 07:59:07'),
(76, 22, 'breakfast', 10, '09:00 - 10:00', '2025-09-04 02:39:16', '2025-09-04 02:39:16'),
(77, 22, 'lunch', 10, '12:00 - 13:00', '2025-09-04 02:39:16', '2025-09-04 02:39:16'),
(78, 22, 'dinner', 10, '20:00 - 21:00', '2025-09-04 02:39:16', '2025-09-04 02:39:16'),
(88, 23, 'breakfast', 11, '08:00 - 09:00', '2025-09-11 06:27:06', '2025-09-11 06:27:06'),
(89, 23, 'lunch', 11, '12:00 - 13:00', '2025-09-11 06:27:06', '2025-09-11 06:27:06'),
(90, 23, 'dinner', 11, '19:00 - 20:00', '2025-09-11 06:27:06', '2025-09-11 06:27:06');

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `feedback_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `feedback_type` enum('meal','service','platform') NOT NULL,
  `rating` tinyint(4) NOT NULL CHECK (`rating` between 1 and 5),
  `comments` text DEFAULT NULL,
  `meal_description` varchar(255) DEFAULT NULL COMMENT 'Optional field for meal feedback',
  `delivery_date` date DEFAULT NULL COMMENT 'Optional field for service feedback',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`feedback_id`, `user_id`, `feedback_type`, `rating`, `comments`, `meal_description`, `delivery_date`, `created_at`, `updated_at`) VALUES
(10, 22, 'platform', 3, 'Good', NULL, NULL, '2025-08-31 13:50:33', '2025-08-31 13:50:33'),
(11, 9, 'platform', 5, 'ui is great', NULL, NULL, '2025-09-01 07:56:18', '2025-09-01 07:56:18'),
(12, 9, 'service', 4, '[Partner: Manoj #16 • 9470076894] on time', NULL, '2025-09-01', '2025-09-01 08:11:52', '2025-09-01 08:11:52'),
(14, 9, 'meal', 5, 'Very delicious', 'Chicken pulao + raita', NULL, '2025-09-01 16:02:09', '2025-09-01 16:02:09'),
(15, 9, 'meal', 5, 'Very tasty', 'Masala dosa + coconut chutney', NULL, '2025-09-01 16:04:06', '2025-09-01 16:04:06'),
(16, 9, 'meal', 5, 'Very delicious', 'Chicken curry + parotta', NULL, '2025-09-01 16:04:47', '2025-09-01 16:04:47'),
(17, 9, 'meal', 5, 'very tasty', 'Lemon rice + curd + vada', NULL, '2025-09-01 16:06:27', '2025-09-01 16:06:27'),
(18, 9, 'meal', 5, 'very delicious', 'Mutton biryani + raita', NULL, '2025-09-01 16:06:46', '2025-09-01 16:06:46'),
(19, 9, 'meal', 5, 'very tasty', 'Idli + chutney', NULL, '2025-09-01 16:07:27', '2025-09-01 16:07:27');

-- --------------------------------------------------------

--
-- Table structure for table `inquiries`
--

CREATE TABLE `inquiries` (
  `inquiry_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `inquiry_type` varchar(50) NOT NULL DEFAULT 'general',
  `response` text DEFAULT NULL,
  `status` enum('pending','responded','closed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inquiries`
--

INSERT INTO `inquiries` (`inquiry_id`, `user_id`, `message`, `inquiry_type`, `response`, `status`, `created_at`) VALUES
(1, 9, 'payment cancelled', 'other', NULL, 'pending', '2025-08-06 18:45:14'),
(5, 9, 'not loading', 'technical', 'will fix soon ma\'am.', 'responded', '2025-08-06 18:52:26'),
(6, 9, 'Some bugs.', 'technical', 'Will fix soon.', 'responded', '2025-08-10 07:41:26');

-- --------------------------------------------------------

--
-- Table structure for table `meals`
--

CREATE TABLE `meals` (
  `meal_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `meal_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `meals`
--

INSERT INTO `meals` (`meal_id`, `category_id`, `meal_name`, `description`, `image_url`, `is_active`) VALUES
(1, 1, 'Idli + chutney', NULL, NULL, 1),
(2, 4, 'Chicken curry + rice', NULL, NULL, 1),
(3, 5, 'Veg curry + chapati', NULL, NULL, 1),
(4, 6, 'Egg curry + chapati', NULL, NULL, 1),
(5, 1, 'Upma + banana', NULL, NULL, 1),
(6, 3, 'Chole + jeera rice', NULL, NULL, 1),
(7, 5, 'Veg kurma + chapati', NULL, NULL, 1),
(8, 6, 'Egg curry + chapati', NULL, NULL, 1),
(9, 1, 'Poori + potato masala', NULL, NULL, 1),
(10, 3, 'Sambar + rice + papad', NULL, NULL, 1),
(11, 5, 'Dal fry + chapati', NULL, NULL, 1),
(12, 6, 'Chicken curry + chapati', NULL, NULL, 1),
(13, 1, 'Dosa + chutney', NULL, NULL, 1),
(14, 3, 'Mixed veg curry + rice', NULL, NULL, 1),
(15, 5, 'Dal fry + roti', NULL, NULL, 1),
(16, 6, 'Egg masala + roti', NULL, NULL, 1),
(17, 1, 'Appam + coconut milk', NULL, NULL, 1),
(18, 3, 'Veg pulao', NULL, NULL, 1),
(19, 4, 'Egg masala + lemon rice', NULL, NULL, 1),
(20, 5, 'Paneer curry + roti', NULL, NULL, 1),
(21, 6, 'Chicken curry + roti', NULL, NULL, 1),
(22, 1, 'Bread + jam/butter', NULL, NULL, 1),
(23, 2, 'Omelette + toast', NULL, NULL, 1),
(24, 3, 'Veg pulao + raita', NULL, NULL, 1),
(25, 4, 'Chicken pulao + raita', NULL, NULL, 1),
(26, 5, 'Chana masala + chapati', NULL, NULL, 1),
(27, 1, 'Idiyappam + veg curry', NULL, NULL, 1),
(28, 3, 'Sambar + rice', NULL, NULL, 1),
(29, 5, 'Veg stew + parota', NULL, NULL, 1),
(30, 6, 'Chicken curry + parotta', NULL, NULL, 1),
(31, 1, 'Appam + veg stew', NULL, NULL, 1),
(32, 2, 'Appam + Chicken stew', NULL, NULL, 1),
(33, 1, 'Puttu + kadala curry', NULL, NULL, 0),
(34, 1, 'Masala dosa with sambar', NULL, NULL, 1),
(35, 3, 'Veg biryani + raita + papad', NULL, NULL, 1),
(36, 4, 'Chicken biryani + raita + mirchi ka salan', NULL, NULL, 1),
(37, 4, 'Fish curry meal (rice + fish curry + sides)', NULL, NULL, 1),
(38, 3, 'Vegetable pulao + paneer butter masala', NULL, NULL, 1),
(39, 5, 'Paneer curry + naan + Payasam + salad', NULL, NULL, 1),
(40, 6, 'Mutton fry + parotta + Payasam + salad', NULL, NULL, 1),
(41, 6, 'Chicken tikka masala + garlic naan + dessert', NULL, NULL, 1),
(42, 5, 'Vegetable kofta + roti + dal tadka', NULL, NULL, 1),
(43, 1, 'Masala dosa + coconut chutney', NULL, NULL, 1),
(44, 2, 'Egg dosa + sambar', NULL, NULL, 1),
(45, 1, 'Poha with peanuts and sev', NULL, NULL, 1),
(46, 1, 'Upma with vegetables', NULL, NULL, 1),
(47, 3, 'Kerala veg biryani + pachadi', NULL, NULL, 1),
(48, 4, 'Prawn fried rice + manchurian', NULL, NULL, 1),
(49, 3, 'Lemon rice + curd + vada', NULL, NULL, 1),
(50, 3, 'Jeera rice + dal makhani', NULL, NULL, 1),
(51, 5, 'Paneer butter masala + naan + Gulab Jamun + soup', NULL, NULL, 1),
(52, 6, 'Chicken chettinad + appam + dessert', NULL, NULL, 1),
(53, 5, 'Dal makhani + jeera rice + salad', NULL, NULL, 1),
(54, 5, 'Mushroom masala + roti + raita', NULL, NULL, 1),
(55, 1, 'Idli + sambar + chutney', NULL, NULL, 1),
(56, 2, 'Egg bhurji + toast', NULL, NULL, 1),
(57, 1, 'Rava idli + coconut chutney', NULL, NULL, 1),
(58, 1, 'Aloo paratha + curd', NULL, NULL, 1),
(59, 3, 'Sambar rice + papad + pickle', NULL, NULL, 1),
(60, 4, 'Chicken curry + ghee rice + fry', NULL, NULL, 1),
(61, 3, 'Vegetable pulao + raita', NULL, NULL, 1),
(62, 3, 'Dal tadka + jeera rice + papad', NULL, NULL, 1),
(63, 5, 'Malai kofta + naan + kheer', NULL, NULL, 1),
(64, 6, 'Butter chicken + naan + salad', NULL, NULL, 1),
(65, 5, 'Dal fry + roti + pickle', NULL, NULL, 1),
(66, 5, 'Chana masala + bhatura + onion salad', NULL, NULL, 1),
(67, 1, 'Dosa + potato masala + chutney', NULL, NULL, 1),
(68, 2, 'Egg sandwich + juice', NULL, NULL, 1),
(69, 1, 'Medu vada + sambar', NULL, NULL, 1),
(70, 1, 'Besan chilla + mint chutney', NULL, NULL, 1),
(71, 3, 'Rajma chawal + salad', NULL, NULL, 1),
(72, 4, 'Fish fry + lemon rice', NULL, NULL, 1),
(73, 3, 'Kadhi pakora + steamed rice', NULL, NULL, 1),
(74, 3, 'Veg fried rice + manchurian', NULL, NULL, 1),
(75, 5, 'Palak paneer + roti + gulab jamun', NULL, NULL, 1),
(76, 6, 'Mutton curry + biryani + raita', NULL, NULL, 1),
(77, 5, 'Mix veg + roti + dal', NULL, NULL, 1),
(78, 6, 'Chicken biryani + mirchi ka salan', NULL, NULL, 1),
(79, 1, 'Uttapam + sambar', NULL, NULL, 1),
(80, 2, 'Scrambled eggs + toast', NULL, NULL, 1),
(81, 1, 'Rava upma + coconut chutney', NULL, NULL, 1),
(82, 1, 'Pesarattu + ginger chutney', NULL, NULL, 1),
(83, 3, 'Curd rice + pickle + papad', NULL, NULL, 1),
(84, 4, 'Chicken pulao + raita', NULL, NULL, 1),
(85, 3, 'Veg khichdi + kadhi', NULL, NULL, 1),
(86, 3, 'Tomato rice + potato fry', NULL, NULL, 1),
(87, 5, 'Kadai paneer + naan + rasmalai', NULL, NULL, 1),
(88, 6, 'Chicken korma + naan + salad', NULL, NULL, 1),
(89, 5, 'Dal makhani + jeera rice', NULL, NULL, 1),
(90, 6, 'Egg curry + paratha + pickle', NULL, NULL, 1),
(91, 1, 'Pongal + sambar + chutney', NULL, NULL, 1),
(92, 2, 'Bacon and eggs + toast', NULL, NULL, 1),
(93, 1, 'Mysore masala dosa', NULL, NULL, 1),
(94, 1, 'Moong dal chilla + chutney', NULL, NULL, 1),
(95, 3, 'Bisi bele bath + papad', NULL, NULL, 1),
(96, 4, 'Mutton biryani + raita', NULL, NULL, 1),
(97, 3, 'Vegetable biryani + boondi raita', NULL, NULL, 1),
(98, 3, 'Pulao + paneer gravy', NULL, NULL, 1),
(99, 5, 'Navratan korma + naan + jalebi', NULL, NULL, 1),
(100, 6, 'Prawn masala + fried rice', NULL, NULL, 1),
(101, 5, 'Dal tadka + phulka + salad', NULL, NULL, 1),
(102, 6, 'Chicken 65 + noodles', NULL, NULL, 1),
(103, 2, 'Egg Benedict + hash browns', NULL, NULL, 1),
(104, 1, 'Rava dosa + chutney', NULL, NULL, 1),
(105, 1, 'Aloo paratha + butter + curd', NULL, NULL, 1),
(106, 3, 'Thali (3 veg + dal + rice + roti + salad)', NULL, NULL, 1),
(107, 4, 'Butter chicken + naan + rice', NULL, NULL, 1),
(108, 4, 'Hyderabadi biryani + mirchi ka salan', NULL, NULL, 1),
(109, 3, 'Veg thali (4 curries + rice + breads)', NULL, NULL, 1),
(110, 5, 'Shahi paneer + naan + kulfi', NULL, NULL, 1),
(111, 6, 'Mutton rogan josh + naan + salad', NULL, NULL, 1),
(112, 5, 'Dal fry + jeera rice + papad', NULL, NULL, 1),
(113, 6, 'Chicken tikka + rumali roti + salad', NULL, NULL, 1),
(114, 3, 'Veg thali', NULL, NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `meal_categories`
--

CREATE TABLE `meal_categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(50) NOT NULL,
  `meal_type` enum('Breakfast','Lunch','Dinner') NOT NULL,
  `option_type` enum('veg','non_veg') NOT NULL,
  `slot` enum('breakfast','lunch','dinner') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `meal_categories`
--

INSERT INTO `meal_categories` (`category_id`, `category_name`, `meal_type`, `option_type`, `slot`) VALUES
(1, 'Breakfast Veg', 'Breakfast', 'veg', 'breakfast'),
(2, 'Breakfast Non-Veg', 'Breakfast', 'non_veg', 'breakfast'),
(3, 'Lunch Veg', 'Lunch', 'veg', 'lunch'),
(4, 'Lunch Non-Veg', 'Lunch', 'non_veg', 'lunch'),
(5, 'Dinner Veg', 'Dinner', 'veg', 'dinner'),
(6, 'Dinner Non-Veg', 'Dinner', 'non_veg', 'dinner');

-- --------------------------------------------------------

--
-- Table structure for table `meal_plans`
--

CREATE TABLE `meal_plans` (
  `plan_id` int(11) NOT NULL,
  `plan_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `plan_type` enum('basic','premium') NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `base_price` decimal(8,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `meal_plans`
--

INSERT INTO `meal_plans` (`plan_id`, `plan_name`, `description`, `plan_type`, `is_active`, `base_price`) VALUES
(1, 'Basic', 'Simple meals perfect for students and working professionals looking for daily meals at an affordable price.', 'basic', 1, 250.00),
(2, 'Premium', 'Enhanced meal experience. Fully customizable meals with both veg and non-veg options, complete with delicious desserts.', 'premium', 1, 320.00);

-- --------------------------------------------------------

--
-- Table structure for table `partner_payments`
--

CREATE TABLE `partner_payments` (
  `payment_id` int(11) NOT NULL,
  `partner_id` int(11) NOT NULL,
  `subscription_id` int(11) DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `delivery_count` int(11) NOT NULL DEFAULT 0,
  `payment_method` varchar(50) NOT NULL,
  `payment_status` enum('success','failed','pending') NOT NULL DEFAULT 'success',
  `transaction_ref` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `partner_payments`
--

INSERT INTO `partner_payments` (`payment_id`, `partner_id`, `subscription_id`, `delivery_date`, `amount`, `delivery_count`, `payment_method`, `payment_status`, `transaction_ref`, `created_at`) VALUES
(14, 16, 64, '2025-09-01', 90.00, 3, 'Razorpay', 'success', 'pay_RDfv93o22r5UXw', '2025-09-04 21:36:41'),
(16, 16, 66, '2025-09-03', 90.00, 3, 'Razorpay', 'success', 'pay_RDgG0ddt4XfFJk', '2025-09-04 21:56:26'),
(17, 16, 64, '2025-09-02', 90.00, 3, 'Razorpay', 'success', 'pay_RDn8DK2xmcjkwB', '2025-09-05 04:39:47'),
(18, 16, 64, '2025-09-03', 60.00, 2, 'Razorpay', 'success', 'pay_RDn8vE3NCEyqu6', '2025-09-05 04:40:28'),
(19, 16, 64, '2025-09-04', 90.00, 3, 'Razorpay', 'success', 'pay_RDn9IiKR3p1o46', '2025-09-05 04:40:49'),
(20, 16, 66, '2025-09-04', 90.00, 3, 'Razorpay', 'success', 'pay_RDn9fRrxIcJqsg', '2025-09-05 04:41:10'),
(21, 19, 69, '2025-09-04', 90.00, 3, 'Razorpay', 'success', 'pay_RDnFlT1yPhEBuV', '2025-09-05 04:46:57'),
(22, 16, 64, '2025-09-05', 120.00, 3, 'Razorpay', 'success', 'pay_REB51Fbclu3HKk', '2025-09-06 04:05:24'),
(23, 16, 66, '2025-09-05', 120.00, 3, 'Razorpay', 'success', 'pay_REB5MTKwnX9JzL', '2025-09-06 04:05:44'),
(24, 19, 69, '2025-09-05', 120.00, 3, 'Razorpay', 'success', 'pay_REB5hsRbBH6e6q', '2025-09-06 04:06:03'),
(25, 19, 69, '2025-09-06', 120.00, 3, 'Razorpay', 'success', 'pay_RFBy37pF7LO96z', '2025-09-08 17:36:29'),
(26, 16, 64, '2025-09-08', 120.00, 3, 'Razorpay', 'success', 'pay_RFByQA1e1qvBVn', '2025-09-08 17:36:50'),
(27, 16, 66, '2025-09-08', 120.00, 3, 'Razorpay', 'success', 'pay_RFBzG76aeQzS57', '2025-09-08 17:37:38'),
(28, 19, 69, '2025-09-08', 120.00, 3, 'Razorpay', 'success', 'pay_RFBzkk7XPSQUNo', '2025-09-08 17:38:06'),
(29, 19, 69, '2025-09-09', 120.00, 3, 'Razorpay', 'success', 'pay_RHjNRiOu4TYj1w', '2025-09-15 03:35:34'),
(30, 16, 66, '2025-09-09', 120.00, 3, 'Razorpay', 'success', 'pay_RHjNzsx0FrZhvH', '2025-09-15 03:36:05'),
(31, 16, 66, '2025-09-15', 40.00, 1, 'Razorpay', 'success', 'pay_RHjON0y9wAsPbU', '2025-09-15 03:36:26'),
(32, 16, 64, '2025-09-09', 120.00, 3, 'Razorpay', 'success', 'pay_RHjOniOksBMMba', '2025-09-15 03:36:50'),
(33, 19, 69, '2025-09-10', 120.00, 3, 'Razorpay', 'success', 'pay_RHjPAOwZsh2cWG', '2025-09-15 03:37:11'),
(34, 16, 66, '2025-09-10', 120.00, 3, 'Razorpay', 'success', 'pay_RHjPWsQVWtUIk3', '2025-09-15 03:37:32'),
(35, 16, 64, '2025-09-10', 120.00, 3, 'Razorpay', 'success', 'pay_RHjPu76Nr176dM', '2025-09-15 03:37:53'),
(36, 16, 64, '2025-09-12', 120.00, 3, 'Razorpay', 'success', 'pay_RHjQFhM0HHjUaI', '2025-09-15 03:38:13'),
(37, 19, 69, '2025-09-11', 120.00, 3, 'Razorpay', 'success', 'pay_RHjQbikKIRTUst', '2025-09-15 03:38:33'),
(38, 16, 66, '2025-09-11', 120.00, 3, 'Razorpay', 'success', 'pay_RHjQxp7Ar33UxY', '2025-09-15 03:38:53'),
(39, 16, 64, '2025-09-11', 120.00, 3, 'Razorpay', 'success', 'pay_RHjRtmhhUOy97G', '2025-09-15 03:39:46'),
(40, 16, 66, '2025-09-12', 120.00, 3, 'Razorpay', 'success', 'pay_RHjSDrJNQ1DEkk', '2025-09-15 03:40:04');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `subscription_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `payment_status` enum('pending','success','failed') NOT NULL DEFAULT 'pending',
  `transaction_ref` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `user_id`, `subscription_id`, `amount`, `payment_method`, `payment_status`, `transaction_ref`, `created_at`) VALUES
(34, 9, 64, 1999.00, 'Razorpay', 'success', 'pay_RCFBOdxy2X7nh0', '2025-09-01 06:48:17'),
(36, 21, 66, 999.50, 'Razorpay', 'success', 'pay_RCevmAohmfcvi6', '2025-09-02 07:59:31'),
(39, 22, 69, 1199.71, 'Razorpay', 'success', 'pay_RDMYD1SEUwNlPt', '2025-09-04 02:39:41'),
(41, 23, 84, 2500.00, 'Razorpay', 'success', 'pay_RFc4yrunsHuXRH', '2025-09-09 19:09:09'),
(42, 23, 86, 1500.00, 'Razorpay', 'success', 'pay_RGCB8o1Xhv78Fr', '2025-09-11 06:28:02');

-- --------------------------------------------------------

--
-- Table structure for table `plan_features`
--

CREATE TABLE `plan_features` (
  `feature_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `feature_text` varchar(255) NOT NULL,
  `sort_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `plan_features`
--

INSERT INTO `plan_features` (`feature_id`, `plan_id`, `feature_text`, `sort_order`) VALUES
(2, 1, 'simple veg/non-veg meals', 0),
(3, 1, 'No meal customization', 0),
(4, 1, 'Simple eco-packaging', 0),
(6, 2, 'Customizable menu', 0),
(7, 2, 'Deserts available', 0),
(8, 2, 'Premium Packaging', 0);

-- --------------------------------------------------------

--
-- Table structure for table `plan_images`
--

CREATE TABLE `plan_images` (
  `image_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `sort_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `plan_images`
--

INSERT INTO `plan_images` (`image_id`, `plan_id`, `image_url`, `sort_order`) VALUES
(2, 1, 'assets/meals/plan_1_20250822063418_cf1884.jpg', 0),
(3, 1, 'assets/meals/plan_1_20250822063521_191454.jpg', 0),
(4, 1, 'assets/meals/plan_1_20250822063616_7fcaa6.jpg', 0),
(5, 1, 'assets/meals/plan_1_20250822063724_485e0a.jpg', 0),
(7, 2, 'assets/meals/plan_2_20250822063820_27a8a3.jpg', 0),
(8, 2, 'assets/meals/plan_2_20250822063838_66d234.jpg', 0),
(9, 2, 'assets/meals/plan_2_20250822063849_e0c890.jpg', 0),
(10, 2, 'assets/meals/plan_2_20250822063956_388757.jpg', 0);

-- --------------------------------------------------------

--
-- Table structure for table `plan_meals`
--

CREATE TABLE `plan_meals` (
  `plan_meal_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `day_of_week` enum('MONDAY','TUESDAY','WEDNESDAY','THURSDAY','FRIDAY','SATURDAY','SUNDAY') NOT NULL,
  `meal_type` enum('Breakfast','Lunch','Dinner') NOT NULL,
  `meal_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `plan_meals`
--

INSERT INTO `plan_meals` (`plan_meal_id`, `plan_id`, `day_of_week`, `meal_type`, `meal_id`) VALUES
(1, 1, 'MONDAY', 'Breakfast', 1),
(2, 1, 'MONDAY', 'Lunch', 114),
(3, 1, 'MONDAY', 'Lunch', 2),
(4, 1, 'MONDAY', 'Dinner', 3),
(5, 1, 'MONDAY', 'Dinner', 4),
(6, 1, 'TUESDAY', 'Breakfast', 5),
(7, 1, 'TUESDAY', 'Lunch', 6),
(8, 1, 'TUESDAY', 'Dinner', 7),
(9, 1, 'TUESDAY', 'Dinner', 8),
(10, 1, 'WEDNESDAY', 'Breakfast', 9),
(11, 1, 'WEDNESDAY', 'Lunch', 10),
(12, 1, 'WEDNESDAY', 'Dinner', 11),
(13, 1, 'WEDNESDAY', 'Dinner', 12),
(14, 1, 'THURSDAY', 'Breakfast', 13),
(15, 1, 'THURSDAY', 'Lunch', 14),
(16, 1, 'THURSDAY', 'Dinner', 15),
(17, 1, 'THURSDAY', 'Dinner', 16),
(18, 1, 'FRIDAY', 'Breakfast', 17),
(19, 1, 'FRIDAY', 'Lunch', 18),
(20, 1, 'FRIDAY', 'Lunch', 19),
(21, 1, 'FRIDAY', 'Dinner', 20),
(22, 1, 'FRIDAY', 'Dinner', 21),
(23, 1, 'SATURDAY', 'Breakfast', 22),
(24, 1, 'SATURDAY', 'Breakfast', 23),
(25, 1, 'SATURDAY', 'Lunch', 24),
(26, 1, 'SATURDAY', 'Lunch', 25),
(27, 1, 'SATURDAY', 'Dinner', 26),
(28, 1, 'SATURDAY', 'Dinner', 8),
(29, 1, 'SUNDAY', 'Breakfast', 27),
(30, 1, 'SUNDAY', 'Breakfast', 8),
(31, 1, 'SUNDAY', 'Lunch', 28),
(32, 1, 'SUNDAY', 'Lunch', 2),
(33, 1, 'SUNDAY', 'Dinner', 29),
(34, 1, 'SUNDAY', 'Dinner', 30),
(35, 2, 'MONDAY', 'Breakfast', 31),
(36, 2, 'MONDAY', 'Breakfast', 32),
(38, 2, 'MONDAY', 'Breakfast', 34),
(39, 2, 'MONDAY', 'Lunch', 35),
(40, 2, 'MONDAY', 'Lunch', 36),
(41, 2, 'MONDAY', 'Lunch', 37),
(42, 2, 'MONDAY', 'Lunch', 38),
(43, 2, 'MONDAY', 'Dinner', 39),
(44, 2, 'MONDAY', 'Dinner', 40),
(45, 2, 'MONDAY', 'Dinner', 41),
(46, 2, 'MONDAY', 'Dinner', 42),
(47, 2, 'TUESDAY', 'Breakfast', 43),
(48, 2, 'TUESDAY', 'Breakfast', 44),
(49, 2, 'TUESDAY', 'Breakfast', 45),
(50, 2, 'TUESDAY', 'Breakfast', 46),
(51, 2, 'TUESDAY', 'Lunch', 47),
(52, 2, 'TUESDAY', 'Lunch', 48),
(53, 2, 'TUESDAY', 'Lunch', 49),
(54, 2, 'TUESDAY', 'Lunch', 50),
(55, 2, 'TUESDAY', 'Dinner', 51),
(56, 2, 'TUESDAY', 'Dinner', 52),
(57, 2, 'TUESDAY', 'Dinner', 53),
(58, 2, 'TUESDAY', 'Dinner', 54),
(59, 2, 'WEDNESDAY', 'Breakfast', 55),
(60, 2, 'WEDNESDAY', 'Breakfast', 56),
(61, 2, 'WEDNESDAY', 'Breakfast', 57),
(62, 2, 'WEDNESDAY', 'Breakfast', 58),
(63, 2, 'WEDNESDAY', 'Lunch', 59),
(64, 2, 'WEDNESDAY', 'Lunch', 60),
(65, 2, 'WEDNESDAY', 'Lunch', 61),
(66, 2, 'WEDNESDAY', 'Lunch', 62),
(67, 2, 'WEDNESDAY', 'Dinner', 63),
(68, 2, 'WEDNESDAY', 'Dinner', 64),
(69, 2, 'WEDNESDAY', 'Dinner', 65),
(70, 2, 'WEDNESDAY', 'Dinner', 66),
(71, 2, 'THURSDAY', 'Breakfast', 67),
(72, 2, 'THURSDAY', 'Breakfast', 68),
(73, 2, 'THURSDAY', 'Breakfast', 69),
(74, 2, 'THURSDAY', 'Breakfast', 70),
(75, 2, 'THURSDAY', 'Lunch', 71),
(76, 2, 'THURSDAY', 'Lunch', 72),
(77, 2, 'THURSDAY', 'Lunch', 73),
(78, 2, 'THURSDAY', 'Lunch', 74),
(79, 2, 'THURSDAY', 'Dinner', 75),
(80, 2, 'THURSDAY', 'Dinner', 76),
(81, 2, 'THURSDAY', 'Dinner', 77),
(82, 2, 'THURSDAY', 'Dinner', 78),
(83, 2, 'FRIDAY', 'Breakfast', 79),
(84, 2, 'FRIDAY', 'Breakfast', 80),
(85, 2, 'FRIDAY', 'Breakfast', 81),
(86, 2, 'FRIDAY', 'Breakfast', 82),
(87, 2, 'FRIDAY', 'Lunch', 83),
(88, 2, 'FRIDAY', 'Lunch', 84),
(89, 2, 'FRIDAY', 'Lunch', 85),
(90, 2, 'FRIDAY', 'Lunch', 86),
(91, 2, 'FRIDAY', 'Dinner', 87),
(92, 2, 'FRIDAY', 'Dinner', 88),
(93, 2, 'FRIDAY', 'Dinner', 89),
(94, 2, 'FRIDAY', 'Dinner', 90),
(95, 2, 'SATURDAY', 'Breakfast', 91),
(96, 2, 'SATURDAY', 'Breakfast', 92),
(97, 2, 'SATURDAY', 'Breakfast', 93),
(98, 2, 'SATURDAY', 'Breakfast', 94),
(99, 2, 'SATURDAY', 'Lunch', 95),
(100, 2, 'SATURDAY', 'Lunch', 96),
(101, 2, 'SATURDAY', 'Lunch', 97),
(102, 2, 'SATURDAY', 'Lunch', 98),
(103, 2, 'SATURDAY', 'Dinner', 99),
(104, 2, 'SATURDAY', 'Dinner', 100),
(105, 2, 'SATURDAY', 'Dinner', 101),
(106, 2, 'SATURDAY', 'Dinner', 102),
(107, 2, 'SUNDAY', 'Breakfast', 9),
(108, 2, 'SUNDAY', 'Breakfast', 103),
(109, 2, 'SUNDAY', 'Breakfast', 104),
(110, 2, 'SUNDAY', 'Breakfast', 105),
(111, 2, 'SUNDAY', 'Lunch', 106),
(112, 2, 'SUNDAY', 'Lunch', 107),
(113, 2, 'SUNDAY', 'Lunch', 108),
(114, 2, 'SUNDAY', 'Lunch', 109),
(115, 2, 'SUNDAY', 'Dinner', 110),
(116, 2, 'SUNDAY', 'Dinner', 111),
(117, 2, 'SUNDAY', 'Dinner', 112),
(118, 2, 'SUNDAY', 'Dinner', 113);

-- --------------------------------------------------------

--
-- Table structure for table `plan_schedule_options`
--

CREATE TABLE `plan_schedule_options` (
  `schedule_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `schedule_type` enum('weekdays','extended','full_week') NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `days_count` tinyint(4) NOT NULL,
  `price_multiplier` decimal(3,2) DEFAULT 1.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `plan_schedule_options`
--

INSERT INTO `plan_schedule_options` (`schedule_id`, `plan_id`, `schedule_type`, `description`, `days_count`, `price_multiplier`) VALUES
(0, 1, 'weekdays', NULL, 5, 1.00),
(0, 1, 'extended', NULL, 6, 1.20),
(0, 1, 'full_week', NULL, 7, 1.75),
(0, 2, 'weekdays', NULL, 5, 1.00),
(0, 2, 'extended', NULL, 6, 1.20),
(0, 2, 'full_week', NULL, 7, 1.75);

-- --------------------------------------------------------

--
-- Table structure for table `popular_meals`
--

CREATE TABLE `popular_meals` (
  `id` int(11) NOT NULL,
  `meal_id` int(11) NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `plan_type` enum('basic','premium') NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `popular_meals`
--

INSERT INTO `popular_meals` (`id`, `meal_id`, `image_url`, `description`, `plan_type`, `sort_order`, `is_active`, `created_at`) VALUES
(1, 96, 'assets/meals/popular_1755848868_79cc5b9f.jpg', 'Fragrant basmati rice cooked with tender meat and aromatic spices', 'premium', 0, 1, '2025-08-22 07:47:48'),
(2, 1, 'assets/meals/popular_1755849052_dc93d7e2.jpg', 'Steamed rice cakes served with coconut chutney', 'basic', 0, 1, '2025-08-22 07:50:52'),
(3, 49, 'assets/meals/popular_1755849179_ff91ef5b.jpg', 'Fragrant rice tossed with zesty lemon juice, curry leaves, and spices served with curd and vada', 'premium', 0, 1, '2025-08-22 07:52:59'),
(4, 30, 'assets/meals/popular_1755849253_b82131c2.jpg', 'Flaky layered Parota served with rich, spiced chicken curry', 'basic', 0, 1, '2025-08-22 07:54:13'),
(6, 43, 'assets/meals/popular_1755849412_dfc34471.jpg', 'Crispy rice crepe filled with spiced potato filling served with delicious coconut chutney', 'premium', 0, 1, '2025-08-22 07:56:52'),
(8, 25, 'assets/meals/popular_1755849658_e5910582.jpg', 'Fragrant pulao rice mixed with chicken tossed with curry leaves, and spices served with raita', 'basic', 0, 1, '2025-08-22 08:00:58');

-- --------------------------------------------------------

--
-- Table structure for table `subscriptions`
--

CREATE TABLE `subscriptions` (
  `subscription_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `plan_id` int(11) DEFAULT NULL,
  `dietary_preference` varchar(20) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `schedule` enum('Weekdays','Extended','Full Week') NOT NULL,
  `delivery_time` time NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `payment_status` enum('paid','unpaid','failed') DEFAULT 'unpaid',
  `status` enum('active','pending','cancelled','expired','completed') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subscriptions`
--

INSERT INTO `subscriptions` (`subscription_id`, `user_id`, `plan_id`, `dietary_preference`, `start_date`, `end_date`, `schedule`, `delivery_time`, `total_price`, `payment_status`, `status`, `created_at`) VALUES
(64, 9, 1, 'veg', '2025-09-01', '2025-09-26', 'Weekdays', '08:00:00', 1999.00, 'paid', 'active', '2025-09-01 06:47:36'),
(66, 21, 1, 'non_veg', '2025-09-03', '2025-09-16', 'Weekdays', '08:00:00', 999.50, 'paid', 'active', '2025-09-02 07:59:00'),
(69, 22, 2, '', '2025-09-04', '2025-09-11', 'Extended', '08:00:00', 1199.71, 'paid', 'completed', '2025-09-04 02:39:10'),
(83, 23, 1, 'veg', '2025-09-15', '2025-09-26', 'Weekdays', '08:00:00', 2500.00, 'paid', 'cancelled', '2025-09-09 18:59:10'),
(84, 23, 1, 'veg', '2025-09-15', '2025-09-26', 'Weekdays', '08:00:00', 2500.00, 'paid', 'cancelled', '2025-09-09 19:08:16'),
(86, 23, 1, 'veg', '2025-09-11', '2025-09-18', 'Weekdays', '08:00:00', 1500.00, 'paid', 'cancelled', '2025-09-11 06:27:01');

-- --------------------------------------------------------

--
-- Table structure for table `subscription_meals`
--

CREATE TABLE `subscription_meals` (
  `id` int(11) NOT NULL,
  `subscription_id` int(11) NOT NULL,
  `day_of_week` varchar(20) NOT NULL,
  `meal_type` varchar(20) NOT NULL,
  `meal_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subscription_meals`
--

INSERT INTO `subscription_meals` (`id`, `subscription_id`, `day_of_week`, `meal_type`, `meal_name`, `created_at`) VALUES
(1, 5, 'Monday', 'Breakfast', 'Appam + Chicken stew', '2025-08-09 11:59:47'),
(2, 5, 'Monday', 'Lunch', 'Veg biryani + raita + papad', '2025-08-09 11:59:47'),
(3, 5, 'Monday', 'Dinner', 'Chicken tikka masala + garlic naan + dessert', '2025-08-09 11:59:47'),
(4, 5, 'Tuesday', 'Breakfast', 'Poha with peanuts and sev', '2025-08-09 11:59:47'),
(5, 5, 'Tuesday', 'Lunch', 'Prawn fried rice + manchurian', '2025-08-09 11:59:47'),
(6, 5, 'Tuesday', 'Dinner', 'Chicken chettinad + appam + dessert', '2025-08-09 11:59:47'),
(7, 5, 'Wednesday', 'Breakfast', 'Egg bhurji + toast', '2025-08-09 11:59:47'),
(8, 5, 'Wednesday', 'Lunch', 'Chicken curry + ghee rice + fry', '2025-08-09 11:59:47'),
(9, 5, 'Wednesday', 'Dinner', 'Chana masala + bhatura + onion salad', '2025-08-09 11:59:47'),
(10, 5, 'Thursday', 'Breakfast', 'Egg sandwich + juice', '2025-08-09 11:59:47'),
(11, 5, 'Thursday', 'Lunch', 'Kadhi pakora + steamed rice', '2025-08-09 11:59:47'),
(12, 5, 'Thursday', 'Dinner', 'Palak paneer + roti + gulab jamun', '2025-08-09 11:59:47'),
(13, 5, 'Friday', 'Breakfast', 'Scrambled eggs + toast', '2025-08-09 11:59:47'),
(14, 5, 'Friday', 'Lunch', 'Chicken pulao + raita', '2025-08-09 11:59:47'),
(15, 5, 'Friday', 'Dinner', 'Chicken korma + naan + salad', '2025-08-09 11:59:47'),
(16, 5, 'Saturday', 'Breakfast', 'Mysore masala dosa', '2025-08-09 11:59:47'),
(17, 5, 'Saturday', 'Lunch', 'Mutton biryani + raita', '2025-08-09 11:59:47'),
(18, 5, 'Saturday', 'Dinner', 'Prawn masala + fried rice', '2025-08-09 11:59:47'),
(19, 21, 'Monday', 'Breakfast', 'Appam + veg stew', '2025-08-09 13:56:21'),
(20, 21, 'Monday', 'Lunch', 'Chicken biryani + raita + mirchi ka salan', '2025-08-09 13:56:21'),
(21, 21, 'Monday', 'Dinner', 'Mutton fry + parotta + Payasam + salad', '2025-08-09 13:56:21'),
(22, 21, 'Tuesday', 'Breakfast', 'Masala dosa + coconut chutney', '2025-08-09 13:56:21'),
(23, 21, 'Tuesday', 'Lunch', 'Lemon rice + curd + vada', '2025-08-09 13:56:21'),
(24, 21, 'Tuesday', 'Dinner', 'Dal makhani + jeera rice + salad', '2025-08-09 13:56:21'),
(25, 21, 'Wednesday', 'Breakfast', 'Egg bhurji + toast', '2025-08-09 13:56:21'),
(26, 21, 'Wednesday', 'Lunch', 'Vegetable pulao + raita', '2025-08-09 13:56:21'),
(27, 21, 'Wednesday', 'Dinner', 'Malai kofta + naan + kheer', '2025-08-09 13:56:21'),
(28, 21, 'Thursday', 'Breakfast', 'Egg sandwich + juice', '2025-08-09 13:56:21'),
(29, 21, 'Thursday', 'Lunch', 'Kadhi pakora + steamed rice', '2025-08-09 13:56:21'),
(30, 21, 'Thursday', 'Dinner', 'Mutton curry + biryani + raita', '2025-08-09 13:56:21'),
(31, 21, 'Friday', 'Breakfast', 'Scrambled eggs + toast', '2025-08-09 13:56:21'),
(32, 21, 'Friday', 'Lunch', 'Veg khichdi + kadhi', '2025-08-09 13:56:21'),
(33, 21, 'Friday', 'Dinner', 'Chicken korma + naan + salad', '2025-08-09 13:56:21'),
(34, 21, 'Saturday', 'Breakfast', 'Bacon and eggs + toast', '2025-08-09 13:56:21'),
(35, 21, 'Saturday', 'Lunch', 'Pulao + paneer gravy', '2025-08-09 13:56:21'),
(36, 21, 'Saturday', 'Dinner', 'Prawn masala + fried rice', '2025-08-09 13:56:21'),
(37, 21, 'Sunday', 'Breakfast', 'Egg Benedict + hash browns', '2025-08-09 13:56:21'),
(38, 21, 'Sunday', 'Lunch', 'Butter chicken + naan + rice', '2025-08-09 13:56:21'),
(39, 21, 'Sunday', 'Dinner', 'Chicken tikka + rumali roti + salad', '2025-08-09 13:56:21'),
(0, 28, 'Monday', 'Breakfast', 'Appam + Chicken stew', '2025-08-10 14:19:58'),
(0, 28, 'Monday', 'Lunch', 'Chicken biryani + raita + mirchi ka salan', '2025-08-10 14:19:58'),
(0, 28, 'Monday', 'Dinner', 'Mutton fry + parotta + Payasam + salad', '2025-08-10 14:19:58'),
(0, 28, 'Tuesday', 'Breakfast', 'Egg dosa + sambar', '2025-08-10 14:19:58'),
(0, 28, 'Tuesday', 'Lunch', 'Prawn fried rice + manchurian', '2025-08-10 14:19:58'),
(0, 28, 'Tuesday', 'Dinner', 'Paneer butter masala + naan + Gulab Jamun + soup', '2025-08-10 14:19:58'),
(0, 28, 'Wednesday', 'Breakfast', 'Rava idli + coconut chutney', '2025-08-10 14:19:58'),
(0, 28, 'Wednesday', 'Lunch', 'Chicken curry + ghee rice + fry', '2025-08-10 14:19:58'),
(0, 28, 'Wednesday', 'Dinner', 'Butter chicken + naan + salad', '2025-08-10 14:19:58'),
(0, 28, 'Thursday', 'Breakfast', 'Egg sandwich + juice', '2025-08-10 14:19:58'),
(0, 28, 'Thursday', 'Lunch', 'Kadhi pakora + steamed rice', '2025-08-10 14:19:58'),
(0, 28, 'Thursday', 'Dinner', 'Chicken biryani + mirchi ka salan', '2025-08-10 14:19:58'),
(0, 28, 'Friday', 'Breakfast', 'Pesarattu + ginger chutney', '2025-08-10 14:19:58'),
(0, 28, 'Friday', 'Lunch', 'Tomato rice + potato fry', '2025-08-10 14:19:58'),
(0, 28, 'Friday', 'Dinner', 'Kadai paneer + naan + rasmalai', '2025-08-10 14:19:58'),
(0, 28, 'Saturday', 'Breakfast', 'Bacon and eggs + toast', '2025-08-10 14:19:58'),
(0, 28, 'Saturday', 'Lunch', 'Mutton biryani + raita', '2025-08-10 14:19:58'),
(0, 28, 'Saturday', 'Dinner', 'Chicken 65 + noodles', '2025-08-10 14:19:58'),
(0, 49, 'Monday', 'Breakfast', 'Appam + veg stew', '2025-08-16 07:32:06'),
(0, 49, 'Monday', 'Lunch', 'Veg biryani + raita + papad', '2025-08-16 07:32:06'),
(0, 49, 'Monday', 'Dinner', 'Paneer curry + naan + Payasam + salad', '2025-08-16 07:32:06'),
(0, 49, 'Tuesday', 'Breakfast', 'Masala dosa + coconut chutney', '2025-08-16 07:32:06'),
(0, 49, 'Tuesday', 'Lunch', 'Prawn fried rice + manchurian', '2025-08-16 07:32:06'),
(0, 49, 'Tuesday', 'Dinner', 'Paneer butter masala + naan + Gulab Jamun + soup', '2025-08-16 07:32:06'),
(0, 49, 'Wednesday', 'Breakfast', 'Idli + sambar + chutney', '2025-08-16 07:32:06'),
(0, 49, 'Wednesday', 'Lunch', 'Sambar rice + papad + pickle', '2025-08-16 07:32:06'),
(0, 49, 'Wednesday', 'Dinner', 'Malai kofta + naan + kheer', '2025-08-16 07:32:06'),
(0, 49, 'Thursday', 'Breakfast', 'Dosa + potato masala + chutney', '2025-08-16 07:32:06'),
(0, 49, 'Thursday', 'Lunch', 'Rajma chawal + salad', '2025-08-16 07:32:06'),
(0, 49, 'Thursday', 'Dinner', 'Palak paneer + roti + gulab jamun', '2025-08-16 07:32:06'),
(0, 49, 'Friday', 'Breakfast', 'Scrambled eggs + toast', '2025-08-16 07:32:06'),
(0, 49, 'Friday', 'Lunch', 'Curd rice + pickle + papad', '2025-08-16 07:32:06'),
(0, 49, 'Friday', 'Dinner', 'Kadai paneer + naan + rasmalai', '2025-08-16 07:32:06'),
(0, 57, 'Monday', 'Breakfast', 'Appam + Chicken stew', '2025-08-24 14:33:24'),
(0, 57, 'Monday', 'Lunch', 'Veg biryani + raita + papad', '2025-08-24 14:33:24'),
(0, 57, 'Monday', 'Dinner', 'Mutton fry + parotta + Payasam + salad', '2025-08-24 14:33:24'),
(0, 57, 'Tuesday', 'Breakfast', 'Egg dosa + sambar', '2025-08-24 14:33:24'),
(0, 57, 'Tuesday', 'Lunch', 'Kerala veg biryani + pachadi', '2025-08-24 14:33:24'),
(0, 57, 'Tuesday', 'Dinner', 'Chicken chettinad + appam + dessert', '2025-08-24 14:33:24'),
(0, 57, 'Wednesday', 'Breakfast', 'Egg bhurji + toast', '2025-08-24 14:33:24'),
(0, 57, 'Wednesday', 'Lunch', 'Chicken curry + ghee rice + fry', '2025-08-24 14:33:24'),
(0, 57, 'Wednesday', 'Dinner', 'Dal fry + roti + pickle', '2025-08-24 14:33:24'),
(0, 57, 'Thursday', 'Breakfast', 'Egg sandwich + juice', '2025-08-24 14:33:24'),
(0, 57, 'Thursday', 'Lunch', 'Rajma chawal + salad', '2025-08-24 14:33:24'),
(0, 57, 'Thursday', 'Dinner', 'Mix veg + roti + dal', '2025-08-24 14:33:24'),
(0, 57, 'Friday', 'Breakfast', 'Scrambled eggs + toast', '2025-08-24 14:33:24'),
(0, 57, 'Friday', 'Lunch', 'Chicken pulao + raita', '2025-08-24 14:33:24'),
(0, 57, 'Friday', 'Dinner', 'Kadai paneer + naan + rasmalai', '2025-08-24 14:33:24'),
(0, 57, 'Saturday', 'Breakfast', 'Pongal + sambar + chutney', '2025-08-24 14:33:24'),
(0, 57, 'Saturday', 'Lunch', 'Vegetable biryani + boondi raita', '2025-08-24 14:33:24'),
(0, 57, 'Saturday', 'Dinner', 'Prawn masala + fried rice', '2025-08-24 14:33:24'),
(0, 58, 'Monday', 'Breakfast', 'Appam + Chicken stew', '2025-08-24 15:27:19'),
(0, 58, 'Monday', 'Lunch', 'Chicken biryani + raita + mirchi ka salan', '2025-08-24 15:27:19'),
(0, 58, 'Monday', 'Dinner', 'Mutton fry + parotta + Payasam + salad', '2025-08-24 15:27:19'),
(0, 58, 'Tuesday', 'Breakfast', 'Egg dosa + sambar', '2025-08-24 15:27:19'),
(0, 58, 'Tuesday', 'Lunch', 'Kerala veg biryani + pachadi', '2025-08-24 15:27:19'),
(0, 58, 'Tuesday', 'Dinner', 'Paneer butter masala + naan + Gulab Jamun + soup', '2025-08-24 15:27:19'),
(0, 58, 'Wednesday', 'Breakfast', 'Idli + sambar + chutney', '2025-08-24 15:27:19'),
(0, 58, 'Wednesday', 'Lunch', 'Chicken curry + ghee rice + fry', '2025-08-24 15:27:19'),
(0, 58, 'Wednesday', 'Dinner', 'Dal fry + roti + pickle', '2025-08-24 15:27:19'),
(0, 58, 'Thursday', 'Breakfast', 'Egg sandwich + juice', '2025-08-24 15:27:19'),
(0, 58, 'Thursday', 'Lunch', 'Fish fry + lemon rice', '2025-08-24 15:27:19'),
(0, 58, 'Thursday', 'Dinner', 'Mutton curry + biryani + raita', '2025-08-24 15:27:19'),
(0, 58, 'Friday', 'Breakfast', 'Rava upma + coconut chutney', '2025-08-24 15:27:19'),
(0, 58, 'Friday', 'Lunch', 'Curd rice + pickle + papad', '2025-08-24 15:27:19'),
(0, 58, 'Friday', 'Dinner', 'Chicken korma + naan + salad', '2025-08-24 15:27:19'),
(0, 58, 'Saturday', 'Breakfast', 'Mysore masala dosa', '2025-08-24 15:27:19'),
(0, 58, 'Saturday', 'Lunch', 'Mutton biryani + raita', '2025-08-24 15:27:19'),
(0, 58, 'Saturday', 'Dinner', 'Navratan korma + naan + jalebi', '2025-08-24 15:27:19'),
(0, 58, 'Sunday', 'Breakfast', 'Aloo paratha + butter + curd', '2025-08-24 15:27:19'),
(0, 58, 'Sunday', 'Lunch', 'Veg thali (4 curries + rice + breads)', '2025-08-24 15:27:19'),
(0, 58, 'Sunday', 'Dinner', 'Dal fry + jeera rice + papad', '2025-08-24 15:27:19'),
(0, 67, 'Monday', 'Breakfast', 'Appam + Chicken stew', '2025-09-03 07:05:02'),
(0, 67, 'Monday', 'Lunch', 'Veg biryani + raita + papad', '2025-09-03 07:05:02'),
(0, 67, 'Monday', 'Dinner', 'Mutton fry + parotta + Payasam + salad', '2025-09-03 07:05:02'),
(0, 67, 'Tuesday', 'Breakfast', 'Egg dosa + sambar', '2025-09-03 07:05:02'),
(0, 67, 'Tuesday', 'Lunch', 'Prawn fried rice + manchurian', '2025-09-03 07:05:02'),
(0, 67, 'Tuesday', 'Dinner', 'Chicken chettinad + appam + dessert', '2025-09-03 07:05:02'),
(0, 67, 'Wednesday', 'Breakfast', 'Rava idli + coconut chutney', '2025-09-03 07:05:02'),
(0, 67, 'Wednesday', 'Lunch', 'Dal tadka + jeera rice + papad', '2025-09-03 07:05:02'),
(0, 67, 'Wednesday', 'Dinner', 'Malai kofta + naan + kheer', '2025-09-03 07:05:02'),
(0, 67, 'Thursday', 'Breakfast', 'Egg sandwich + juice', '2025-09-03 07:05:02'),
(0, 67, 'Thursday', 'Lunch', 'Rajma chawal + salad', '2025-09-03 07:05:02'),
(0, 67, 'Thursday', 'Dinner', 'Chicken biryani + mirchi ka salan', '2025-09-03 07:05:02'),
(0, 67, 'Friday', 'Breakfast', 'Rava upma + coconut chutney', '2025-09-03 07:05:02'),
(0, 67, 'Friday', 'Lunch', 'Veg khichdi + kadhi', '2025-09-03 07:05:02'),
(0, 67, 'Friday', 'Dinner', 'Kadai paneer + naan + rasmalai', '2025-09-03 07:05:02'),
(0, 68, 'Monday', 'Breakfast', 'Puttu + kadala curry', '2025-09-03 07:15:37'),
(0, 68, 'Monday', 'Lunch', 'Veg biryani + raita + papad', '2025-09-03 07:15:37'),
(0, 68, 'Monday', 'Dinner', 'Mutton fry + parotta + Payasam + salad', '2025-09-03 07:15:37'),
(0, 68, 'Tuesday', 'Breakfast', 'Poha with peanuts and sev', '2025-09-03 07:15:37'),
(0, 68, 'Tuesday', 'Lunch', 'Lemon rice + curd + vada', '2025-09-03 07:15:37'),
(0, 68, 'Tuesday', 'Dinner', 'Paneer butter masala + naan + Gulab Jamun + soup', '2025-09-03 07:15:37'),
(0, 68, 'Wednesday', 'Breakfast', 'Idli + sambar + chutney', '2025-09-03 07:15:37'),
(0, 68, 'Wednesday', 'Lunch', 'Chicken curry + ghee rice + fry', '2025-09-03 07:15:37'),
(0, 68, 'Wednesday', 'Dinner', 'Malai kofta + naan + kheer', '2025-09-03 07:15:37'),
(0, 68, 'Thursday', 'Breakfast', 'Dosa + potato masala + chutney', '2025-09-03 07:15:37'),
(0, 68, 'Thursday', 'Lunch', 'Veg fried rice + manchurian', '2025-09-03 07:15:37'),
(0, 68, 'Thursday', 'Dinner', 'Mutton curry + biryani + raita', '2025-09-03 07:15:37'),
(0, 68, 'Friday', 'Breakfast', 'Pesarattu + ginger chutney', '2025-09-03 07:15:37'),
(0, 68, 'Friday', 'Lunch', 'Curd rice + pickle + papad', '2025-09-03 07:15:37'),
(0, 68, 'Friday', 'Dinner', 'Kadai paneer + naan + rasmalai', '2025-09-03 07:15:37'),
(0, 69, 'Monday', 'Breakfast', 'Appam + veg stew', '2025-09-04 02:39:10'),
(0, 69, 'Monday', 'Lunch', 'Chicken biryani + raita + mirchi ka salan', '2025-09-04 02:39:10'),
(0, 69, 'Monday', 'Dinner', 'Mutton fry + parotta + Payasam + salad', '2025-09-04 02:39:10'),
(0, 69, 'Tuesday', 'Breakfast', 'Egg dosa + sambar', '2025-09-04 02:39:10'),
(0, 69, 'Tuesday', 'Lunch', 'Prawn fried rice + manchurian', '2025-09-04 02:39:10'),
(0, 69, 'Tuesday', 'Dinner', 'Paneer butter masala + naan + Gulab Jamun + soup', '2025-09-04 02:39:10'),
(0, 69, 'Wednesday', 'Breakfast', 'Idli + sambar + chutney', '2025-09-04 02:39:10'),
(0, 69, 'Wednesday', 'Lunch', 'Sambar rice + papad + pickle', '2025-09-04 02:39:10'),
(0, 69, 'Wednesday', 'Dinner', 'Chana masala + bhatura + onion salad', '2025-09-04 02:39:10'),
(0, 69, 'Thursday', 'Breakfast', 'Dosa + potato masala + chutney', '2025-09-04 02:39:10'),
(0, 69, 'Thursday', 'Lunch', 'Rajma chawal + salad', '2025-09-04 02:39:10'),
(0, 69, 'Thursday', 'Dinner', 'Mix veg + roti + dal', '2025-09-04 02:39:10'),
(0, 69, 'Friday', 'Breakfast', 'Scrambled eggs + toast', '2025-09-04 02:39:10'),
(0, 69, 'Friday', 'Lunch', 'Chicken pulao + raita', '2025-09-04 02:39:10'),
(0, 69, 'Friday', 'Dinner', 'Kadai paneer + naan + rasmalai', '2025-09-04 02:39:10'),
(0, 69, 'Saturday', 'Breakfast', 'Mysore masala dosa', '2025-09-04 02:39:10'),
(0, 69, 'Saturday', 'Lunch', 'Bisi bele bath + papad', '2025-09-04 02:39:10'),
(0, 69, 'Saturday', 'Dinner', 'Navratan korma + naan + jalebi', '2025-09-04 02:39:10');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `role` enum('user','admin','delivery') DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `security_question` varchar(255) NOT NULL,
  `security_answer` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `name`, `email`, `password`, `phone`, `role`, `created_at`, `security_question`, `security_answer`) VALUES
(1, 'Admin', 'admin@tiffinly.com', '$2y$10$X4lIIb6i24UwpPaCS/mlGeivdep6.iEnpm0qt5hXEgbpjEz/GMrfO', '8884834606', 'admin', '2025-07-15 19:15:14', '', ''),
(9, 'Mariya', 'mariyajoby@gmail.com', '$2y$10$ECiHRsdgb2Lfv0kTrKORDudYv2hieAf2WLi0xasaOqMNZLUI4hKn6', '8884834606', 'user', '2025-07-21 03:36:30', 'What is your first pet\'s name?', '$2y$10$3FIi2G1ps2Rz88r4zTJHSudTwTtr4esWFFLD4fwbK7c7qhPHk78Py'),
(16, 'Manoj', 'manoj@gmail.com', '$2y$10$3KpdH57fgWYiUhYeG9/yFe6F7Kn8BxlW.G9C6MZDYWTj6dhRGvlba', '9470076894', 'delivery', '2025-08-10 09:08:32', 'What is your first pet\'s name?', '$2y$10$1CPTy/VKd/Rl63gmJOGffukunuNhlFOv1CJ7j4FeUoYI2Mgze3Sai'),
(19, 'Jobykuttan', 'joby123@gmail.com', '$2y$10$ShrQrv/wicKU5etrNzGTEuAZSTWuF3cyFVWlXH.dAw8X3Mv8qfYBS', '9800987654', 'delivery', '2025-08-24 14:11:12', 'What is your favorite childhood food?', '$2y$10$GDZlhhFdH8g/VsUo9OkTheQYcmJXygz2B/U2RqYeQcx3VRmoovhFW'),
(21, 'Saji', 'saji123@gmail.com', '$2y$10$.sZ9C2Vo3gcRhzM9PdCM5O80F66hItExYjhRI7iCX0/wFxy5BVzz6', '9400537470', 'user', '2025-08-31 08:41:35', 'What is your favorite childhood food?', '$2y$10$v/AmNpNFYtGGubNZgTJJ5ur0g.XzcP5vFbdUUcrNcot03LQki0btq'),
(22, 'Mekhna', 'mekhnajoby@gmail.com', '$2y$10$mzeK2LIO.7Fuz9ej/1LHK..X9iFr4DcKZqDx9citWiTkXv7fbTcfS', '8884834606', 'user', '2025-08-31 12:11:11', 'What is your first pet\'s name?', '$2y$10$zBxeJbUCDix1.emTVV1wjOgyAfrrkoIoxvshzDOd0RkGIdesw8d4a'),
(23, 'Sera Manoj', 'sera@gmail.com', '$2y$10$0/R.V4YL4m9GMq5d.jFMMu09GDhHeMYNpPnANsnxY2UcaAVQO7hIW', '8884834606', 'user', '2025-09-05 08:10:39', 'What is your favorite childhood food?', '$2y$10$B1l8SqpC6Yef01eBsYqO9Op.XNnVRf11bKivAKNXHU2r1vL4vaKgy');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `addresses`
--
ALTER TABLE `addresses`
  ADD PRIMARY KEY (`address_id`),
  ADD UNIQUE KEY `unique_address_type_per_user` (`user_id`,`address_type`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `auth_tokens`
--
ALTER TABLE `auth_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `selector` (`selector`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `deliveries`
--
ALTER TABLE `deliveries`
  ADD PRIMARY KEY (`delivery_id`);

--
-- Indexes for table `delivery_assignments`
--
ALTER TABLE `delivery_assignments`
  ADD PRIMARY KEY (`assignment_id`),
  ADD UNIQUE KEY `uq_subscription_meal_date` (`subscription_id`,`meal_type`,`delivery_date`),
  ADD KEY `idx_partner_status` (`partner_id`,`status`),
  ADD KEY `idx_delivery_date` (`delivery_date`),
  ADD KEY `idx_meal_id` (`meal_id`);

--
-- Indexes for table `delivery_issues`
--
ALTER TABLE `delivery_issues`
  ADD PRIMARY KEY (`issue_id`),
  ADD KEY `idx_partner_created` (`partner_id`,`created_at`);

--
-- Indexes for table `delivery_partner_details`
--
ALTER TABLE `delivery_partner_details`
  ADD PRIMARY KEY (`partner_id`);

--
-- Indexes for table `delivery_preferences`
--
ALTER TABLE `delivery_preferences`
  ADD PRIMARY KEY (`preference_id`),
  ADD UNIQUE KEY `ux_dp_user_meal` (`user_id`,`meal_type`),
  ADD KEY `idx_user_meal` (`user_id`,`meal_type`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`feedback_id`);

--
-- Indexes for table `inquiries`
--
ALTER TABLE `inquiries`
  ADD PRIMARY KEY (`inquiry_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `meal_categories`
--
ALTER TABLE `meal_categories`
  ADD KEY `idx_category_slot` (`slot`);

--
-- Indexes for table `meal_plans`
--
ALTER TABLE `meal_plans`
  ADD PRIMARY KEY (`plan_id`);

--
-- Indexes for table `partner_payments`
--
ALTER TABLE `partner_payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `idx_partner_payments_partner` (`partner_id`),
  ADD KEY `idx_partner_payments_created` (`created_at`),
  ADD KEY `idx_subscription_id` (`subscription_id`),
  ADD KEY `idx_partner_sub_date` (`partner_id`,`subscription_id`,`delivery_date`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `subscription_id` (`subscription_id`);

--
-- Indexes for table `plan_features`
--
ALTER TABLE `plan_features`
  ADD PRIMARY KEY (`feature_id`),
  ADD KEY `plan_id` (`plan_id`),
  ADD KEY `sort_order` (`sort_order`);

--
-- Indexes for table `plan_images`
--
ALTER TABLE `plan_images`
  ADD PRIMARY KEY (`image_id`),
  ADD KEY `plan_id` (`plan_id`),
  ADD KEY `sort_order` (`sort_order`);

--
-- Indexes for table `popular_meals`
--
ALTER TABLE `popular_meals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `meal_id` (`meal_id`);

--
-- Indexes for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD PRIMARY KEY (`subscription_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `plan_id` (`plan_id`),
  ADD KEY `idx_status_payment` (`status`,`payment_status`),
  ADD KEY `idx_user_active_window` (`user_id`,`start_date`,`end_date`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `addresses`
--
ALTER TABLE `addresses`
  MODIFY `address_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `auth_tokens`
--
ALTER TABLE `auth_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `deliveries`
--
ALTER TABLE `deliveries`
  MODIFY `delivery_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=409;

--
-- AUTO_INCREMENT for table `delivery_assignments`
--
ALTER TABLE `delivery_assignments`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=133;

--
-- AUTO_INCREMENT for table `delivery_issues`
--
ALTER TABLE `delivery_issues`
  MODIFY `issue_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `delivery_preferences`
--
ALTER TABLE `delivery_preferences`
  MODIFY `preference_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=91;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `feedback_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `inquiries`
--
ALTER TABLE `inquiries`
  MODIFY `inquiry_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `meal_plans`
--
ALTER TABLE `meal_plans`
  MODIFY `plan_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `partner_payments`
--
ALTER TABLE `partner_payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `plan_features`
--
ALTER TABLE `plan_features`
  MODIFY `feature_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `plan_images`
--
ALTER TABLE `plan_images`
  MODIFY `image_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `popular_meals`
--
ALTER TABLE `popular_meals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `subscription_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=87;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `auth_tokens`
--
ALTER TABLE `auth_tokens`
  ADD CONSTRAINT `auth_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `partner_payments`
--
ALTER TABLE `partner_payments`
  ADD CONSTRAINT `fk_partner_payments_partner` FOREIGN KEY (`partner_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`subscription_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
