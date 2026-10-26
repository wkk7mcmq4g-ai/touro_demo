-- =====================================================================================
-- Demo showcase: anonymise a COPY of the MyAT database and add the demo logins.
--
-- Run in phpMyAdmin, on the demo copy only (select that database, SQL tab, paste,
-- Go). It refuses to run on the live business databases. Data only: no table, column,
-- view or trigger is changed; the helper functions/tables it makes are dropped at the end.
-- If it stops half-way (e.g. a timeout), fix the cause and run the whole file again.
--
-- What it does
--   * Passengers, lead names/phones, lessees: invented names, phones in the UK range
--     reserved for fiction (+44 7700 900xxx), so nobody real can be reached.
--   * Partners (customers, suppliers, coach companies, guides): invented company or
--     person names, e-mails @example.com, no VAT/ΔΟΥ/IBAN/bank/AMKA/notes. The names are
--     changed on the transfers too (Coach, Assistant), so the links keep working.
--   * Drivers: invented names, phones, e-mails, licence/ID numbers; no salary, files,
--     Telegram. Vehicles: invented plates, no photos.
--   * Logins: renamed (staff01, driver01, partner01, ...) everywhere they are recorded
--     (CreatedBy, ModifiedBy, column settings, tasks...), e-mail @example.com, and LOCKED
--     (no password works). `admin` keeps its name but is locked too.
--   * Free text (notes, comments, expense reasons): phone numbers and e-mail addresses are
--     replaced. Names typed inside notes cannot be found automatically; see the end.
--   * Attachments (transfer/partner/booking files, task files) deleted; file links cleared.
--   * Logs and copies (access log, audit, app logs, Telegram, sent e-mails, notifications,
--     users_copy, settings_copy, calusers) emptied; Elorus API key removed.
--   * Kept as they are: dates, times, routes, flights, prices, statuses, vehicles' names,
--     tours, charges, payments.
--   * Demo logins (password Demo2026 for all four):
--       demo     Admin   (back office)
--       office   Entry   (back office, office rights)
--       driver   Driver  (portal; linked to the busiest active driver)
--       partner  Partner (portal; linked to the busiest customer of the last 12 months)
--
-- Side effect to know: every transfer is updated, so MariaDB recomputes two derived flags
-- on each (`pricecheck`, `SupplierModified`), exactly as an edit in the app would, and a
-- completed "NOT PROVIDED" job gets InvoicedRcvd = 'Δε θα λάβω' if it didn't have it yet.
-- All other money columns are put back as they were (tested: sales, assets, income equal).
-- =====================================================================================

-- ---- 0. Never on a live database --------------------------------------------------
DROP PROCEDURE IF EXISTS zz_anon_guard;
DELIMITER $$
CREATE PROCEDURE zz_anon_guard()
BEGIN
  IF DATABASE() IS NULL OR DATABASE() IN ('kwi14599_myat', 'kwi14599_yomadic', 'kwi14599_lstours',
      'kwi14599_beskos', 'kwi14599_keep', 'kwi14599_tta', 'kwi14599_euro') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Stopped: this is a live database (or none is selected). Run on the demo copy only.';
  END IF;
END$$
DELIMITER ;
CALL zz_anon_guard();
DROP PROCEDURE zz_anon_guard;

-- ---- 1. Helpers (dropped at the end) ---------------------------------------------
DROP FUNCTION IF EXISTS zz_anon_guest;
DROP FUNCTION IF EXISTS zz_anon_greek;
DROP FUNCTION IF EXISTS zz_anon_company;
DROP FUNCTION IF EXISTS zz_anon_phone;
DROP FUNCTION IF EXISTS zz_anon_scrubbed;
DROP PROCEDURE IF EXISTS zz_anon_run;
DROP PROCEDURE IF EXISTS zz_anon_scrub;
DROP PROCEDURE IF EXISTS zz_anon_rename_users;

DELIMITER $$

