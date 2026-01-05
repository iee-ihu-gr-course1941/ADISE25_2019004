-- --------------------------------------------------------
-- Διακομιστής:                  127.0.0.1
-- Έκδοση διακομιστή:            10.4.32-MariaDB - mariadb.org binary distribution
-- Λειτ. σύστημα διακομιστή:     Win64
-- HeidiSQL Έκδοση:              12.14.0.7165
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Dumping database structure for kseri
CREATE DATABASE IF NOT EXISTS `kseri` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;
USE `kseri`;

-- Dumping structure for πίνακας kseri.board
CREATE TABLE IF NOT EXISTS `board` (
  `id` tinyint(4) NOT NULL DEFAULT 1 CHECK (`id` = 1),
  `current_turn` tinyint(4) NOT NULL CHECK (`current_turn` in (1,2)),
  `player1_hand` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`player1_hand`)),
  `player2_hand` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`player2_hand`)),
  `table_pile` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`table_pile`)),
  `player1_captured` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`player1_captured`)),
  `player2_captured` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`player2_captured`)),
  `forced_draw` int(11) DEFAULT 0,
  `chosen_suit` enum('spades','hearts','diamonds','clubs') DEFAULT NULL,
  `status` enum('waiting','ready','initial_deal','active','round_over','finished') NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table kseri.board: ~0 rows (approximately)
DELETE FROM `board`;
INSERT INTO `board` (`id`, `current_turn`, `player1_hand`, `player2_hand`, `table_pile`, `player1_captured`, `player2_captured`, `forced_draw`, `chosen_suit`, `status`, `updated_at`) VALUES
	(1, 1, '[]', '[]', '[]', '[]', '[]', 0, NULL, 'waiting', '2026-01-03 18:48:25');

-- Dumping structure for πίνακας kseri.cards
CREATE TABLE IF NOT EXISTS `cards` (
  `card_id` int(11) NOT NULL AUTO_INCREMENT,
  `suit` enum('spades','hearts','diamonds','clubs') NOT NULL,
  `rank` enum('A','2','3','4','5','6','7','8','9','10','J','Q','K') NOT NULL,
  `rank_value` tinyint(4) GENERATED ALWAYS AS (case `rank` when 'A' then 1 when '2' then 2 when '3' then 3 when '4' then 4 when '5' then 5 when '6' then 6 when '7' then 7 when '8' then 8 when '9' then 9 when '10' then 10 when 'J' then 11 when 'Q' then 12 when 'K' then 13 end) STORED,
  `short_name` varchar(4) GENERATED ALWAYS AS (concat(case `suit` when 'spades' then '♠' when 'hearts' then '♥' when 'diamonds' then '♦' when 'clubs' then '♣' end,`rank`)) STORED,
  PRIMARY KEY (`card_id`),
  UNIQUE KEY `unique_card` (`suit`,`rank`)
) ENGINE=InnoDB AUTO_INCREMENT=53 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table kseri.cards: ~52 rows (approximately)
DELETE FROM `cards`;
INSERT INTO `cards` (`card_id`, `suit`, `rank`) VALUES
	(1, 'spades', 'A'),
	(2, 'spades', '2'),
	(3, 'spades', '3'),
	(4, 'spades', '4'),
	(5, 'spades', '5'),
	(6, 'spades', '6'),
	(7, 'spades', '7'),
	(8, 'spades', '8'),
	(9, 'spades', '9'),
	(10, 'spades', '10'),
	(11, 'spades', 'J'),
	(12, 'spades', 'Q'),
	(13, 'spades', 'K'),
	(14, 'hearts', 'A'),
	(15, 'hearts', '2'),
	(16, 'hearts', '3'),
	(17, 'hearts', '4'),
	(18, 'hearts', '5'),
	(19, 'hearts', '6'),
	(20, 'hearts', '7'),
	(21, 'hearts', '8'),
	(22, 'hearts', '9'),
	(23, 'hearts', '10'),
	(24, 'hearts', 'J'),
	(25, 'hearts', 'Q'),
	(26, 'hearts', 'K'),
	(27, 'diamonds', 'A'),
	(28, 'diamonds', '2'),
	(29, 'diamonds', '3'),
	(30, 'diamonds', '4'),
	(31, 'diamonds', '5'),
	(32, 'diamonds', '6'),
	(33, 'diamonds', '7'),
	(34, 'diamonds', '8'),
	(35, 'diamonds', '9'),
	(36, 'diamonds', '10'),
	(37, 'diamonds', 'J'),
	(38, 'diamonds', 'Q'),
	(39, 'diamonds', 'K'),
	(40, 'clubs', 'A'),
	(41, 'clubs', '2'),
	(42, 'clubs', '3'),
	(43, 'clubs', '4'),
	(44, 'clubs', '5'),
	(45, 'clubs', '6'),
	(46, 'clubs', '7'),
	(47, 'clubs', '8'),
	(48, 'clubs', '9'),
	(49, 'clubs', '10'),
	(50, 'clubs', 'J'),
	(51, 'clubs', 'Q'),
	(52, 'clubs', 'K');

-- Dumping structure for πίνακας kseri.deck
CREATE TABLE IF NOT EXISTS `deck` (
  `position` int(11) NOT NULL,
  `card_id` int(11) NOT NULL,
  PRIMARY KEY (`position`),
  KEY `card_id` (`card_id`),
  CONSTRAINT `deck_ibfk_1` FOREIGN KEY (`card_id`) REFERENCES `cards` (`card_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table kseri.deck: ~0 rows (approximately)
DELETE FROM `deck`;

-- Dumping structure for πίνακας kseri.players
CREATE TABLE IF NOT EXISTS `players` (
  `player_id` tinyint(4) NOT NULL CHECK (`player_id` in (1,2)),
  `username` varchar(20) DEFAULT NULL,
  `token` varchar(100) DEFAULT NULL,
  `last_action` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`player_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table kseri.players: ~0 rows (approximately)
DELETE FROM `players`;

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
