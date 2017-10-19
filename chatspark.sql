-- phpMyAdmin SQL Dump
-- version 4.7.4
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 19, 2017 at 06:32 AM
-- Server version: 5.7.19
-- PHP Version: 7.0.23

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `chatspark`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`sparktalk_user`@`%` PROCEDURE `map_id_autoincrement` (IN `id2` SMALLINT(5), IN `type` TINYINT(3))  BEGIN
	SET @id=IFNULL((SELECT MAX(id1) FROM id_map WHERE type=type), 0)+1;
	INSERT INTO id_map (`id1`, `id2`, `type`) VALUES(@id, id2, type);
	SELECT @id AS id;
	END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `email`
--

CREATE TABLE `email` (
  `email_address` varchar(320) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `requested` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sent` timestamp NULL DEFAULT NULL,
  `ip` varchar(45) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `file_urls`
--

CREATE TABLE `file_urls` (
  `file_id` smallint(6) NOT NULL,
  `path` varchar(64) NOT NULL,
  `url` varchar(2100) NOT NULL,
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `id_map`
--

CREATE TABLE `id_map` (
  `id1` smallint(5) UNSIGNED NOT NULL,
  `id2` smallint(5) UNSIGNED NOT NULL,
  `type` tinyint(3) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `strings`
--

CREATE TABLE `strings` (
  `string_id` smallint(5) UNSIGNED NOT NULL,
  `language` tinyint(3) UNSIGNED NOT NULL,
  `string` varchar(1024) NOT NULL,
  `string_hash` int(10) UNSIGNED NOT NULL,
  `string_uid` mediumint(9) NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `email_hash` varchar(88) NOT NULL,
  `email_hash_hash` int(10) UNSIGNED NOT NULL,
  `gender` tinyint(3) UNSIGNED NOT NULL,
  `birthday` int(10) UNSIGNED DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `password_hash` varchar(88) NOT NULL,
  `verified` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `email_hash`, `email_hash_hash`, `gender`, `birthday`, `created`, `password_hash`, `verified`) VALUES
(1, 'GdfilU+DEU2qIfmT9o/YLXBDz6OfuE80vUS/z0WBfGg=', 1952491474, 0, 1167638400, '2017-10-19 13:30:36', 'lF1FHJDYQKf95i5Bx53Kl1mgZVAJqji9oFAdNLJHzdk=', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `email`
--
ALTER TABLE `email`
  ADD UNIQUE KEY `user_id` (`user_id`,`sent`) USING BTREE;

--
-- Indexes for table `file_urls`
--
ALTER TABLE `file_urls`
  ADD PRIMARY KEY (`file_id`),
  ADD UNIQUE KEY `path` (`path`);

--
-- Indexes for table `id_map`
--
ALTER TABLE `id_map`
  ADD UNIQUE KEY `UNIQUE` (`id1`,`id2`,`type`) USING BTREE;

--
-- Indexes for table `strings`
--
ALTER TABLE `strings`
  ADD PRIMARY KEY (`string_uid`),
  ADD UNIQUE KEY `string_id` (`string_id`,`language`) USING BTREE,
  ADD UNIQUE KEY `string_hash` (`string_hash`,`language`) USING BTREE;

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email_hash` (`email_hash`),
  ADD UNIQUE KEY `email-hash-hash` (`email_hash_hash`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `file_urls`
--
ALTER TABLE `file_urls`
  MODIFY `file_id` smallint(6) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `strings`
--
ALTER TABLE `strings`
  MODIFY `string_uid` mediumint(9) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