-- An international guest's name, the same for the same number.
CREATE FUNCTION zz_anon_guest(n INT) RETURNS VARCHAR(100) CHARSET utf8mb4 DETERMINISTIC
RETURN CONCAT(
  ELT(1 + MOD(ABS(n) * 7, 30), 'James', 'Emma', 'Olivia', 'Liam', 'Noah', 'Sophia', 'Mia', 'Lucas', 'Anna', 'David',
      'Sarah', 'Michael', 'Laura', 'Daniel', 'Julia', 'Thomas', 'Chloe', 'Matteo', 'Elena', 'Marco',
      'Hannah', 'Lukas', 'Isabella', 'Pierre', 'Camille', 'Carlos', 'Lucia', 'Kenji', 'Yuki', 'Ethan'),
  ' ',
  ELT(1 + MOD(ABS(n) * 13 + 5, 30), 'Smith', 'Johnson', 'Brown', 'Miller', 'Wilson', 'Taylor', 'Anderson', 'Moore', 'Martin', 'Garcia',
      'Rossi', 'Bianchi', 'Muller', 'Schmidt', 'Dubois', 'Laurent', 'Lopez', 'Fernandez', 'Silva', 'Nielsen',
      'Jensen', 'Novak', 'Kowalski', 'Tanaka', 'Sato', 'Kim', 'Cohen', 'Murphy', 'Kelly', 'Evans'))$$

-- A Greek person's name (drivers, guides, staff), unique for n < 400.
CREATE FUNCTION zz_anon_greek(n INT, female TINYINT) RETURNS VARCHAR(100) CHARSET utf8mb4 DETERMINISTIC
RETURN CONCAT(
  IF(female,
     ELT(1 + MOD(ABS(n), 20), 'Maria', 'Eleni', 'Katerina', 'Sofia', 'Georgia', 'Dimitra', 'Vasiliki', 'Anna', 'Ioanna', 'Christina',
         'Despina', 'Angeliki', 'Foteini', 'Eirini', 'Konstantina', 'Paraskevi', 'Stella', 'Marina', 'Athina', 'Niki'),
     ELT(1 + MOD(ABS(n), 20), 'Giorgos', 'Nikos', 'Kostas', 'Dimitris', 'Giannis', 'Christos', 'Panagiotis', 'Vasilis', 'Thanasis', 'Manolis',
         'Stelios', 'Michalis', 'Spyros', 'Antonis', 'Petros', 'Lefteris', 'Stavros', 'Alexandros', 'Pavlos', 'Andreas')),
  ' ',
  IF(female,
     ELT(1 + MOD(FLOOR(ABS(n) / 20), 20), 'Papadopoulou', 'Georgiou', 'Nikolaou', 'Konstantinou', 'Dimitriou', 'Ioannou', 'Vlachou', 'Karra', 'Alexiou', 'Makri',
         'Pappa', 'Oikonomou', 'Lambrou', 'Antoniou', 'Christodoulou', 'Stavrou', 'Petrou', 'Michailidou', 'Athanasiou', 'Rapti'),
     ELT(1 + MOD(FLOOR(ABS(n) / 20), 20), 'Papadopoulos', 'Georgiou', 'Nikolaou', 'Konstantinou', 'Dimitriou', 'Ioannou', 'Vlachos', 'Karras', 'Alexiou', 'Makris',
         'Pappas', 'Oikonomou', 'Lambrou', 'Antoniou', 'Christodoulou', 'Stavrou', 'Petrou', 'Michailidis', 'Athanasiou', 'Raptis')),
  IF(ABS(n) >= 400, CONCAT(' ', FLOOR(ABS(n) / 400) + 1), ''))$$

-- A company name, unique per number (for counterparties).
CREATE FUNCTION zz_anon_company(n INT) RETURNS VARCHAR(100) CHARSET utf8mb4 DETERMINISTIC
RETURN CONCAT(
  ELT(1 + MOD(ABS(n), 20), 'Aegean', 'Olympus', 'Acropolis', 'Poseidon', 'Hermes', 'Athena', 'Delphi', 'Marathon', 'Lycabettus', 'Plaka',
      'Saronic', 'Ionian', 'Cycladic', 'Meteora', 'Parthenon', 'Attica', 'Hellas', 'Apollo', 'Artemis', 'Odyssey'),
  ' ',
  ELT(1 + MOD(FLOOR(ABS(n) / 20), 10), 'Travel', 'Tours', 'Holidays', 'Coaches', 'Transport', 'Journeys', 'Voyages', 'DMC', 'Excursions', 'Mobility'),
  IF(ABS(n) >= 200, CONCAT(' ', FLOOR(ABS(n) / 200) + 1), ''))$$

