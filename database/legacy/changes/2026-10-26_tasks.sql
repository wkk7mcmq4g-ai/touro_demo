-- Εργασίες (projects / tasks / Kanban) for the MyAT back office.
-- Run once in phpMyAdmin on kwi14599_myat. Only creates new tables; nothing existing is touched,
-- so the PHPRunner app keeps working exactly as before.
-- People are the PHPRunner logins (MyAthensTransfersAPP_users.username).

CREATE TABLE IF NOT EXISTS `myat_projects` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `Name` varchar(150) NOT NULL DEFAULT '',
  `Emoji` varchar(16) NOT NULL DEFAULT '',
  `Color` varchar(7) NOT NULL DEFAULT '#0096ff',
  `Description` text DEFAULT NULL,
  `Archived` tinyint(1) NOT NULL DEFAULT 0,
  `Sort` int(11) NOT NULL DEFAULT 0,
  `CreatedBy` varchar(255) NOT NULL DEFAULT '',
  `CreatedAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `myat_tasks` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `ProjectID` int(11) NOT NULL,
  `Title` varchar(250) NOT NULL DEFAULT '',
  `Description` text DEFAULT NULL,
  `Status` varchar(20) NOT NULL DEFAULT 'todo' COMMENT 'todo, doing, review, done',
  `Priority` varchar(10) NOT NULL DEFAULT 'medium' COMMENT 'critical, high, medium, low',
  `DueDate` date DEFAULT NULL,
  `Sort` int(11) NOT NULL DEFAULT 0 COMMENT 'order inside its Kanban column',
  `TransferID` int(11) DEFAULT NULL COMMENT 'optional link to a transfer',
  `CreatedBy` varchar(255) NOT NULL DEFAULT '',
  `CreatedAt` datetime NOT NULL DEFAULT current_timestamp(),
  `UpdatedAt` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `CompletedAt` datetime DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `ProjectStatus` (`ProjectID`, `Status`, `Sort`),
  KEY `DueDate` (`DueDate`),
  KEY `TransferID` (`TransferID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `myat_task_assignees` (
  `TaskID` int(11) NOT NULL,
  `Username` varchar(255) NOT NULL,
  PRIMARY KEY (`TaskID`, `Username`),
  KEY `Username` (`Username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `myat_task_subtasks` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `TaskID` int(11) NOT NULL,
  `Title` varchar(250) NOT NULL DEFAULT '',
  `Done` tinyint(1) NOT NULL DEFAULT 0,
  `Sort` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ID`),
  KEY `TaskID` (`TaskID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `myat_task_comments` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `TaskID` int(11) NOT NULL,
  `Username` varchar(255) NOT NULL DEFAULT '',
  `Body` text NOT NULL,
  `CreatedAt` datetime NOT NULL DEFAULT current_timestamp(),
  `EditedAt` datetime DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `TaskID` (`TaskID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Files are kept in the database, like myat_transfers_files.
CREATE TABLE IF NOT EXISTS `myat_task_files` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `TaskID` int(11) NOT NULL,
  `Flnm` varchar(250) NOT NULL DEFAULT '',
  `Mime` varchar(120) NOT NULL DEFAULT '',
  `Size` int(11) NOT NULL DEFAULT 0,
  `Attachment` mediumblob DEFAULT NULL,
  `Username` varchar(255) NOT NULL DEFAULT '',
  `CreatedAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`ID`),
  KEY `TaskID` (`TaskID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
