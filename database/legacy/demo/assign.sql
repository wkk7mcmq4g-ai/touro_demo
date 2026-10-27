-- =====================================================================================
-- Demo showcase: give the vehicles their drivers, and every open transfer a vehicle or
-- a partner, so Dispatch, Ημερήσιο πλάνο and the dashboard look like a working day.
--
-- Run in HeidiSQL or phpMyAdmin on the demo database (it refuses the live ones), after
-- anonymise.sql. Safe to run again, e.g. every few days: it only fills what is empty and
-- never changes an assignment or a transfer that already has a vehicle or partner.
-- Data only: no table, view or trigger is changed; its helpers are dropped at the end.
--
-- 1. Drivers -> vehicles (Ανάθεση, vehicle_assignments), day by day:
--    - fleet vehicles only (active, not Sold / Rental / PVT, not sold by that day);
--    - active drivers who are not on leave (Άδειες Οδηγών) or off in the driver calendar
--      (ΡΕΠΟ / ΣΥΝΤΗΡΗΣΗ / ΑΔΕΙΑ) and not already on another vehicle that day;
--    - a driver keeps the vehicle he drove last, where possible.
--    The database's nightly event (Daily_Notifications, 09:41) copies today's assignments
--    to tomorrow. When it is active, filling days ahead would make it add a second row
--    for tomorrow, and every job would show twice in Dispatch. So with the event active
--    the script fills today (and tomorrow once the event has run today) and leaves the
--    later days to the event; with it off, it fills the whole period.
-- 2. Vehicle or partner -> transfers with none (not cancelled), in time order:
--    - a fleet vehicle that has a driver that day, seats >= pax, and no other job at that
--      time (job lengths as on Ημερήσιο πλάνο: arrival 90', departure 75', transfer 60',
--      tours from Προορισμοί or 4 h; plus a gap between jobs). Same car type first, then
--      the vehicle with the fewest jobs that day, then the smallest that fits;
--    - otherwise a coach partner, spread in proportion to how often each was used before.
--      A partner job with no purchase price gets a share of its sale price (setting below).
--    - jobs with no time yet (TBD) go to a partner.
--    The transfers trigger does the rest as when you pick a vehicle in the app (vid,
--    platform, "Δε θα λάβω" for own vehicles).
-- =====================================================================================

-- ---- Settings ---------------------------------------------------------------------------
SET @demo_from = CURDATE();      -- first day
SET @demo_days = 30;             -- number of days from @demo_from
SET @demo_gap = 30;              -- minutes kept free between two jobs of one vehicle
SET @demo_partner_cost = 0.75;   -- partner job without a purchase price: this share of the
                                 -- sale price (NULL = leave it 0)

-- ---- 0. Session settings, and never on a live database ---------------------------
-- Not strict: transfers timed "TBD - " recalculate their voucher-time columns on update.
SET @zz_old_sql_mode = @@SESSION.sql_mode;
SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION';

DROP PROCEDURE IF EXISTS zz_demo_guard;
DELIMITER $$
CREATE PROCEDURE zz_demo_guard()
BEGIN
  IF DATABASE() IS NULL OR DATABASE() IN ('kwi14599_myat', 'kwi14599_yomadic', 'kwi14599_lstours',
      'kwi14599_beskos', 'kwi14599_keep', 'kwi14599_tta', 'kwi14599_euro') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Stopped: this is a live database (or none is selected). Run on the demo copy only.';
  END IF;
END$$
DELIMITER ;
CALL zz_demo_guard();
DROP PROCEDURE zz_demo_guard;

-- ---- 1. Helpers (dropped at the end) ---------------------------------------------
DROP FUNCTION IF EXISTS zz_demo_len;
DROP PROCEDURE IF EXISTS zz_demo_drivers;
DROP PROCEDURE IF EXISTS zz_demo_transfers;
DROP TABLE IF EXISTS zz_demo_free, zz_demo_jobs, zz_demo_busy, zz_demo_partners;

