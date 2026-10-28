-- Ιστορικό ενεργειών (activity log) of the new MyAT app.
-- Run once in phpMyAdmin on kwi14599_myat. Only creates a new table; nothing existing is touched.
-- PHPRunner keeps writing its own trail to myat_audit_audit.
-- One row per login, logout, insert, update and delete made in the new app, with the app version.

CREATE TABLE IF NOT EXISTS `myat_activity_log` (
  `ID` bigint(20) NOT NULL AUTO_INCREMENT,
  `CreatedAt` datetime NOT NULL DEFAULT current_timestamp(),
  `Username` varchar(255) NOT NULL DEFAULT '' COMMENT 'MyAthensTransfersAPP_users.username; empty for system jobs',
  `IP` varchar(45) NOT NULL DEFAULT '',
  `Action` varchar(10) NOT NULL COMMENT 'login, logout, insert, update, delete, call',
  `TableName` varchar(100) NOT NULL DEFAULT '',
  `RecordID` varchar(100) NOT NULL DEFAULT '',
  `Changes` mediumtext DEFAULT NULL COMMENT 'JSON: update = {field: [old, new]}; insert/delete = {field: value}; other writes = {sql, bindings}',
  `Panel` varchar(20) NOT NULL DEFAULT '' COMMENT 'admin or portal',
  `Url` varchar(500) NOT NULL DEFAULT '',
  `AppVersion` varchar(40) NOT NULL DEFAULT '' COMMENT 'Έκδοση N (commit) of the new app',
  PRIMARY KEY (`ID`),
  KEY `Record` (`TableName`, `RecordID`),
  KEY `CreatedAt` (`CreatedAt`),
  KEY `UserDate` (`Username`, `CreatedAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