-- A phone number in the UK range reserved for fiction (Ofcom): +44 7700 900000-900999.
CREATE FUNCTION zz_anon_phone(n INT) RETURNS VARCHAR(30) CHARSET utf8mb4 DETERMINISTIC
RETURN CONCAT('+44 7700 900', LPAD(MOD(ABS(n), 1000), 3, '0'))$$

-- Free text with phone numbers (9+ digits) and e-mail addresses replaced.
CREATE FUNCTION zz_anon_scrubbed(s TEXT CHARSET utf8mb4) RETURNS TEXT CHARSET utf8mb4 DETERMINISTIC
RETURN IF(s IS NULL OR s = '', s,
  REGEXP_REPLACE(
    REGEXP_REPLACE(s, '[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}', 'guest@example.com'),
    '(\\+|00)?[0-9]([ .-]?[0-9]){8,}', '+44 7700 900000'))$$

-- Run a statement only if the table exists (the copy may predate some tables).
CREATE PROCEDURE zz_anon_run(IN tbl VARCHAR(64), IN stmt TEXT)
BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND TABLE_TYPE = 'BASE TABLE') THEN
    SET @zz_sql = stmt;
    PREPARE zz_stmt FROM @zz_sql;
    EXECUTE zz_stmt;
    DEALLOCATE PREPARE zz_stmt;
  END IF;
END$$

-- Replace phones / e-mails in a free-text column, if it exists.
CREATE PROCEDURE zz_anon_scrub(IN tbl VARCHAR(64), IN col VARCHAR(64))
BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col) THEN
    SET @zz_sql = CONCAT('UPDATE `', tbl, '` SET `', col, '` = zz_anon_scrubbed(CONVERT(`', col, '` USING utf8mb4)) ',
                         'WHERE `', col, '` REGEXP ''@|[0-9]{3}''');
    PREPARE zz_stmt FROM @zz_sql;
    EXECUTE zz_stmt;
    DEALLOCATE PREPARE zz_stmt;
  END IF;
END$$

-- Every column that records a login name (CreatedBy, ModifiedBy, settings USERNAME, task
-- people...) follows the renamed logins. Not PaymentsReceived: its update trigger
-- re-allocates payments, so it is left alone.
CREATE PROCEDURE zz_anon_rename_users()
BEGIN
  DECLARE done INT DEFAULT 0;
  DECLARE t VARCHAR(64);
  DECLARE c VARCHAR(64);
  DECLARE cur CURSOR FOR
    SELECT col.TABLE_NAME, col.COLUMN_NAME
    FROM information_schema.COLUMNS col
    JOIN information_schema.TABLES tb ON tb.TABLE_SCHEMA = col.TABLE_SCHEMA AND tb.TABLE_NAME = col.TABLE_NAME
    WHERE col.TABLE_SCHEMA = DATABASE() AND tb.TABLE_TYPE = 'BASE TABLE'
      AND col.COLUMN_NAME IN ('CreatedBy', 'ModifiedBy', 'MofifiedBy', 'LastUpdatedBy', 'ApprovedBy', 'USERNAME')
      AND col.EXTRA NOT LIKE '%GENERATED%'
      AND col.TABLE_NAME NOT IN ('MyAthensTransfersAPP_users', 'MyAthensTransfersAPP_users_copy', 'PaymentsReceived')
      AND col.TABLE_NAME NOT LIKE 'zz\_anon\_%';
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;
  OPEN cur;
  each_column: LOOP
    FETCH cur INTO t, c;
    IF done THEN LEAVE each_column; END IF;
    SET @zz_sql = CONCAT('UPDATE `', t, '` x JOIN zz_anon_users m ON CONVERT(x.`', c, '` USING utf8mb4) COLLATE utf8mb4_general_ci = m.old_un ',
                         'SET x.`', c, '` = m.new_un', IF(t = 'transfers', ', x.ModifiedTimestamp = x.ModifiedTimestamp', ''));
    PREPARE zz_stmt FROM @zz_sql;
    EXECUTE zz_stmt;
    DEALLOCATE PREPARE zz_stmt;
  END LOOP;
  CLOSE cur;
END$$

DELIMITER ;

-- ---- 2. Keep the money columns the transfers trigger would reset ---------------------
-- Changing a transfer's coach makes `transfers_before_update` reset vid, invoice and
-- platform fields; they are put back from here in step 8.
-- Kept if it already exists: a re-run after a failed attempt must not back up values the
-- first attempt already reset.
CREATE TABLE IF NOT EXISTS zz_anon_money (PRIMARY KEY (TransferID)) ENGINE=InnoDB AS
SELECT TransferID, vid, InvoicedRcvd, Platform, PlatformPrice, PurchasePrice, PricePaid FROM transfers;