DELIMITER $$

-- Minutes a job takes (as App\Filament\Pages\DayTimeline estimates it).
CREATE FUNCTION zz_demo_len(ttype VARCHAR(150) CHARSET utf8mb4, dest VARCHAR(500) CHARSET utf8mb4)
RETURNS INT NOT DETERMINISTIC READS SQL DATA
BEGIN
  DECLARE h DOUBLE DEFAULT NULL;
  IF ttype = 'Tour' THEN
    SELECT MAX(tr.TourDurationHours) INTO h FROM tours tr
    WHERE LOWER(TRIM(CONVERT(tr.TourName USING utf8mb4))) COLLATE utf8mb4_general_ci = LOWER(TRIM(dest)) COLLATE utf8mb4_general_ci
      AND tr.TourDurationHours > 0;
    RETURN IFNULL(ROUND(h * 60), 240);
  END IF;
  RETURN CASE ttype WHEN 'Arrival' THEN 90 WHEN 'Departure' THEN 75 WHEN 'Transfer' THEN 60 ELSE 60 END;
END$$

-- Drivers -> fleet vehicles with no driver yet, for each day from d_from to d_to.
CREATE PROCEDURE zz_demo_drivers(IN d_from DATE, IN d_to DATE)
BEGIN
  DECLARE dd DATE;
  DECLARE i INT;
  DECLARE cnt INT;
  DECLARE vid INT;
  DECLARE drv INT;
  SET dd = d_from;
  WHILE dd <= d_to DO
    DELETE FROM zz_demo_free;
    ALTER TABLE zz_demo_free AUTO_INCREMENT = 1;
    INSERT INTO zz_demo_free (VehicleID)
    SELECT v.VehicleID FROM vehicles v
    WHERE IFNULL(v.Active, 1) = 1
      AND (v.VehicleLicence IS NULL OR v.VehicleLicence NOT IN ('Sold', 'Rental', 'PVT'))
      AND (v.SoldDate IS NULL OR v.SoldDate > dd)
      AND NOT EXISTS (SELECT 1 FROM vehicle_assignments a WHERE a.VehicleID = v.VehicleID AND a.AssignmentDate = dd)
    ORDER BY IFNULL(v.`order`, 999), v.VehicleID;

    SET i = 1;
    SELECT COUNT(*) INTO cnt FROM zz_demo_free;
    WHILE i <= cnt DO
      SELECT VehicleID INTO vid FROM zz_demo_free WHERE n = i;
      SET drv = NULL;
      SELECT x.DriverID INTO drv FROM (
        SELECT d.DriverID, d.CalOrder,
               (SELECT a.VehicleID FROM vehicle_assignments a
                WHERE a.DriverID = d.DriverID AND a.VehicleID IS NOT NULL AND a.AssignmentDate < dd
                ORDER BY a.AssignmentDate DESC LIMIT 1) AS lastv
        FROM drivers d
        WHERE d.Active = 1
          AND (d.StartDate IS NULL OR d.StartDate <= dd)
          AND (d.EndDate IS NULL OR d.EndDate >= dd)
          AND NOT EXISTS (SELECT 1 FROM vehicle_assignments a WHERE a.DriverID = d.DriverID AND a.AssignmentDate = dd)
          AND NOT EXISTS (SELECT 1 FROM drivers_holidays_master h WHERE h.DriverID = d.DriverID
                          AND dd BETWEEN h.HolidayStartDate AND IFNULL(h.HolidayEndDate, h.HolidayStartDate))
          AND NOT EXISTS (SELECT 1 FROM schedule s WHERE s.driver_id = d.DriverID AND s.`date` = dd AND s.status IN (0, 5, 9))
      ) x
      -- his own vehicle first; then drivers whose own vehicle is not still waiting for its driver
      ORDER BY (x.lastv <=> vid) DESC,
               (x.lastv IN (SELECT f.VehicleID FROM zz_demo_free f WHERE f.n > i)) ASC,
               IFNULL(x.CalOrder, 0), x.DriverID
      LIMIT 1;
      IF drv IS NOT NULL THEN
        INSERT INTO vehicle_assignments (VehicleID, DriverID, AssignmentDate) VALUES (vid, drv, dd);
        SET @zz_drivers_added = @zz_drivers_added + 1;
      END IF;
      SET i = i + 1;
    END WHILE;
    SET dd = DATE_ADD(dd, INTERVAL 1 DAY);
  END WHILE;
