-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 23, 2025 at 06:20 AM
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
-- Database: `cf-rw-db`
--

-- --------------------------------------------------------

--
-- Table structure for table `ingredienttransaction`
--

CREATE TABLE `ingredienttransaction` (
  `transaction_id` int(11) NOT NULL,
  `ingredient_id` int(11) NOT NULL,
  `cashier_id` int(11) NOT NULL,
  `transaction_type` enum('add','use') NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `transaction_date` datetime NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `ingredienttransaction`
--
ALTER TABLE `ingredienttransaction`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `ingredient_id` (`ingredient_id`),
  ADD KEY `fk_cashier` (`cashier_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `ingredienttransaction`
--
ALTER TABLE `ingredienttransaction`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `ingredienttransaction`
--
ALTER TABLE `ingredienttransaction`
  ADD CONSTRAINT `fk_cashier` FOREIGN KEY (`cashier_id`) REFERENCES `cashier` (`cashier_id`),
  ADD CONSTRAINT `ingredienttransaction_ibfk_1` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredient` (`ingredient_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
