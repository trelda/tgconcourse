-- phpMyAdmin SQL Dump
-- version 5.1.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jun 20, 2023 at 05:06 AM
-- Server version: 10.3.38-MariaDB-0ubuntu0.20.04.1-log
-- PHP Version: 8.1.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `concourse`
--

-- --------------------------------------------------------

--
-- Table structure for table `game_list`
--

CREATE TABLE `game_list` (
  `id` int(11) NOT NULL,
  `gameStatus` int(11) NOT NULL DEFAULT 0,
  `author` varchar(20) NOT NULL,
  `gameId` int(11) NOT NULL,
  `users` mediumtext DEFAULT NULL,
  `gameDate` datetime NOT NULL,
  `gameFile` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `game_questions`
--

CREATE TABLE `game_questions` (
  `id` int(11) NOT NULL,
  `gameId` int(11) NOT NULL,
  `questionText` mediumtext NOT NULL DEFAULT '',
  `questionScore` int(11) NOT NULL DEFAULT 0,
  `questionEfficiency` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `game_users`
--

CREATE TABLE `game_users` (
  `id` int(10) UNSIGNED NOT NULL,
  `chatId` mediumtext DEFAULT NULL,
  `userName` varchar(450) DEFAULT NULL,
  `type` varchar(1) DEFAULT '0',
  `contact` varchar(45) DEFAULT NULL,
  `date` datetime DEFAULT NULL,
  `mode` longtext DEFAULT NULL,
  `userFirstName` varchar(450) DEFAULT NULL,
  `startCounter` int(11) DEFAULT 0,
  `region` int(11) DEFAULT 0,
  `inGame` int(11) NOT NULL DEFAULT 0,
  `mId` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `game_list`
--
ALTER TABLE `game_list`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `game_questions`
--
ALTER TABLE `game_questions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `game_users`
--
ALTER TABLE `game_users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `game_list`
--
ALTER TABLE `game_list`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `game_questions`
--
ALTER TABLE `game_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `game_users`
--
ALTER TABLE `game_users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