END$$

-- A vehicle or a partner for every listed job (zz_demo_jobs), in order.
CREATE PROCEDURE zz_demo_transfers()
BEGIN
  DECLARE i INT DEFAULT 1;
  DECLARE cnt INT;
  DECLARE tid INT;
  DECLARE dd DATE;
  DECLARE refday DATE;
  DECLARE ss INT;
  DECLARE ll INT;
  DECLARE pp INT;
  DECLARE ct VARCHAR(150) CHARSET utf8mb4;
  DECLARE vid INT;
  DECLARE pid INT;
  DECLARE vname VARCHAR(250) CHARSET utf16;

  SELECT COUNT(*) INTO cnt FROM zz_demo_jobs;
  WHILE i <= cnt DO
    SELECT TransferID, d, s, len, pax, cartype INTO tid, dd, ss, ll, pp, ct FROM zz_demo_jobs WHERE n = i;
    -- NULL -> '' first, as the app saves it: the transfers trigger only reacts to a change
    -- from a value, so from NULL it would not set vid / platform / invoice.
    UPDATE transfers SET CoachCompanyID = '' WHERE TransferID = tid AND CoachCompanyID IS NULL;
    -- Days not filled yet (the nightly event will) use the last filled day's drivers.
    SET refday = IF(@zz_last_va IS NULL OR dd <= @zz_last_va, dd, @zz_last_va);
    SET vid = NULL;
    IF ss IS NOT NULL THEN
      SELECT v.VehicleID INTO vid FROM vehicles v
      WHERE IFNULL(v.Active, 1) = 1
        AND (v.VehicleLicence IS NULL OR v.VehicleLicence NOT IN ('Sold', 'Rental', 'PVT'))
        AND (v.SoldDate IS NULL OR v.SoldDate > dd)
        AND EXISTS (SELECT 1 FROM vehicle_assignments a WHERE a.VehicleID = v.VehicleID AND a.AssignmentDate = refday
                    AND a.DriverID IS NOT NULL AND IFNULL(a.Away, '') = '')
        AND (IFNULL(v.Passengers, 0) = 0 OR v.Passengers >= pp)
        AND NOT EXISTS (SELECT 1 FROM zz_demo_busy b WHERE b.VehicleID = v.VehicleID AND b.d = dd
                        AND b.s < ss + ll + @demo_gap AND ss < b.e + @demo_gap)
      ORDER BY (ct <> '' AND CONVERT(IFNULL(v.VehicleType, '') USING utf8mb4) COLLATE utf8mb4_general_ci = ct COLLATE utf8mb4_general_ci) DESC,
               (SELECT COUNT(*) FROM zz_demo_busy b2 WHERE b2.VehicleID = v.VehicleID AND b2.d = dd) ASC,
               IFNULL(NULLIF(v.Passengers, 0), 99) ASC, v.VehicleID
      LIMIT 1;
    END IF;

    IF vid IS NOT NULL THEN
      SELECT VehicleName INTO vname FROM vehicles WHERE VehicleID = vid;
      UPDATE transfers SET CoachCompanyID = vname WHERE TransferID = tid;
      INSERT INTO zz_demo_busy (VehicleID, d, s, e) VALUES (vid, dd, ss, ss + ll);
      SET @zz_own = @zz_own + 1;
    ELSE
      SET pid = NULL;
      SELECT CID INTO pid FROM zz_demo_partners ORDER BY given / weight ASC, weight DESC, CID LIMIT 1;
      IF pid IS NOT NULL THEN
        SELECT CoachName INTO vname FROM counterparties WHERE CID = pid;
        UPDATE transfers
        SET CoachCompanyID = vname,
            PurchasePrice = IF(@demo_partner_cost IS NOT NULL AND IFNULL(PurchasePrice, 0) = 0,
                               ROUND(IFNULL(SalePrice, 0) * @demo_partner_cost, 2), PurchasePrice)
        WHERE TransferID = tid;
        UPDATE zz_demo_partners SET given = given + 1 WHERE CID = pid;
        SET @zz_partner = @zz_partner + 1;
      ELSE
        SET @zz_left = @zz_left + 1;
      END IF;
    END IF;
    SET i = i + 1;
  END WHILE;
