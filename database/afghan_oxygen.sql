-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 06, 2026 at 03:51 PM
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
(6, 'Hamza', '023423423', '', 1, '2026-05-03 06:15:47'),
(8, 'Arif', '0234234324', '', 0, '2026-05-04 09:50:22'),
(9, 'Imtiaz Khan', '', 'Karkhano Peshawar', 1, '2026-05-04 14:22:28'),
(10, 'Kamran Khan', '043432343243', 'Karkhano Peshawar lahore', 0, '2026-05-05 11:53:43'),
(11, 'Waris', '023432432', 'addresssss yeh ian', 0, '2026-05-05 11:56:09');

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
(17, 6, 'Small', 1, '2026-05-04 13:37:39', '2026-05-04 13:37:39'),
(18, 9, 'Small', 1, '2026-05-04 14:23:21', '2026-05-04 14:23:21');

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
(8, 18.00, 15.00, 3, 1, '2026-05-04 14:23:21');

-- --------------------------------------------------------

--
-- Table structure for table `cylinder_stock_by_type`
--

CREATE TABLE `cylinder_stock_by_type` (
  `id` int(11) NOT NULL,
  `cylinder_type` enum('Small','Medium','Large') NOT NULL,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `available` decimal(12,2) NOT NULL DEFAULT 0.00,
  `available_pressure` decimal(14,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cylinder_stock_by_type`
--

INSERT INTO `cylinder_stock_by_type` (`id`, `cylinder_type`, `total`, `available`, `available_pressure`, `created_at`, `updated_at`) VALUES
(1138, 'Small', 7.00, 4.00, 22000.00, '2026-05-04 12:03:43', '2026-05-04 14:23:21'),
(1139, 'Medium', 6.00, 6.00, 42000.00, '2026-05-04 12:03:43', '2026-05-04 12:56:46'),
(1140, 'Large', 5.00, 5.00, 60000.00, '2026-05-04 12:03:43', '2026-05-04 12:32:12');

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
(10, 6, 10, 1000.00, 1000.00, 0.00, 'Paid', '2026-05-04 13:37:39'),
(11, 9, 11, 8000.00, 8000.00, 0.00, 'Paid', '2026-05-04 14:23:21');

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
(17, 6, 1000.00, 0.00, 1000.00, '2026-05-04', 'Cylinder exchange & gas refill order', 2, 1, 1, 'service', 10, '2026-05-04 13:37:39'),
(18, 6, 0.00, 800.00, 200.00, '2026-05-04', 'Payment for Order #10', 0, 0, 0, 'service', 10, '2026-05-04 13:37:39'),
(19, 9, 8000.00, 0.00, 8000.00, '2026-05-04', 'Cylinder exchange & gas refill order', 1, 0, 1, 'service', 11, '2026-05-04 14:23:21'),
(20, 9, 0.00, 8000.00, 0.00, '2026-05-04', 'Payment for Order #11', 0, 0, 0, 'service', 11, '2026-05-04 14:23:21');

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
(12, 10, 800.00, '2026-05-04', '2026-05-04 13:37:39'),
(13, 10, 200.00, '2026-05-04', '2026-05-04 13:39:09'),
(14, 11, 8000.00, '2026-05-04', '2026-05-04 14:23:21');

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
(10, 14, 17, 14, 15, -1, 'Dispatch vs receipt cylinder count', '2026-05-04 12:32:12');

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
(10, 6, 'refill', 2, 1000.00, 1000.00, 0.00, 0.00, 1000.00, 1000.00, 0.00, 'Cylinder exchange & gas refill order', '2026-05-04', '2026-05-04 13:37:39'),
(11, 9, 'refill', 1, 8000.00, 8000.00, 0.00, 0.00, 8000.00, 8000.00, 0.00, 'Cylinder exchange & gas refill order', '2026-05-04', '2026-05-04 14:23:21');

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
(9, 10, 'Small', 2, 1, 2, 1, 500.00, 'quantity', 1000.00, 2000.00, 1000.00, '2026-05-04 13:37:39'),
(10, 11, 'Small', 1, 0, 1, 1, 8000.00, 'quantity', 3000.00, 3000.00, 8000.00, '2026-05-04 14:23:21');

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
(14, 'Lahore Oxygen Limited', 'Zeeshan', '2343242', NULL, '', 0.00, '2026-05-04 11:19:34'),
(15, 'Afghan limited', 'amjid', '3243244', NULL, '', 0.00, '2026-05-04 12:55:44');

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
(95, 14, 42499.90, 0.00, 42499.90, 'purchase', 17, 'Purchase #SP-17 — Sent: Small (Qty: 5) | Medium (Qty: 5) | Large (Qty: 4) — Received: Small (Qty: 5 @ 5000 PSI @  1,000.00/u) | Medium (Qty: 5 @ 8000 PSI @  2,499.98/u) | Large (Qty: 5 @ 12000 PSI @  5,000.00/u) — Avg unit:  2,833.33', '2026-05-04', '2026-05-04 12:32:12'),
(96, 14, 0.00, 20000.00, 22499.90, 'payment', 17, 'Installment #SP-17 (Cash) —  20,000.00 toward refill purchase', '2026-05-04', '2026-05-04 12:32:12'),
(97, 14, 2999.99, 0.00, 25499.89, 'purchase', 18, 'Purchase #SP-18 — Sent: Small (Qty: 1) | Medium (Qty: 1) — Received: Small (Qty: 1 @ 1000 PSI @  1,000.00/u) | Medium (Qty: 1 @ 2000 PSI @  1,999.99/u) — Avg unit:  1,500.00', '2026-05-04', '2026-05-04 12:56:46'),
(98, 14, 0.00, 499.98, 24999.91, 'payment', 18, 'Installment #SP-18 (Cash) —  499.98 toward refill purchase', '2026-05-04', '2026-05-04 12:56:46'),
(99, 14, 0.00, 100.00, 24899.91, 'payment', 18, 'Payment received via ledger view', '2026-05-04', '2026-05-04 13:12:13'),
(100, 14, 0.00, 100.00, 24799.91, 'payment', 18, 'Payment received via ledger view', '2026-05-04', '2026-05-04 13:13:39'),
(101, 14, 0.00, 200.00, 24599.91, 'payment', 18, 'Payment received via ledger view', '2026-05-05', '2026-05-04 13:14:04'),
(102, 14, 0.00, 2100.00, 22499.91, 'payment', 18, 'Payment received via ledger view', '2026-05-07', '2026-05-04 13:19:32'),
(103, 14, 0.00, 0.01, 22499.90, 'payment', 18, 'Payment received via ledger view', '2026-05-08', '2026-05-04 13:19:50'),
(104, 15, 2000.00, 0.00, 2000.00, 'purchase', 19, 'Purchase #SP-19 — Sent: Small (Qty: 1 @ 100 PSI) — Received: Small (Qty: 1 @ 1000 PSI @  2,000.00/u) — Avg unit:  2,000.00', '2026-05-04', '2026-05-04 13:27:55'),
(105, 15, 0.00, 500.00, 1500.00, 'payment', 19, 'Installment #SP-19 (Cash) —  500.00 toward refill purchase', '2026-05-04', '2026-05-04 13:27:55'),
(106, 15, 0.00, 1500.00, 0.00, 'payment', 19, 'Payment received via ledger view', '2026-05-08', '2026-05-04 13:28:26');

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
(26, 14, 17, 20000.00, 'Cash', '2026-05-04', '2026-05-04 12:32:12'),
(27, 14, 18, 499.98, 'Cash', '2026-05-04', '2026-05-04 12:56:46'),
(28, 14, 18, 100.00, 'Cash', '2026-05-04', '2026-05-04 13:12:13'),
(29, 14, 18, 100.00, 'Cash', '2026-05-04', '2026-05-04 13:13:39'),
(30, 14, 18, 200.00, 'Cash', '2026-05-05', '2026-05-04 13:14:04'),
(31, 14, 18, 2100.00, 'Cash', '2026-05-07', '2026-05-04 13:19:32'),
(32, 14, 18, 0.01, 'Cash', '2026-05-08', '2026-05-04 13:19:50'),
(33, 15, 19, 500.00, 'Cash', '2026-05-04', '2026-05-04 13:27:55'),
(34, 15, 19, 1500.00, 'Cash', '2026-05-08', '2026-05-04 13:28:26');

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
(14, 'Large', -1, '2026-05-04 12:32:12');

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
(29, 17, 'Small', 'Refill Receipt', 5, 5000.00, 1000.00, 5.00, '2026-05-04 12:32:12'),
(30, 17, 'Medium', 'Refill Receipt', 5, 8000.00, 2499.98, 5.00, '2026-05-04 12:32:12'),
(31, 17, 'Large', 'Refill Receipt', 5, 12000.00, 5000.00, 5.00, '2026-05-04 12:32:12'),
(32, 18, 'Small', 'Refill Receipt', 1, 1000.00, 1000.00, 1.00, '2026-05-04 12:56:46'),
(33, 18, 'Medium', 'Refill Receipt', 1, 2000.00, 1999.99, 1.00, '2026-05-04 12:56:46'),
(34, 19, 'Small', 'Refill Receipt', 1, 1000.00, 2000.00, 1.00, '2026-05-04 13:27:55');

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
  `transaction_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `supplier_transactions`
--

INSERT INTO `supplier_transactions` (`id`, `supplier_id`, `sent_quantity`, `date_sent`, `sent_pressure`, `sent_qty_small`, `sent_qty_medium`, `sent_qty_large`, `sent_pressure_small`, `sent_pressure_medium`, `sent_pressure_large`, `cylinder_type`, `quantity`, `total_received`, `inventory_quantity`, `received_fully_quantity`, `received_pressure_total`, `unit_price`, `total_amount`, `paid_amount`, `remaining_amount`, `payment_type`, `payment_status`, `transaction_date`, `created_at`) VALUES
(17, 14, 14, '2026-05-04', NULL, 5, 5, 4, NULL, NULL, NULL, 'Mixed', 15, 15, 15.00, 15, 125000.00, 2833.33, 42499.90, 20000.00, 22499.90, 'Cash', 'PARTIAL', '2026-05-04', '2026-05-04 12:32:12'),
(18, 14, 2, '2026-05-04', NULL, 1, 1, 0, NULL, NULL, NULL, 'Mixed', 2, 2, 2.00, 2, 3000.00, 1500.00, 2999.99, 2999.99, 0.00, 'Cash', 'PAID', '2026-05-04', '2026-05-04 12:56:46'),
(19, 15, 1, '2026-05-04', 100.00, 1, 0, 0, 100.00, NULL, NULL, 'Small', 1, 1, 1.00, 1, 1000.00, 2000.00, 2000.00, 2000.00, 0.00, 'Cash', 'PAID', '2026-05-04', '2026-05-04 13:27:55');

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
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `customer_cylinder_balance`
--
ALTER TABLE `customer_cylinder_balance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `cylinders`
--
ALTER TABLE `cylinders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `cylinder_stock_by_type`
--
ALTER TABLE `cylinder_stock_by_type`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1489;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `ledger`
--
ALTER TABLE `ledger`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `refill_discrepancy`
--
ALTER TABLE `refill_discrepancy`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `reminders`
--
ALTER TABLE `reminders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `service_cylinder_rows`
--
ALTER TABLE `service_cylinder_rows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `supplier_ledger`
--
ALTER TABLE `supplier_ledger`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=107;

--
-- AUTO_INCREMENT for table `supplier_payments`
--
ALTER TABLE `supplier_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `supplier_refill_breakdown`
--
ALTER TABLE `supplier_refill_breakdown`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `supplier_transactions`
--
ALTER TABLE `supplier_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

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
