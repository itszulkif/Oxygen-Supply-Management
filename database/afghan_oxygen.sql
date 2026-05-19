-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 19, 2026 at 03:19 PM
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
(2850, 'Small', 0.00, 0.00, 0, 0, 0.00, '2026-05-19 12:46:34', '2026-05-19 12:46:34');

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `customer_cylinder_balance`
--
ALTER TABLE `customer_cylinder_balance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `cylinders`
--
ALTER TABLE `cylinders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `cylinder_stock_by_type`
--
ALTER TABLE `cylinder_stock_by_type`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2854;

--
-- AUTO_INCREMENT for table `employee_salaries`
--
ALTER TABLE `employee_salaries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `general_expenses`
--
ALTER TABLE `general_expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `ledger`
--
ALTER TABLE `ledger`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=94;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `refill_discrepancy`
--
ALTER TABLE `refill_discrepancy`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `reminders`
--
ALTER TABLE `reminders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `service_cylinder_rows`
--
ALTER TABLE `service_cylinder_rows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `supplier_ledger`
--
ALTER TABLE `supplier_ledger`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=227;

--
-- AUTO_INCREMENT for table `supplier_payments`
--
ALTER TABLE `supplier_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=87;

--
-- AUTO_INCREMENT for table `supplier_refill_breakdown`
--
ALTER TABLE `supplier_refill_breakdown`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=87;

--
-- AUTO_INCREMENT for table `supplier_transactions`
--
ALTER TABLE `supplier_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

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