END$$

DELIMITER ;

-- ---- 2. Period and the nightly event ------------------------------------------------
SET @zz_to = DATE_ADD(@demo_from, INTERVAL GREATEST(@demo_days, 1) - 1 DAY);
SET @zz_event_on = @@GLOBAL.event_scheduler = 'ON' AND EXISTS (
  SELECT 1 FROM information_schema.EVENTS
  WHERE EVENT_SCHEMA = DATABASE() AND EVENT_NAME = 'Daily_Notifications' AND STATUS = 'ENABLED');
SET @zz_event_ran_today = EXISTS (
  SELECT 1 FROM information_schema.EVENTS
  WHERE EVENT_SCHEMA = DATABASE() AND EVENT_NAME = 'Daily_Notifications' AND LAST_EXECUTED >= CURDATE());
-- Last day the script gives drivers: the whole period, or (event on) today / tomorrow.
SET @zz_drivers_to = IF(@zz_event_on,
                        LEAST(@zz_to, IF(@zz_event_ran_today, DATE_ADD(CURDATE(), INTERVAL 1 DAY), CURDATE())),
                        @zz_to);

-- ---- 3. Drivers -> vehicles ---------------------------------------------------------
CREATE TABLE zz_demo_free (n INT AUTO_INCREMENT PRIMARY KEY, VehicleID INT NOT NULL) ENGINE=InnoDB;
SET @zz_drivers_added = 0;
CALL zz_demo_drivers(@demo_from, @zz_drivers_to);
SET @zz_last_va = (SELECT MAX(AssignmentDate) FROM vehicle_assignments
                   WHERE AssignmentDate <= @zz_to AND VehicleID IS NOT NULL AND DriverID IS NOT NULL);