-- ---- 3. Logins ----------------------------------------------------------------------
DELETE FROM MyAthensTransfersAPP_users WHERE username IN ('demo', 'office', 'driver', 'partner');

DROP TABLE IF EXISTS zz_anon_users;
CREATE TABLE zz_anon_users (
  ID INT PRIMARY KEY,
  old_un VARCHAR(255) COLLATE utf8mb4_general_ci,
  new_un VARCHAR(255) COLLATE utf8mb4_general_ci,
  KEY (old_un)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO zz_anon_users (ID, old_un, new_un)
SELECT ID, old_un,
       IF(LOWER(old_un) = 'admin', 'admin', CONCAT(prefix, LPAD(ROW_NUMBER() OVER (PARTITION BY prefix ORDER BY ID), 2, '0')))
FROM (
  SELECT ID, CONVERT(username USING utf8mb4) COLLATE utf8mb4_general_ci AS old_un,
         CASE
           WHEN groupid IN ('Admin', 'Entry') THEN 'staff'
           WHEN groupid IN ('Drivers', 'Drivers_Hol', 'Drivers_Log') THEN 'driver'
           WHEN groupid = 'Partner' THEN 'partner'
           WHEN groupid IN ('Supplier', 'Supplier_Hotel') THEN 'supplier'
           WHEN groupid = 'Coach' THEN 'coach'
           ELSE 'user'
         END AS prefix
  FROM MyAthensTransfersAPP_users
  WHERE IFNULL(username, '') <> ''
) u;

UPDATE MyAthensTransfersAPP_users u
JOIN zz_anon_users m ON m.ID = u.ID
SET u.username = m.new_un,
    u.fullname = IF(m.new_un = 'admin', 'Administrator', zz_anon_greek(u.ID, MOD(u.ID, 2))),
    u.email = CONCAT(m.new_un, '@example.com'),
    u.password = '!locked',          -- not a bcrypt hash: no password matches
    u.ext_security_id = NULL,
    u.userpic = NULL,
    u.apikey = NULL,
    u.remember_token = NULL;

CALL zz_anon_rename_users();

-- ---- 4. Drivers ---------------------------------------------------------------------
UPDATE drivers
SET Fullname = zz_anon_greek(DriverID + 37, 0),
    Shortname = SUBSTRING_INDEX(zz_anon_greek(DriverID + 37, 0), ' ', 1),
    MobilePhone = zz_anon_phone(500 + DriverID),
    Email = CONCAT('driver', DriverID, '@example.com'),
    LicenceNumber = CONCAT('DL', LPAD(MOD(DriverID * 7919, 1000000), 6, '0')),
    IDNumber = CONCAT('XX', LPAD(MOD(DriverID * 104729, 1000000), 6, '0')),
    Salary = NULL,
    Benefits = NULL,
    Files = NULL,
    TelegramID = NULL;

-- Driver logins carry their driver's name.
UPDATE MyAthensTransfersAPP_users u JOIN drivers d ON d.UserID = u.ID SET u.fullname = d.Fullname;

-- ---- 5. Partners (counterparties) ----------------------------------------------------
-- Map of old names -> new, so transfers' Coach / Assistant follow.
DROP TABLE IF EXISTS zz_anon_names;
CREATE TABLE zz_anon_names (
  CID INT,
  kind VARCHAR(10),
  old_name VARCHAR(500) COLLATE utf8mb4_general_ci,
  new_name VARCHAR(500) COLLATE utf8mb4_general_ci,
  KEY (kind, old_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Placeholders that the app and triggers look for are never renamed.
SET @keep = '^(TB[AD]|NOT PROVIDED|NotRegistered)';

-- Guides (assistants) become people, everyone else a company.
DROP TABLE IF EXISTS zz_anon_cp;
CREATE TABLE zz_anon_cp ENGINE=InnoDB AS
SELECT CID,
       (IsAssistant = 1) AS is_person,
       IF(IsAssistant = 1, zz_anon_greek(CID, 1), zz_anon_company(CID)) AS new_name
FROM counterparties;
ALTER TABLE zz_anon_cp ADD PRIMARY KEY (CID);

INSERT INTO zz_anon_names (CID, kind, old_name, new_name)
SELECT c.CID, 'coach', CONVERT(c.CoachName USING utf8mb4), m.new_name
FROM counterparties c JOIN zz_anon_cp m ON m.CID = c.CID
WHERE IFNULL(c.CoachName, '') <> '' AND CONVERT(c.CoachName USING utf8mb4) NOT REGEXP @keep;

INSERT INTO zz_anon_names (CID, kind, old_name, new_name)
SELECT c.CID, 'assistant', CONVERT(c.AssistantName USING utf8mb4), m.new_name
FROM counterparties c JOIN zz_anon_cp m ON m.CID = c.CID
WHERE IFNULL(c.AssistantName, '') <> '' AND CONVERT(c.AssistantName USING utf8mb4) NOT REGEXP @keep;

UPDATE counterparties c
JOIN zz_anon_cp m ON m.CID = c.CID
SET c.CounterShortName = IF(CONVERT(IFNULL(c.CounterShortName, '') USING utf8mb4) REGEXP @keep, CONVERT(c.CounterShortName USING utf8mb4), m.new_name),
    c.SupplierName = IF(CONVERT(IFNULL(c.SupplierName, '') USING utf8mb4) REGEXP @keep, CONVERT(c.SupplierName USING utf8mb4), m.new_name),
    c.CoachName = IF(CONVERT(IFNULL(c.CoachName, '') USING utf8mb4) REGEXP @keep, CONVERT(c.CoachName USING utf8mb4), m.new_name),
    c.AssistantName = IF(IFNULL(c.AssistantName, '') = '' OR CONVERT(c.AssistantName USING utf8mb4) REGEXP @keep, CONVERT(c.AssistantName USING utf8mb4), m.new_name),
    c.LesseName = IF(IFNULL(c.LesseName, '') = '' OR CONVERT(IFNULL(c.CounterShortName, '') USING utf8mb4) REGEXP @keep, CONVERT(c.LesseName USING utf8mb4),
                     IF(m.is_person, m.new_name, CONCAT(m.new_name, ' ', ELT(1 + MOD(c.CID, 4), 'IKE', 'OE', 'AE', 'EE')))),
    c.DOY = NULL, c.VAT = IF(IFNULL(c.VAT, '') = '', CONVERT(c.VAT USING utf8mb4), CONCAT('99', LPAD(c.CID, 7, '0'))),
    c.BankName = NULL, c.BankAccount = NULL, c.IBAN = NULL,
    c.Notes = NULL,
    c.Phones = IF(IFNULL(c.Phones, '') = '', CONVERT(c.Phones USING utf8mb4), zz_anon_phone(2000 + c.CID)),
    c.Email = IF(IFNULL(c.Email, '') = '', CONVERT(c.Email USING utf8mb4), CONCAT('contact', c.CID, '@example.com')),
    c.Address = IF(IFNULL(c.Address, '') = '', CONVERT(c.Address USING utf8mb4), CONCAT(c.CID, ' Demo Street, Athens')),
    c.AssistantNum = NULL, c.AssistantAMKA = NULL,
    c.ElorusID = 'NotRegistered';

-- Partner / supplier / coach logins carry their company's name.
UPDATE MyAthensTransfersAPP_users u JOIN counterparties c ON c.UserID = u.ID SET u.fullname = c.CounterShortName;
UPDATE MyAthensTransfersAPP_users u JOIN counterparties c ON c.CID = u.SupplierID
SET u.fullname = c.CounterShortName WHERE u.groupid IN ('Supplier', 'Supplier_Hotel');

-- ---- 6. Transfers -------------------------------------------------------------------
-- TBA/TBD placeholders and empty fields stay as they are (the warnings rely on them).
UPDATE transfers t
LEFT JOIN zz_anon_names nc ON nc.kind = 'coach' AND nc.old_name = CONVERT(t.CoachCompanyID USING utf8mb4) COLLATE utf8mb4_general_ci
LEFT JOIN zz_anon_names na ON na.kind = 'assistant' AND na.old_name = CONVERT(t.Assistant USING utf8mb4) COLLATE utf8mb4_general_ci
LEFT JOIN counterparties c ON c.CID = t.cID_Customer
SET t.PassengerName = IF(IFNULL(t.PassengerName, '') = '' OR UPPER(t.PassengerName) LIKE 'TB%', CONVERT(t.PassengerName USING utf8mb4), zz_anon_guest(t.TransferID)),
    t.LeadName = IF(IFNULL(t.LeadName, '') = '' OR UPPER(t.LeadName) LIKE 'TB%', CONVERT(t.LeadName USING utf8mb4), zz_anon_guest(t.TransferID)),
    t.LeadPhone = IF(IFNULL(t.LeadPhone, '') = '' OR UPPER(t.LeadPhone) LIKE 'TB%', CONVERT(t.LeadPhone USING utf8mb4), zz_anon_phone(t.TransferID)),
    t.LesseeName = IF(IFNULL(t.LesseeName, '') = '' OR UPPER(t.LesseeName) LIKE 'TB%', CONVERT(t.LesseeName USING utf8mb4),
                      IF(c.CID IS NOT NULL AND c.LeadName <> 1, CONVERT(c.LesseName USING utf8mb4), zz_anon_guest(t.TransferID))),
    t.Notes = zz_anon_scrubbed(CONVERT(t.Notes USING utf8mb4)),
    t.DriverNotes = zz_anon_scrubbed(CONVERT(t.DriverNotes USING utf8mb4)),
    t.CoachCompanyID = IFNULL(nc.new_name, CONVERT(t.CoachCompanyID USING utf8mb4)),
    t.Assistant = IFNULL(na.new_name, CONVERT(t.Assistant USING utf8mb4)),
    t.Files = NULL, t.Attachments = NULL, t.FLNM = NULL,
    t.ModifiedTimestamp = t.ModifiedTimestamp;

-- Archive (no triggers).
CALL zz_anon_run('transfers_archive', '
UPDATE transfers_archive t
LEFT JOIN zz_anon_names nc ON nc.kind = ''coach'' AND nc.old_name = CONVERT(t.CoachCompanyID USING utf8mb4) COLLATE utf8mb4_general_ci
LEFT JOIN zz_anon_names na ON na.kind = ''assistant'' AND na.old_name = CONVERT(t.Assistant USING utf8mb4) COLLATE utf8mb4_general_ci
LEFT JOIN counterparties c ON c.CID = t.cID_Customer
SET t.PassengerName = IF(IFNULL(t.PassengerName, '''') = '''' OR UPPER(t.PassengerName) LIKE ''TB%'', CONVERT(t.PassengerName USING utf8mb4), zz_anon_guest(t.TransferID)),
    t.LeadName = IF(IFNULL(t.LeadName, '''') = '''' OR UPPER(t.LeadName) LIKE ''TB%'', CONVERT(t.LeadName USING utf8mb4), zz_anon_guest(t.TransferID)),
    t.LeadPhone = IF(IFNULL(t.LeadPhone, '''') = '''' OR UPPER(t.LeadPhone) LIKE ''TB%'', CONVERT(t.LeadPhone USING utf8mb4), zz_anon_phone(t.TransferID)),
    t.LesseeName = IF(IFNULL(t.LesseeName, '''') = '''' OR UPPER(t.LesseeName) LIKE ''TB%'', CONVERT(t.LesseeName USING utf8mb4),
                      IF(c.CID IS NOT NULL AND c.LeadName <> 1, CONVERT(c.LesseName USING utf8mb4), zz_anon_guest(t.TransferID))),
    t.Notes = zz_anon_scrubbed(CONVERT(t.Notes USING utf8mb4)),
    t.DriverNotes = zz_anon_scrubbed(CONVERT(t.DriverNotes USING utf8mb4)),
    t.CoachCompanyID = IFNULL(nc.new_name, CONVERT(t.CoachCompanyID USING utf8mb4)),
    t.Assistant = IFNULL(na.new_name, CONVERT(t.Assistant USING utf8mb4)),
    t.Files = NULL, t.Attachments = NULL, t.FLNM = NULL');

-- Booking requests (Προς Διαλογή).
UPDATE transfers_load t
LEFT JOIN zz_anon_names na ON na.kind = 'assistant' AND na.old_name = CONVERT(t.Assistant USING utf8mb4) COLLATE utf8mb4_general_ci
SET t.PassengerList = IF(IFNULL(t.PassengerList, '') = '' OR UPPER(t.PassengerList) LIKE 'TB%', CONVERT(t.PassengerList USING utf8mb4), zz_anon_guest(t.LoadID + 900000)),
    t.LeadName = IF(IFNULL(t.LeadName, '') = '' OR UPPER(t.LeadName) LIKE 'TB%', CONVERT(t.LeadName USING utf8mb4), zz_anon_guest(t.LoadID + 900000)),
    t.LeadPhone = IF(IFNULL(t.LeadPhone, '') = '' OR UPPER(t.LeadPhone) LIKE 'TB%', CONVERT(t.LeadPhone USING utf8mb4), zz_anon_phone(t.LoadID + 900000)),
    t.Notes = zz_anon_scrubbed(CONVERT(t.Notes USING utf8mb4)),
    t.Assistant = IFNULL(na.new_name, CONVERT(t.Assistant USING utf8mb4));

-- ---- 7. Other free text, vehicles, files, logs, secrets ------------------------------
CALL zz_anon_scrub('transfer_notes', 'Note');
CALL zz_anon_scrub('transfer_notes_load', 'Note');
CALL zz_anon_scrub('transfers_comms', 'Notes');
CALL zz_anon_scrub('transfer_charges', 'TransferCharge');
CALL zz_anon_scrub('transfer_charges', 'ExpenseReason');
CALL zz_anon_scrub('expenses', 'ExpenseReason');
CALL zz_anon_scrub('vehicles_log', 'Description');
CALL zz_anon_scrub('vehicles', 'FreeText');
CALL zz_anon_scrub('myat_tasks', 'Description');
CALL zz_anon_scrub('myat_task_comments', 'Body');

UPDATE vehicles
SET LicencePlate = IF(IFNULL(LicencePlate, '') = '', CONVERT(LicencePlate USING utf8mb4),
                      CONCAT(ELT(1 + MOD(VehicleID, 6), 'ZZA', 'ZZB', 'ZZE', 'ZZH', 'ZZK', 'ZZM'), '-', LPAD(MOD(VehicleID * 7919, 10000), 4, '0'))),
    Picture = NULL, flnm = NULL;

UPDATE expenses SET Files = NULL WHERE Files IS NOT NULL;
UPDATE fuel_register SET Files = NULL WHERE Files IS NOT NULL;
UPDATE vehicles_log SET Files = NULL WHERE Files IS NOT NULL;

-- Attachments stored in the database (passports, vouchers, invoices...).
CALL zz_anon_run('myat_transfers_files', 'DELETE FROM myat_transfers_files');
CALL zz_anon_run('myat_partner_files', 'DELETE FROM myat_partner_files');
CALL zz_anon_run('myat_transfers_load_files', 'DELETE FROM myat_transfers_load_files');
CALL zz_anon_run('myat_task_files', 'DELETE FROM myat_task_files');

-- Logs, copies and message history.
CALL zz_anon_run('access_log', 'DELETE FROM access_log');
CALL zz_anon_run('myat_audit_audit', 'DELETE FROM myat_audit_audit');
CALL zz_anon_run('app_logs', 'DELETE FROM app_logs');
CALL zz_anon_run('telegram_messages', 'DELETE FROM telegram_messages');
CALL zz_anon_run('telegram_users', 'DELETE FROM telegram_users');
CALL zz_anon_run('emails_sent', 'DELETE FROM emails_sent');
CALL zz_anon_run('touro_noti', 'DELETE FROM touro_noti');
CALL zz_anon_run('MyAthensTransfersAPP_users_copy', 'DELETE FROM MyAthensTransfersAPP_users_copy');
CALL zz_anon_run('MyAthensTransfersAPP_settings_copy', 'DELETE FROM MyAthensTransfersAPP_settings_copy');
CALL zz_anon_run('calusers', 'DELETE FROM calusers');
CALL zz_anon_run('testapi', 'DELETE FROM testapi');
CALL zz_anon_run('rc_resourcedetails', 'UPDATE rc_resourcedetails SET phone = NULL');
CALL zz_anon_run('review_links', 'UPDATE review_links SET ReviewLink = ''https://www.example.com/review''');

-- Secrets.
CALL zz_anon_run('elorus_Settings', 'UPDATE elorus_Settings SET apikey = NULL, OrgID = NULL');

-- ---- 8. Put the money columns back -------------------------------------------------
UPDATE transfers t
JOIN zz_anon_money b ON b.TransferID = t.TransferID
SET t.vid = b.vid, t.InvoicedRcvd = b.InvoicedRcvd, t.Platform = b.Platform, t.PlatformPrice = b.PlatformPrice,
    t.PurchasePrice = b.PurchasePrice, t.PricePaid = b.PricePaid,
    t.ModifiedTimestamp = t.ModifiedTimestamp;

-- ---- 9. Demo logins (password: Demo2026) --------------------------------------------
SET @pw = '$2y$10$dWrlvBeOcaFFdFDEYbwaaOhG2A9yMyR7X5vTp1Ck.rXci6fyHHi.G';
SET @id0 = (SELECT IFNULL(MAX(ID), 0) FROM MyAthensTransfersAPP_users);

INSERT INTO MyAthensTransfersAPP_users (ID, username, password, email, fullname, groupid, active, PasswordStatus) VALUES
  (@id0 + 1, 'demo',    @pw, 'demo@example.com',    'Demo Admin',   'Admin',   1, NULL),
  (@id0 + 2, 'office',  @pw, 'office@example.com',  'Office Desk',  'Entry',   1, NULL),
  (@id0 + 3, 'driver',  @pw, 'driver@example.com',  'Demo Driver',  'Drivers', 1, NULL),
  (@id0 + 4, 'partner', @pw, 'partner@example.com', 'Demo Partner', 'Partner', 1, NULL);

-- The driver login drives the busiest active driver of the last 90 days.
SET @drv = (SELECT d.DriverID FROM drivers d
            LEFT JOIN vehicle_assignments a ON a.DriverID = d.DriverID AND a.AssignmentDate >= CURDATE() - INTERVAL 90 DAY
            WHERE d.Active = 1 GROUP BY d.DriverID ORDER BY COUNT(a.DriverID) DESC, d.DriverID LIMIT 1);
UPDATE drivers SET UserID = @id0 + 3 WHERE DriverID = @drv;
UPDATE MyAthensTransfersAPP_users u JOIN drivers d ON d.UserID = u.ID SET u.fullname = d.Fullname WHERE u.ID = @id0 + 3;

-- The partner login is the busiest customer of the last 12 months.
SET @cust = (SELECT t.cID_Customer FROM transfers t JOIN counterparties c ON c.CID = t.cID_Customer
             WHERE t.TransferDate >= CURDATE() - INTERVAL 12 MONTH AND t.Status <> 'Cancelled'
             GROUP BY t.cID_Customer ORDER BY COUNT(*) DESC LIMIT 1);
UPDATE counterparties SET UserID = @id0 + 4 WHERE CID = @cust;
UPDATE MyAthensTransfersAPP_users u JOIN counterparties c ON c.UserID = u.ID SET u.fullname = c.CounterShortName WHERE u.ID = @id0 + 4;

-- `demo` starts with admin's saved column layouts and menu.
DELETE FROM MyAthensTransfersAPP_settings WHERE USERNAME = 'demo';
SET @sid0 = (SELECT IFNULL(MAX(ID), 0) FROM MyAthensTransfersAPP_settings);
INSERT INTO MyAthensTransfersAPP_settings (ID, TYPE, NAME, USERNAME, COOKIE, SEARCH, TABLENAME)
SELECT @sid0 + ROW_NUMBER() OVER (ORDER BY ID), TYPE, NAME, 'demo', COOKIE, SEARCH, TABLENAME
FROM MyAthensTransfersAPP_settings WHERE USERNAME = 'admin';

-- ---- 10. Clean up -------------------------------------------------------------------
DROP TABLE zz_anon_money;
DROP TABLE zz_anon_users;
DROP TABLE zz_anon_names;
DROP TABLE zz_anon_cp;
DROP FUNCTION zz_anon_guest;
DROP FUNCTION zz_anon_greek;
DROP FUNCTION zz_anon_company;
DROP FUNCTION zz_anon_phone;
DROP FUNCTION zz_anon_scrubbed;
DROP PROCEDURE zz_anon_run;
DROP PROCEDURE zz_anon_scrub;
DROP PROCEDURE zz_anon_rename_users;

-- Done. Still to look at by hand (cannot be found automatically): names typed inside
-- notes (transfers Notes/DriverNotes, transfer notes, comments). To blank all transfer
-- notes instead, run:  UPDATE transfers SET Notes = '', DriverNotes = '', ModifiedTimestamp = ModifiedTimestamp;
