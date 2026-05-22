-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 22, 2026 at 02:00 PM
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
-- Database: `afghan_oxygen`
--

-- --------------------------------------------------------

--
-- Table structure for table `cash_transactions`
--

CREATE TABLE `cash_transactions` (
  `id` int(11) NOT NULL,
  `category` varchar(100) NOT NULL DEFAULT 'General',
  `description` varchar(500) DEFAULT NULL,
  `inflow` decimal(12,2) NOT NULL DEFAULT 0.00,
  `outflow` decimal(12,2) NOT NULL DEFAULT 0.00,
  `transaction_date` date NOT NULL,
  `is_opening` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cash_transactions`
--

INSERT INTO `cash_transactions` (`id`, `category`, `description`, `inflow`, `outflow`, `transaction_date`, `is_opening`, `created_at`) VALUES
(10, 'Opening Balance', 'Opening Balance / Initial Setup', 1000.00, 0.00, '2026-05-22', 1, '2026-05-22 11:17:39');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `phone` varchar(30) NOT NULL,
  `address` text DEFAULT NULL,
  `current_cylinder_balance` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `name`, `phone`, `address`, `current_cylinder_balance`, `created_at`) VALUES
(28, 'Muhammad Zulkif', '45656', 'Karkhano Peshawar', 1, '2026-05-22 11:21:16'),
(29, 'amir', '324432', 'afsdsdfd', 1, '2026-05-22 11:29:11'),
(30, 'Jawad', '43224', '', 0, '2026-05-22 11:31:23'),
(31, 'haris', 'w523352523', 'w', 0, '2026-05-22 11:39:50'),
(32, 'haroon', '32432234', '', 0, '2026-05-22 11:42:15'),
(33, 'Umar', '432444', '', 1, '2026-05-22 11:53:50');

-- --------------------------------------------------------

--
-- Table structure for table `customer_cylinder_balance`
--

CREATE TABLE `customer_cylinder_balance` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `cylinder_size` enum('Small','Medium','Large') NOT NULL,
  `balance_qty` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customer_cylinder_balance`
--

INSERT INTO `customer_cylinder_balance` (`id`, `customer_id`, `cylinder_size`, `balance_qty`, `created_at`, `updated_at`) VALUES
(45, 28, 'Small', 1, '2026-05-22 11:22:07', '2026-05-22 11:22:07'),
(46, 29, 'Small', 1, '2026-05-22 11:29:27', '2026-05-22 11:29:27'),
(47, 30, 'Small', 0, '2026-05-22 11:32:00', '2026-05-22 11:32:00'),
(48, 33, 'Small', 1, '2026-05-22 11:56:22', '2026-05-22 11:56:22');

-- --------------------------------------------------------

--
-- Table structure for table `cylinders`
--

CREATE TABLE `cylinders` (
  `id` int(11) NOT NULL,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `available` decimal(12,2) NOT NULL DEFAULT 0.00,
  `issued` int(11) NOT NULL DEFAULT 0,
  `empty` int(11) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cylinders`
--

INSERT INTO `cylinders` (`id`, `total`, `available`, `issued`, `empty`, `updated_at`) VALUES
(23, 15.00, 7.00, 4, 8, '2026-05-22 11:56:23');

-- --------------------------------------------------------

--
-- Table structure for table `cylinder_stock_by_type`
--

CREATE TABLE `cylinder_stock_by_type` (
  `id` int(11) NOT NULL,
  `cylinder_type` enum('Small','Medium','Large') NOT NULL,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `available` decimal(12,2) NOT NULL DEFAULT 0.00,
  `daka_qty` int(11) NOT NULL DEFAULT 0,
  `tash_qty` int(11) NOT NULL DEFAULT 0,
  `available_pressure` decimal(14,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cylinder_stock_by_type`
--

INSERT INTO `cylinder_stock_by_type` (`id`, `cylinder_type`, `total`, `available`, `daka_qty`, `tash_qty`, `available_pressure`, `created_at`, `updated_at`) VALUES
(3022, 'Small', 12.00, 7.00, 7, 8, 0.00, '2026-05-22 11:12:48', '2026-05-22 11:56:22');

-- --------------------------------------------------------

--
-- Table structure for table `employee_salaries`
--

CREATE TABLE `employee_salaries` (
  `id` int(11) NOT NULL,
  `employee_name` varchar(200) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `salary_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `employee_salaries`
--

INSERT INTO `employee_salaries` (`id`, `employee_name`, `amount`, `salary_date`, `notes`, `created_at`) VALUES
(4, 'Kamran', 100.00, '2026-05-22', NULL, '2026-05-22 11:26:03');

-- --------------------------------------------------------

--
-- Table structure for table `financial_month_closures`
--

CREATE TABLE `financial_month_closures` (
  `id` int(11) NOT NULL,
  `month_key` char(7) NOT NULL,
  `closed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `closed_by_user_id` int(11) DEFAULT NULL,
  `snapshot_json` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `general_expenses`
--

CREATE TABLE `general_expenses` (
  `id` int(11) NOT NULL,
  `description` varchar(500) NOT NULL,
  `category` varchar(100) NOT NULL DEFAULT 'General',
  `amount` decimal(12,2) NOT NULL,
  `expense_date` date NOT NULL,
  `payment_type` enum('Cash','Bank') NOT NULL DEFAULT 'Cash',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `general_expenses`
--

INSERT INTO `general_expenses` (`id`, `description`, `category`, `amount`, `expense_date`, `payment_type`, `notes`, `created_at`) VALUES
(3, 'Tea', 'General', 100.00, '2026-05-22', 'Cash', NULL, '2026-05-22 11:25:41');

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `service_id` int(11) DEFAULT NULL,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `remaining_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('Pending','Paid') NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `invoices`
--

INSERT INTO `invoices` (`id`, `customer_id`, `service_id`, `total_amount`, `paid_amount`, `remaining_amount`, `status`, `created_at`) VALUES
(38, 28, 38, 400.00, 400.00, 0.00, 'Paid', '2026-05-22 11:22:07'),
(39, 29, 39, 100.00, 100.00, 0.00, 'Paid', '2026-05-22 11:29:27'),
(40, 30, 40, 100.00, 100.00, 0.00, 'Paid', '2026-05-22 11:32:00'),
(41, 33, 41, 100.00, 100.00, 0.00, 'Paid', '2026-05-22 11:56:22');

-- --------------------------------------------------------

--
-- Table structure for table `ledger`
--

CREATE TABLE `ledger` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `debit` decimal(12,2) NOT NULL DEFAULT 0.00,
  `credit` decimal(12,2) NOT NULL DEFAULT 0.00,
  `balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `date` date NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `cylinders_sent` int(11) NOT NULL DEFAULT 0,
  `cylinders_received` int(11) NOT NULL DEFAULT 0,
  `cylinders_baqi` int(11) NOT NULL DEFAULT 0,
  `reference_type` varchar(40) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ledger`
--

INSERT INTO `ledger` (`id`, `customer_id`, `debit`, `credit`, `balance`, `date`, `description`, `cylinders_sent`, `cylinders_received`, `cylinders_baqi`, `reference_type`, `reference_id`, `created_at`) VALUES
(99, 28, 200.00, 0.00, 200.00, '2026-05-22', 'Opening balance owed / Initial setup', 0, 0, 2, 'opening_balance', 28, '2026-05-22 11:21:16'),
(100, 28, 200.00, 0.00, 400.00, '2026-05-22', 'Cylinder exchange & gas refill order', 1, 2, -1, 'service', 38, '2026-05-22 11:22:07'),
(101, 28, 0.00, 200.00, 200.00, '2026-05-22', 'Payment for Order #38 - Cylinder exchange & gas refill order', 0, 0, 0, 'service', 38, '2026-05-22 11:22:07'),
(102, 28, 0.00, 200.00, 0.00, '2026-05-22', 'Payment received for INV-38', 0, 0, 0, 'payment', 59, '2026-05-22 11:22:35'),
(103, 28, 0.00, 0.00, 0.00, '2026-05-22', 'Cylinder return received', 0, 2, 0, 'cylinder_settlement', 0, '2026-05-22 11:23:23'),
(104, 29, 100.00, 0.00, 100.00, '2026-05-22', 'Cylinder exchange & gas refill order', 1, 0, 1, 'service', 39, '2026-05-22 11:29:27'),
(105, 29, 0.00, 100.00, 0.00, '2026-05-22', 'Payment for Order #39 - Cylinder exchange & gas refill order', 0, 0, 0, 'service', 39, '2026-05-22 11:29:27'),
(106, 30, 100.00, 0.00, 100.00, '2026-05-22', 'Cylinder exchange & gas refill order', 1, 1, 0, 'service', 40, '2026-05-22 11:32:00'),
(107, 30, 0.00, 100.00, 0.00, '2026-05-22', 'Payment for Order #40 - Cylinder exchange & gas refill order', 0, 0, 0, 'service', 40, '2026-05-22 11:32:00'),
(108, 31, 10000.00, 0.00, 10000.00, '2026-05-22', 'Opening balance owed / Initial setup', 0, 0, 1, 'opening_balance', 31, '2026-05-22 11:39:50'),
(109, 31, 0.00, 1000.00, 9000.00, '2026-05-22', 'Payment received (opening / account balance)', 0, 0, 0, 'opening_payment', 31, '2026-05-22 11:40:47'),
(110, 31, 0.00, 9000.00, 0.00, '2026-05-22', 'Payment received (opening / account balance)', 0, 0, 0, 'opening_payment', 31, '2026-05-22 11:41:03'),
(111, 31, 0.00, 0.00, 0.00, '2026-05-22', 'Cylinder return received', 0, 1, 0, 'cylinder_settlement', 0, '2026-05-22 11:41:05'),
(112, 32, 500.00, 0.00, 500.00, '2026-05-22', 'Opening balance owed / Initial setup', 0, 0, 1, 'opening_balance', 32, '2026-05-22 11:42:15'),
(113, 32, 0.00, 500.00, 0.00, '2026-05-22', 'Payment received (opening / account balance)', 0, 0, 0, 'opening_payment', 32, '2026-05-22 11:52:21'),
(114, 32, 0.00, 0.00, 0.00, '2026-05-22', 'Cylinder return received', 0, 1, 0, 'cylinder_settlement', 0, '2026-05-22 11:52:25'),
(115, 33, 500.00, 0.00, 500.00, '2026-05-22', 'Opening balance owed / Initial setup', 0, 0, 1, 'opening_balance', 33, '2026-05-22 11:53:50'),
(116, 33, 0.00, 500.00, 0.00, '2026-05-22', 'Payment received (opening / account balance)', 0, 0, 0, 'opening_payment', 33, '2026-05-22 11:55:29'),
(117, 33, 0.00, 0.00, 0.00, '2026-05-22', 'Cylinder return received', 0, 1, 0, 'cylinder_settlement', 0, '2026-05-22 11:55:36'),
(118, 33, 100.00, 0.00, 100.00, '2026-05-22', 'Cylinder exchange & gas refill order', 1, 0, 1, 'service', 41, '2026-05-22 11:56:22'),
(119, 33, 0.00, 100.00, 0.00, '2026-05-22', 'Payment for Order #41 - Cylinder exchange & gas refill order', 0, 0, 0, 'service', 41, '2026-05-22 11:56:22');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `invoice_id`, `amount`, `payment_date`, `created_at`) VALUES
(58, 38, 200.00, '2026-05-22', '2026-05-22 11:22:07'),
(59, 38, 200.00, '2026-05-22', '2026-05-22 11:22:35'),
(60, 39, 100.00, '2026-05-22', '2026-05-22 11:29:27'),
(61, 40, 100.00, '2026-05-22', '2026-05-22 11:32:00'),
(62, 41, 100.00, '2026-05-22', '2026-05-22 11:56:22');

-- --------------------------------------------------------

--
-- Table structure for table `refill_discrepancy`
--

CREATE TABLE `refill_discrepancy` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `sent_quantity` int(11) NOT NULL DEFAULT 0,
  `received_fully` int(11) NOT NULL DEFAULT 0,
  `difference_quantity` int(11) NOT NULL DEFAULT 0,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `refill_discrepancy`
--

INSERT INTO `refill_discrepancy` (`id`, `supplier_id`, `transaction_id`, `sent_quantity`, `received_fully`, `difference_quantity`, `notes`, `created_at`) VALUES
(51, 32, 77, 2, 1, 1, 'Dispatch vs receipt cylinder count', '2026-05-22 11:19:34');

-- --------------------------------------------------------

--
-- Table structure for table `reminders`
--

CREATE TABLE `reminders` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `channel` enum('whatsapp') NOT NULL DEFAULT 'whatsapp',
  `message` text NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `service_type` enum('rental','refill','delivery','wholesale') NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_bill` decimal(12,2) NOT NULL DEFAULT 0.00,
  `service_charges` decimal(12,2) NOT NULL DEFAULT 0.00,
  `previous_balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `remaining_balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `notes` varchar(255) DEFAULT NULL,
  `date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `customer_id`, `service_type`, `quantity`, `price`, `total_bill`, `service_charges`, `previous_balance`, `grand_total`, `paid_amount`, `remaining_balance`, `notes`, `date`, `created_at`) VALUES
(38, 28, 'refill', 1, 200.00, 200.00, 0.00, 200.00, 400.00, 400.00, 0.00, 'Cylinder exchange & gas refill order', '2026-05-22', '2026-05-22 11:22:07'),
(39, 29, 'refill', 1, 100.00, 100.00, 0.00, 0.00, 100.00, 100.00, 0.00, 'Cylinder exchange & gas refill order', '2026-05-22', '2026-05-22 11:29:27'),
(40, 30, 'refill', 1, 100.00, 100.00, 0.00, 0.00, 100.00, 100.00, 0.00, 'Cylinder exchange & gas refill order', '2026-05-22', '2026-05-22 11:32:00'),
(41, 33, 'refill', 1, 100.00, 100.00, 0.00, 0.00, 100.00, 100.00, 0.00, 'Cylinder exchange & gas refill order', '2026-05-22', '2026-05-22 11:56:22');

-- --------------------------------------------------------

--
-- Table structure for table `service_cylinder_rows`
--

CREATE TABLE `service_cylinder_rows` (
  `id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `cylinder_size` enum('Small','Medium','Large') NOT NULL,
  `sent_qty` int(11) NOT NULL DEFAULT 0,
  `received_qty` int(11) NOT NULL DEFAULT 0,
  `sale_units` int(11) NOT NULL DEFAULT 0,
  `baqi_qty` int(11) NOT NULL DEFAULT 0,
  `rate` decimal(12,2) NOT NULL DEFAULT 0.00,
  `billing_basis` enum('quantity','psi') NOT NULL DEFAULT 'quantity',
  `sale_pressure` decimal(12,2) NOT NULL DEFAULT 0.00,
  `sold_pressure_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `service_cylinder_rows`
--

INSERT INTO `service_cylinder_rows` (`id`, `service_id`, `cylinder_size`, `sent_qty`, `received_qty`, `sale_units`, `baqi_qty`, `rate`, `billing_basis`, `sale_pressure`, `sold_pressure_total`, `total_amount`, `created_at`) VALUES
(37, 38, 'Small', 1, 2, 1, 1, 200.00, 'quantity', 0.00, 0.00, 200.00, '2026-05-22 11:22:07'),
(38, 39, 'Small', 1, 0, 1, 1, 100.00, 'quantity', 0.00, 0.00, 100.00, '2026-05-22 11:29:27'),
(39, 40, 'Small', 1, 1, 1, 0, 100.00, 'quantity', 0.00, 0.00, 100.00, '2026-05-22 11:32:00'),
(40, 41, 'Small', 1, 0, 1, 1, 100.00, 'quantity', 0.00, 0.00, 100.00, '2026-05-22 11:56:22');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `company_name` varchar(150) NOT NULL,
  `company_phone` varchar(30) DEFAULT NULL,
  `company_email` varchar(150) DEFAULT NULL,
  `company_address` text DEFAULT NULL,
  `oxygen_std_pressure_small` decimal(12,2) NOT NULL DEFAULT 0.00,
  `oxygen_std_pressure_medium` decimal(12,2) NOT NULL DEFAULT 0.00,
  `oxygen_std_pressure_large` decimal(12,2) NOT NULL DEFAULT 0.00,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `company_name`, `company_phone`, `company_email`, `company_address`, `oxygen_std_pressure_small`, `oxygen_std_pressure_medium`, `oxygen_std_pressure_large`, `updated_at`) VALUES
(1, 'Afghan Oxygen Supply', NULL, NULL, NULL, 1000.00, 2000.00, 3000.00, '2026-05-04 14:20:08');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `name` varchar(140) NOT NULL,
  `contact_person` varchar(120) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `opening_balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `name`, `contact_person`, `phone`, `email`, `address`, `opening_balance`, `created_at`) VALUES
(32, 'ASA oxygen limited', 'asif', '5678y78', NULL, '', 100.00, '2026-05-22 11:18:55');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_ledger`
--

CREATE TABLE `supplier_ledger` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `debit` decimal(12,2) NOT NULL DEFAULT 0.00,
  `credit` decimal(12,2) NOT NULL DEFAULT 0.00,
  `balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `reference_type` varchar(40) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `description` varchar(2000) DEFAULT NULL,
  `entry_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `supplier_ledger`
--

INSERT INTO `supplier_ledger` (`id`, `supplier_id`, `debit`, `credit`, `balance`, `reference_type`, `reference_id`, `description`, `entry_date`, `created_at`) VALUES
(272, 32, 100.00, 0.00, 100.00, 'opening_balance', 32, 'Opening balance owed (pending liability)', '2026-05-22', '2026-05-22 11:20:33'),
(273, 32, 200.00, 0.00, 300.00, 'purchase', 77, 'oxygen refile', '2026-05-22', '2026-05-22 11:20:33'),
(274, 32, 0.00, 200.00, 100.00, 'payment', 99, 'Installment #SP-77 (Cash) —  200.00 toward refill purchase', '2026-05-22', '2026-05-22 11:20:33'),
(275, 32, 0.00, 100.00, 0.00, 'payment', 100, 'Payment toward opening balance —  100.00 (Cash)', '2026-05-22', '2026-05-22 11:20:33');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_payments`
--

CREATE TABLE `supplier_payments` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_type` enum('Cash','Bank','Credit') NOT NULL DEFAULT 'Cash',
  `payment_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `supplier_payments`
--

INSERT INTO `supplier_payments` (`id`, `supplier_id`, `transaction_id`, `amount`, `payment_type`, `payment_date`, `created_at`) VALUES
(99, 32, 77, 200.00, 'Cash', '2026-05-22', '2026-05-22 11:19:34'),
(100, 32, NULL, 100.00, 'Cash', '2026-05-22', '2026-05-22 11:20:33');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_pending_cylinders`
--

CREATE TABLE `supplier_pending_cylinders` (
  `supplier_id` int(11) NOT NULL,
  `cylinder_type` enum('Small','Medium','Large') NOT NULL,
  `pending_count` int(11) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `supplier_pending_cylinders`
--

INSERT INTO `supplier_pending_cylinders` (`supplier_id`, `cylinder_type`, `pending_count`, `updated_at`) VALUES
(32, 'Small', 1, '2026-05-22 11:19:34');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_refill_breakdown`
--

CREATE TABLE `supplier_refill_breakdown` (
  `id` int(11) NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `refill_cylinder_type` enum('Small','Medium','Large') DEFAULT NULL,
  `status_label` varchar(50) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `pressure_received` decimal(12,2) DEFAULT NULL,
  `line_unit_price` decimal(12,2) DEFAULT NULL,
  `inventory_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `supplier_refill_breakdown`
--

INSERT INTO `supplier_refill_breakdown` (`id`, `transaction_id`, `refill_cylinder_type`, `status_label`, `quantity`, `pressure_received`, `line_unit_price`, `inventory_qty`, `created_at`) VALUES
(95, 77, 'Small', 'Refill Receipt', 1, NULL, 200.00, 1.00, '2026-05-22 11:19:34');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_transactions`
--

CREATE TABLE `supplier_transactions` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `sent_quantity` int(11) NOT NULL DEFAULT 0,
  `date_sent` date DEFAULT NULL,
  `sent_pressure` decimal(12,2) DEFAULT NULL,
  `sent_qty_small` int(11) NOT NULL DEFAULT 0,
  `sent_qty_medium` int(11) NOT NULL DEFAULT 0,
  `sent_qty_large` int(11) NOT NULL DEFAULT 0,
  `sent_pressure_small` decimal(12,2) DEFAULT NULL,
  `sent_pressure_medium` decimal(12,2) DEFAULT NULL,
  `sent_pressure_large` decimal(12,2) DEFAULT NULL,
  `cylinder_type` enum('Small','Medium','Large','Mixed') NOT NULL DEFAULT 'Small',
  `quantity` int(11) NOT NULL DEFAULT 0,
  `total_received` int(11) NOT NULL DEFAULT 0,
  `inventory_quantity` decimal(12,2) NOT NULL DEFAULT 0.00,
  `received_fully_quantity` int(11) NOT NULL DEFAULT 0,
  `received_pressure_total` decimal(14,2) NOT NULL DEFAULT 0.00,
  `unit_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `remaining_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_type` enum('Cash','Bank','Credit') NOT NULL DEFAULT 'Credit',
  `payment_status` enum('PAID','PARTIAL','DUE') NOT NULL DEFAULT 'DUE',
  `notes` text DEFAULT NULL,
  `transaction_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `supplier_transactions`
--

INSERT INTO `supplier_transactions` (`id`, `supplier_id`, `sent_quantity`, `date_sent`, `sent_pressure`, `sent_qty_small`, `sent_qty_medium`, `sent_qty_large`, `sent_pressure_small`, `sent_pressure_medium`, `sent_pressure_large`, `cylinder_type`, `quantity`, `total_received`, `inventory_quantity`, `received_fully_quantity`, `received_pressure_total`, `unit_price`, `total_amount`, `paid_amount`, `remaining_amount`, `payment_type`, `payment_status`, `notes`, `transaction_date`, `created_at`) VALUES
(77, 32, 2, '2026-05-22', NULL, 2, 0, 0, NULL, NULL, NULL, 'Small', 1, 1, 1.00, 1, 0.00, 200.00, 200.00, 200.00, 0.00, 'Cash', 'PAID', 'oxygen refile', '2026-05-22', '2026-05-22 11:19:34');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `username` varchar(60) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin') NOT NULL DEFAULT 'admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `username`, `email`, `password_hash`, `role`, `created_at`) VALUES
(1, 'Haris', 'haris', 'haris@gmail.com', '$2y$10$DkEE0f9.51JXhf6XQSKe6OKmU.i4NG9KlYCk2eKk7ZGw4gtyYWDhC', 'admin', '2026-05-03 06:45:11');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cash_transactions`
--
ALTER TABLE `cash_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cash_tx_date` (`transaction_date`),
  ADD KEY `idx_cash_opening` (`is_opening`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customer_cylinder_balance`
--
ALTER TABLE `customer_cylinder_balance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_customer_size` (`customer_id`,`cylinder_size`);

--
-- Indexes for table `cylinders`
--
ALTER TABLE `cylinders`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cylinder_stock_by_type`
--
ALTER TABLE `cylinder_stock_by_type`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cylinder_type` (`cylinder_type`);

--
-- Indexes for table `employee_salaries`
--
ALTER TABLE `employee_salaries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_salary_date` (`salary_date`);

--
-- Indexes for table `financial_month_closures`
--
ALTER TABLE `financial_month_closures`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_fin_month_key` (`month_key`);

--
-- Indexes for table `general_expenses`
--
ALTER TABLE `general_expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_expense_date` (`expense_date`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_invoices_customer_status` (`customer_id`,`status`);

--
-- Indexes for table `ledger`
--
ALTER TABLE `ledger`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ledger_customer_date` (`customer_id`,`date`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_payments_invoice_date` (`invoice_id`,`payment_date`);

--
-- Indexes for table `refill_discrepancy`
--
ALTER TABLE `refill_discrepancy`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_refill_discrepancy_supplier` (`supplier_id`),
  ADD KEY `fk_refill_discrepancy_tx` (`transaction_id`);

--
-- Indexes for table `reminders`
--
ALTER TABLE `reminders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_reminders_customer` (`customer_id`),
  ADD KEY `idx_reminders_invoice_sent` (`invoice_id`,`sent_at`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_services_customer_date` (`customer_id`,`date`);

--
-- Indexes for table `service_cylinder_rows`
--
ALTER TABLE `service_cylinder_rows`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_service_cylinder_rows_service` (`service_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `supplier_ledger`
--
ALTER TABLE `supplier_ledger`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_supplier_ledger_supplier` (`supplier_id`);

--
-- Indexes for table `supplier_payments`
--
ALTER TABLE `supplier_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_supplier_pay_supplier` (`supplier_id`);

--
-- Indexes for table `supplier_pending_cylinders`
--
ALTER TABLE `supplier_pending_cylinders`
  ADD PRIMARY KEY (`supplier_id`,`cylinder_type`);

--
-- Indexes for table `supplier_refill_breakdown`
--
ALTER TABLE `supplier_refill_breakdown`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_supplier_refill_tx` (`transaction_id`);

--
-- Indexes for table `supplier_transactions`
--
ALTER TABLE `supplier_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_supplier_tx_supplier` (`supplier_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cash_transactions`
--
ALTER TABLE `cash_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `customer_cylinder_balance`
--
ALTER TABLE `customer_cylinder_balance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `cylinders`
--
ALTER TABLE `cylinders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `cylinder_stock_by_type`
--
ALTER TABLE `cylinder_stock_by_type`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3129;

--
-- AUTO_INCREMENT for table `employee_salaries`
--
ALTER TABLE `employee_salaries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `financial_month_closures`
--
ALTER TABLE `financial_month_closures`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `general_expenses`
--
ALTER TABLE `general_expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `ledger`
--
ALTER TABLE `ledger`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=120;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT for table `refill_discrepancy`
--
ALTER TABLE `refill_discrepancy`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `reminders`
--
ALTER TABLE `reminders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `service_cylinder_rows`
--
ALTER TABLE `service_cylinder_rows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `supplier_ledger`
--
ALTER TABLE `supplier_ledger`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=276;

--
-- AUTO_INCREMENT for table `supplier_payments`
--
ALTER TABLE `supplier_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=101;

--
-- AUTO_INCREMENT for table `supplier_refill_breakdown`
--
ALTER TABLE `supplier_refill_breakdown`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=96;

--
-- AUTO_INCREMENT for table `supplier_transactions`
--
ALTER TABLE `supplier_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=78;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `customer_cylinder_balance`
--
ALTER TABLE `customer_cylinder_balance`
  ADD CONSTRAINT `fk_customer_cylinder_balance_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `fk_invoices_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `ledger`
--
ALTER TABLE `ledger`
  ADD CONSTRAINT `fk_ledger_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payments_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `refill_discrepancy`
--
ALTER TABLE `refill_discrepancy`
  ADD CONSTRAINT `fk_refill_discrepancy_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_refill_discrepancy_tx` FOREIGN KEY (`transaction_id`) REFERENCES `supplier_transactions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `reminders`
--
ALTER TABLE `reminders`
  ADD CONSTRAINT `fk_reminders_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_reminders_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `services`
--
ALTER TABLE `services`
  ADD CONSTRAINT `fk_services_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `service_cylinder_rows`
--
ALTER TABLE `service_cylinder_rows`
  ADD CONSTRAINT `fk_service_cylinder_rows_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `supplier_ledger`
--
ALTER TABLE `supplier_ledger`
  ADD CONSTRAINT `fk_supplier_ledger_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `supplier_payments`
--
ALTER TABLE `supplier_payments`
  ADD CONSTRAINT `fk_supplier_pay_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `supplier_pending_cylinders`
--
ALTER TABLE `supplier_pending_cylinders`
  ADD CONSTRAINT `fk_supplier_pending_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `supplier_refill_breakdown`
--
ALTER TABLE `supplier_refill_breakdown`
  ADD CONSTRAINT `fk_supplier_refill_tx` FOREIGN KEY (`transaction_id`) REFERENCES `supplier_transactions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `supplier_transactions`
--
ALTER TABLE `supplier_transactions`
  ADD CONSTRAINT `fk_supplier_tx_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
