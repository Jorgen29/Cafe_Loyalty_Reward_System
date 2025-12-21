-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 17, 2025 at 12:03 PM
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
-- Table structure for table `home_page_assets`
--

CREATE TABLE `home_page_assets` (
  `h_assets_id` int(255) NOT NULL,
  `cover_image` varchar(255) NOT NULL,
  `cover_text` varchar(255) NOT NULL,
  `menu_teaser_title` text NOT NULL,
  `menu_teaser_image` varchar(255) NOT NULL,
  `menu_teaser_description` varchar(255) NOT NULL,
  `category` enum('Home','Menu','Rewards','') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `home_page_assets`
--

INSERT INTO `home_page_assets` (`h_assets_id`, `cover_image`, `cover_text`, `menu_teaser_title`, `menu_teaser_image`, `menu_teaser_description`, `category`) VALUES
(2, '/Cafe_Loyalty_Reward_System/public/assets/page_image/home_page/home_1763351550_691a9bfee06ba.jpg', 'TEST 1', 'TEST 1', '/Cafe_Loyalty_Reward_System/public/assets/page_image/home_page/home_1763351135_691a9a5f6f664.jpg', '123', 'Home');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `home_page_assets`
--
ALTER TABLE `home_page_assets`
  ADD PRIMARY KEY (`h_assets_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `home_page_assets`
--
ALTER TABLE `home_page_assets`
  MODIFY `h_assets_id` int(255) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