-- ---- 4. Vehicle or partner -> transfers ---------------------------------------------
CREATE TABLE zz_demo_jobs (
  n INT AUTO_INCREMENT PRIMARY KEY,
  TransferID INT NOT NULL,
  d DATE NOT NULL,
  s INT NULL,                          -- start, minutes after midnight (NULL: no time yet)
  len INT NOT NULL,
  pax INT NOT NULL,
  cartype VARCHAR(150) CHARSET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB;

INSERT INTO zz_demo_jobs (TransferID, d, s, len, pax, cartype)
SELECT t.TransferID, t.TransferDate,
       IF(t.TransferTime_sort IS NULL, NULL, HOUR(t.TransferTime_sort) * 60 + MINUTE(t.TransferTime_sort)),
       zz_demo_len(CONVERT(IFNULL(t.TransferType, '') USING utf8mb4), CONVERT(IFNULL(t.`To`, '') USING utf8mb4)),
       CAST(IFNULL(NULLIF(REGEXP_SUBSTR(CONVERT(IFNULL(t.Pax, '') USING utf8mb4), '[0-9]+'), ''), '0') AS UNSIGNED),  -- "4", "4 pax", ""
       CONVERT(IFNULL(t.CarTypeID, '') USING utf8mb4)
FROM transfers t
WHERE t.TransferDate BETWEEN @demo_from AND @zz_to
  AND IFNULL(t.`Status`, '') <> 'Cancelled'
  AND IFNULL(t.CoachCompanyID, '') = ''
ORDER BY t.TransferDate, t.TransferTime_sort IS NULL, t.TransferTime_sort, t.TransferID;

-- Jobs our vehicles already have in the period.
CREATE TABLE zz_demo_busy (VehicleID INT NOT NULL, d DATE NOT NULL, s INT NOT NULL, e INT NOT NULL, KEY (VehicleID, d)) ENGINE=InnoDB;
INSERT INTO zz_demo_busy (VehicleID, d, s, e)
SELECT v.VehicleID, t.TransferDate, x.s, x.s + zz_demo_len(CONVERT(IFNULL(t.TransferType, '') USING utf8mb4), CONVERT(IFNULL(t.`To`, '') USING utf8mb4))
FROM transfers t
JOIN (SELECT TransferID, HOUR(TransferTime_sort) * 60 + MINUTE(TransferTime_sort) AS s FROM transfers
      WHERE TransferDate BETWEEN @demo_from AND @zz_to AND TransferTime_sort IS NOT NULL) x ON x.TransferID = t.TransferID
JOIN vehicles v ON CONVERT(v.VehicleName USING utf8mb4) COLLATE utf8mb4_general_ci = CONVERT(t.CoachCompanyID USING utf8mb4) COLLATE utf8mb4_general_ci
WHERE IFNULL(t.`Status`, '') <> 'Cancelled';

-- Coach partners, weighted by how many jobs each had before (only those used before, if any).
CREATE TABLE zz_demo_partners (CID INT PRIMARY KEY, weight INT NOT NULL, given INT NOT NULL DEFAULT 0) ENGINE=InnoDB;
INSERT INTO zz_demo_partners (CID, weight)
SELECT c.CID, 1 + (SELECT COUNT(*) FROM transfers t
                   WHERE CONVERT(t.CoachCompanyID USING utf8mb4) COLLATE utf8mb4_general_ci = CONVERT(c.CoachName USING utf8mb4) COLLATE utf8mb4_general_ci)
FROM counterparties c
WHERE c.IsCoach = 1
  AND (c.Active IS NULL OR c.Active NOT IN ('', '0', 'No', 'no', 'off'))
  AND IFNULL(c.CoachName, '') <> ''
  AND CONVERT(c.CoachName USING utf8mb4) NOT REGEXP '^(TB[AD]|NOT PROVIDED|NotRegistered)';
DELETE FROM zz_demo_partners WHERE weight = 1 AND EXISTS (SELECT 1 FROM (SELECT MAX(weight) AS m FROM zz_demo_partners) x WHERE x.m > 1);

SET @zz_own = 0, @zz_partner = 0, @zz_left = 0;
CALL zz_demo_transfers();

-- ---- 5. What was done --------------------------------------------------------------
SELECT CONCAT(DATE_FORMAT(@demo_from, '%d/%m/%Y'), ' – ', DATE_FORMAT(@zz_to, '%d/%m/%Y')) AS `Περίοδος`,
       IF(@zz_event_on,
          CONCAT('Ενεργό: οδηγοί ως ', DATE_FORMAT(@zz_drivers_to, '%d/%m'), ', τις επόμενες μέρες τους αντιγράφει το event'),
          'Ανενεργό: οδηγοί για όλη την περίοδο') AS `Νυχτερινό event`,
       @zz_drivers_added AS `Οδηγοί σε οχήματα`,
       @zz_own AS `Transfers σε δικό μας όχημα`,
       @zz_partner AS `Transfers σε συνεργάτη`,
       @zz_left AS `Χωρίς όχημα (κανένας συνεργάτης)`;

-- ---- 6. Clean up -------------------------------------------------------------------
DROP TABLE zz_demo_free, zz_demo_jobs, zz_demo_busy, zz_demo_partners;
DROP FUNCTION zz_demo_len;
DROP PROCEDURE zz_demo_drivers;
DROP PROCEDURE zz_demo_transfers;
SET SESSION sql_mode = @zz_old_sql_mode;
