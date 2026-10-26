-- Structure of the MyAT database (kwi14599_myat), exported from phpMyAdmin 2026-10-23.
-- Cleaned for portability: DEFINER clauses and the hardcoded database name removed. No data.
-- Load with: php artisan app:load-schema

-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `kwi14599_myat`
--

DELIMITER $$
--
-- Procedures
--
CREATE PROCEDURE `aAAA` (IN `dd` INT, IN `startDate` DATE)   BEGIN
DECLARE counter INT DEFAULT 0;
   DECLARE dt DATE DEFAULT startDate;
	WHILE counter <= dd DO
       INSERT INTO calendar_days ( CalDate) VALUES (dt);
 				SET dt = DATE_ADD(dt ,INTERVAL 1 DAY) ;
        SET counter = counter + 1;
    END WHILE;
END$$

CREATE PROCEDURE `Copy_Driver_Schedule` ()   BEGIN
INSERT INTO `schedule`  (driver_id, date, `status`)
SELECT s.driver_id  , DATE_ADD(s.date, INTERVAL 1 month) ss, s.`status`
FROM  `schedule` s
WHERE last_day(s.date)  = '2026-09-30'  AND s.driver_id  NOT IN (SELECT  (s.DriverID )
FROM `drivers` s
WHERE Flag IN (1) )



;
END$$

CREATE PROCEDURE `Create_Calendar_view` ()   BEGIN
DROP VIEW if EXISTS calcalendar;
CREATE VIEW calcalendar as
SELECT `t`.`TransferID` AS `id`,
       `t`.`TransferDate` AS `DateField`,
       `t`.`TransferType` AS `Description`,
       concat(`t`.`From`, ' - ', `t`.`To`) AS `Subject`,
       `t`.TransferTime_sort AS `TimeField`,
       cast(addtime(str_to_date(concat(`t`.`TransferDate`, ' ', `t`.`TransferTime_sort`), '%Y-%m-%d %H:%i:%s'), '0:25') AS TIME) AS `EndTime`,
       0 AS `DayEvent`,
       `t`.`TransferDate` AS `EndDate`,
       NULL AS `Period`,
       NULL AS `PeriodDays`,
       NULL AS `Recurrence`,
       `c`.`id` AS `Category`,
       NULL AS `OwnerID`,
       'Never' AS `RecurType`,
       `t`.`TransferDate` AS `RecurOnValue`,
       1 AS `RecurAfterValue`,
       0 AS `notiValue`,
       'day(s)' AS `notiName`,
       NULL AS `notiSent`,
       NULL AS `notiTime`,
       NULL AS `notiUse`,
       `t`.`TransferType` AS `TransferType`,
       `cnt`.`CounterShortName` AS `Coach`,
       `v`.`VehicleName` AS `VehicleName`,
       if(`v`.`VehicleName` IS NULL, 'No', 'Yes') AS `MyATCoach`,
       `t`.`CarTypeID` AS `CarType`
FROM (((`transfers` `t`
        LEFT JOIN `calcategory` `c` on(`t`.`TransferType` = `c`.`Category`))
       LEFT JOIN `vehicles` `v` on(`v`.`VehicleName` = `t`.`CoachCompanyID`))
      LEFT JOIN `counterparties` `cnt` on(`cnt`.`CounterShortName` = `t`.`CoachCompanyID`))
WHERE `t`.`Status` <> 'Cancelled';
END$$

CREATE PROCEDURE `Create_v_transfers_view` ()   BEGIN
DROP VIEW IF EXISTS v_transfers ;
CREATE VIEW v_transfers AS 
SELECT row_number() OVER (PARTITION BY ifnull(`d`.`Fullname`, `cnt`.`CoachName`),
                                       `t`.`TransferDate`
                          ORDER BY `t`.`TransferTime_sort`) AS `row_num`,
                         `t`.`TransferID` AS `TransferID`,
                         `t`.`ImportDate` AS `ImportDate`,
                         `t`.`cID_Customer` AS `cID_Customer`,
                         `t`.`Pax` AS `Pax`,
                         `t`.`SupplierCode` AS `SupplierCode`,
                         `t`.`TransferType` AS `TransferType`,
                         `t`.`TransferDate` AS `TransferDate`,
                         `t`.`TransferTime` AS `TransferTime`,
                         `t`.`From` AS `From`,
                         `t`.`Flight_Ship_Num_From` AS `Flight_Ship_Num_From`,
                         `t`.`To` AS `To`,
                         ifnull(concat(`cnt2`.`CounterShortName`, ' - ', `cnt2`.`Phones`), `t`.`Assistant`) AS `Assistant`,
                         `t`.`Assistant_Price` AS `Assistant_Price`,
                         if(`cnt2`.`CounterShortName` IS NULL
                            OR `cnt2`.`CounterShortName` = '', 'xxx', `cnt2`.`CounterShortName`) AS `cID_Assistant`,
                         `t`.`CoachCompanyID` AS `CoachCompanyID`,
                         `t`.`CarTypeID` AS `CarTypeID`,
                         `t`.`PassengerName` AS `PassengerName`,
                         `t`.`LeadName` AS `LeadName`,
                         `t`.`LeadPhone` AS `LeadPhone`,
                         `t`.`LesseeName` AS `LesseeName`,
                         if(`t`.`DriverNotes` IS NULL
                            OR `t`.`DriverNotes` = '', `t`.`Notes`, `t`.`DriverNotes`) AS `Notes`,
                         `t`.`Status` AS `Status`,
                         `t`.`Platform` AS `Platform`,
                         `t`.`ReferencePeriod` AS `ReferencePeriod`,
                         `t`.`PurchasePrice` AS `PurchasePrice`,
                         `t`.`AssistantStatus` AS `AssistantStatus`,
                         `t`.`CarTypeID` AS `CarType`,
                         `v`.`VehicleName` AS `VehicleName`,
                         ifnull(`d`.`Fullname`, `cnt`.`CoachName`) AS `Fullname`,
                         `d`.`LicenceNumber` AS `LicenceNumber`,
                         `d`.`IDNumber` AS `IDNumber`,
                         ifnull(`u`.`username`, `u2`.`username`) AS `username`,
                         ifnull(`t`.`Voucher_StartDt`, `t`.`Voucher_Start_Date_Calc`) AS `StartDate`,
                         ifnull(`t`.`Voucher_StartTime`, `t`.`Voucher_Start_StartTime_Calc`) AS `StartTime`,
                         ifnull(`t`.`Voucher_EndDt`, `t`.`Voucher_End_Date_Calc`) AS `EndDate`,
                         ifnull(`t`.`Voucher_EndTime`, `t`.`Voucher_End_Time_Calc`) AS `EndTime`,
                         `t`.`PlatformPrice` AS `PlatformPrice`,
                         `v`.`LicencePlate` AS `LicencePlate`,
                         date_format(timediff(str_to_date(concat(ifnull(`t`.`Voucher_EndDt`, `t`.`Voucher_End_Date_Calc`), ' ', ifnull(`t`.`Voucher_EndTime`, `t`.`Voucher_End_Time_Calc`)), '%Y-%m-%d %H:%i:%s'), str_to_date(concat(ifnull(`t`.`Voucher_StartDt`, `t`.`Voucher_Start_Date_Calc`), ' ', ifnull(`t`.`Voucher_StartTime`, `t`.`Voucher_Start_StartTime_Calc`)), '%Y-%m-%d %H:%i:%s')), '%H:%i') AS `duration`,
                         ifnull(`d`.`Email`, `cnt`.`Email`) AS `Email`,
                         ifnull(`v`.`VehicleLicence`, 0) AS `VehicleLicence`,
                         `v`.`VoucherName` AS `VoucherName`,
                         concat(dayofmonth(`t`.`TransferDate`), if(`d`.`DriverID` IS NULL, `cnt`.`CID` + 1000, `d`.`DriverID` + 2000)) / 1 AS `aid`,
                         `cnt2`.`AssistantNum` AS `AssistantNum`,
                         `cnt2`.`AssistantAMKA` AS `AssistantAMKA`,
                         `cnt2`.`VAT` AS `VAT`,
                         `trs`.`TourDescription` AS `TourDescription`,
                         `d`.`MobilePhone` AS `DriverPhone`,
                         `cnt2`.`Email` AS `AssistantEmail`,
                         concat(dayofmonth(`t`.`TransferDate`), `cnt2`.`CID`) / 1 AS `assistantid`,
                         `t`.`Attachments` AS `Attachments`,
                         `t`.`FLNM` AS `FLNM`
FROM ((((((((`transfers` `t`
             LEFT JOIN `vehicles` `v` on(`t`.`CoachCompanyID` = `v`.`VehicleName`))
            LEFT JOIN `vehicle_assignments` `va` on(`va`.`VehicleID` = `v`.`VehicleID`
                                                    AND `va`.`AssignmentDate` = `t`.`TransferDate`))
           LEFT JOIN `drivers` `d` on(`d`.`DriverID` = `va`.`DriverID`))
          LEFT JOIN `MyAthensTransfersAPP_users` `u` on(`d`.`UserID` = `u`.`ID`))
         LEFT JOIN `counterparties` `cnt` on(`cnt`.`CoachName` = `t`.`CoachCompanyID`))
        LEFT JOIN `MyAthensTransfersAPP_users` `u2` on(`u2`.`ID` = `cnt`.`UserID`))
       LEFT JOIN `counterparties` `cnt2` on(`cnt2`.`AssistantName` = if(ifnull(`t`.`Assistant`, '') = '', 'xxx', `t`.`Assistant`)))
      LEFT JOIN `tours` `trs` on(`trs`.`TourName` = `t`.`To`))
WHERE `t`.`Status` <> 'Cancelled'
  AND ifnull(`d`.`Fullname`, `cnt`.`CoachName`) IS NOT NULL
  AND `t`.`TransferDate` BETWEEN curdate() + interval 0 DAY AND curdate() + interval 4 DAY
ORDER BY ifnull(`d`.`Fullname`, `cnt`.`CoachName`),
         `t`.`TransferDate`,
         `t`.`TransferTime_sort`;
    DROP VIEW IF EXISTS v_transfers2;
CREATE VIEW v_transfers2 AS 
    SELECT row_number() OVER (PARTITION BY ifnull(`d`.`Fullname`, `cnt`.`CoachName`),
                                       `t`.`TransferDate`
                          ORDER BY `t`.`TransferTime_sort`) AS `row_num`,
                         `t`.`TransferID` AS `TransferID`,
                         `t`.`ImportDate` AS `ImportDate`,
                         `t`.`cID_Customer` AS `cID_Customer`,
                         `t`.`Pax` AS `Pax`,
                         `t`.`SupplierCode` AS `SupplierCode`,
                         `t`.`TransferType` AS `TransferType`,
                         `t`.`TransferDate` AS `TransferDate`,
                         `t`.`TransferTime` AS `TransferTime`,
                         `t`.`From` AS `From`,
                         `t`.`Flight_Ship_Num_From` AS `Flight_Ship_Num_From`,
                         `t`.`To` AS `To`,
                         `t`.`Assistant` AS `Assistant`,
                         `t`.`Assistant_Price` AS `Assistant_Price`,
                         `cnt2`.`CounterShortName` AS `cID_Assistant`,
                         `t`.`CoachCompanyID` AS `CoachCompanyID`,
                         `t`.`CarTypeID` AS `CarTypeID`,
                         `t`.`PassengerName` AS `PassengerName`,
                         `t`.`LeadName` AS `LeadName`,
                         `t`.`LeadPhone` AS `LeadPhone`,
                         `t`.`LesseeName` AS `LesseeName`,
                         if(`t`.`DriverNotes` IS NULL
                            OR `t`.`DriverNotes` = '', `t`.`Notes`, `t`.`DriverNotes`) AS `Notes`,
                         `t`.`Status` AS `Status`,
                         `t`.`Platform` AS `Platform`,
                         `t`.`ReferencePeriod` AS `ReferencePeriod`,
                         `t`.`PurchasePrice` AS `PurchasePrice`,
                         `t`.`AssistantStatus` AS `AssistantStatus`,
                         `t`.`CarTypeID` AS `CarType`,
                         `v`.`VehicleName` AS `VehicleName`,
                         ifnull(`d`.`Fullname`, `cnt`.`CoachName`) AS `Fullname`,
                         `d`.`LicenceNumber` AS `LicenceNumber`,
                         `d`.`IDNumber` AS `IDNumber`,
                         ifnull(`u`.`username`, `u2`.`username`) AS `username`,
                         ifnull(`t`.`Voucher_StartDt`, `t`.`Voucher_Start_Date_Calc`) AS `StartDate`,
                         ifnull(`t`.`Voucher_StartTime`, `t`.`Voucher_Start_StartTime_Calc`) AS `StartTime`,
                         ifnull(`t`.`Voucher_EndDt`, `t`.`Voucher_End_Date_Calc`) AS `EndDate`,
                         ifnull(`t`.`Voucher_EndTime`, `t`.`Voucher_End_Time_Calc`) AS `EndTime`,
                         `t`.`PlatformPrice` AS `PlatformPrice`,
                         `v`.`LicencePlate` AS `LicencePlate`,
                         date_format(timediff(str_to_date(concat(ifnull(`t`.`Voucher_EndDt`, `t`.`Voucher_End_Date_Calc`), ' ', ifnull(`t`.`Voucher_EndTime`, `t`.`Voucher_End_Time_Calc`)), '%Y-%m-%d %H:%i:%s'), str_to_date(concat(ifnull(`t`.`Voucher_StartDt`, `t`.`Voucher_Start_Date_Calc`), ' ', ifnull(`t`.`Voucher_StartTime`, `t`.`Voucher_Start_StartTime_Calc`)), '%Y-%m-%d %H:%i:%s')), '%H:%i') AS `duration`,
                         ifnull(`d`.`Email`, `u2`.`email`) AS `Email`,
                         ifnull(`v`.`VehicleLicence`, 0) AS `VehicleLicence`,
                         `v`.`VoucherName` AS `VoucherName`,
                         `cnt`.`UserID` AS `UserID`,  t.InvoicedRcvd
FROM (((((((`transfers` `t`
            LEFT JOIN `vehicles` `v` on(`t`.`CoachCompanyID` = `v`.`VehicleName`))
           LEFT JOIN `vehicle_assignments` `va` on(`va`.`VehicleID` = `v`.`VehicleID`
                                                   AND `va`.`AssignmentDate` = `t`.`TransferDate`))
          LEFT JOIN `drivers` `d` on(`d`.`DriverID` = `va`.`DriverID`))
         LEFT JOIN `MyAthensTransfersAPP_users` `u` on(`d`.`UserID` = `u`.`ID`))
        LEFT JOIN `counterparties` `cnt` on(`cnt`.`CoachName` = `t`.`CoachCompanyID`))
       LEFT JOIN `MyAthensTransfersAPP_users` `u2` on(`u2`.`ID` = `cnt`.`UserID`))
      LEFT JOIN `counterparties` `cnt2` on(`cnt2`.`CID` = `t`.`cID_Assistant`))
WHERE `t`.`Status` <> 'Cancelled'
  AND `cnt`.`CoachName` IS NOT NULL
  AND year(`t`.`TransferDate`) >= 2024
ORDER BY ifnull(`d`.`Fullname`, `cnt`.`CoachName`),
         `t`.`TransferDate`,
         `t`.`TransferTime_sort`;
END$$

CREATE PROCEDURE `Daily_Notifications` ()   BEGIN
/*
DECLARE done BOOLEAN DEFAULT FALSE;
DECLARE id INT;
DECLARE uname VARCHAR(255);
DECLARE my_cursor CURSOR FOR
    SELECT u.username FROM MyAthensTransfersAPP_users u WHERE u.groupid = 'Admin';
DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
OPEN my_cursor;
read_loop: LOOP
    -- Fetch the next row into variables
    FETCH my_cursor INTO uname;
    -- Check if no more rows are available
    IF done THEN
        LEAVE read_loop;
    END IF;
    -- Process the current row (replace this with your own logic)
    -- For example, you can print the values
	 INSERT INTO touro_noti (`message` ,`user` ,`provider` ,`title` ,`url` ,`icon` ,`created` ,`expire` ,`type` ,`group` )
				SELECT 
				CONCAT('{"message":"View ',  COUNT(*) ,' Tranfers","newWindow":false}') message,
				uname Username,
				NULL provider, 
				DATE_FORMAT(CURDATE(),'%d %M %Y') AS Title, 
				CONCAT('https://myat.touro.gr/transfers_list.php?q=%28TransferDate~equals~',DAY(CURDATE()),'%2F',Month(CURDATE()),'%2F',Year(CURDATE()),'~date2%29&orderby=dTransferDate;aTransferTime') url ,
				'glyphicon-info-sign'  icon , 
				CURRENT_TIMESTAMP CreateTmstp,
				ADDTIME(CURRENT_TIMESTAMP,'23:30') Expiration   ,
				0 AS `Type`,
				NULL AS `Group`
				FROM transfers t
				WHERE t.TransferDate = CURDATE() AND t.`Status` <> 'Cancelled';
    -- Perform other operations as needed
END LOOP;
CLOSE my_cursor;
*/
INSERT INTO vehicle_assignments (VehicleID, DriverID, AssignmentDate)
SELECT v.VehicleID,
       v.DriverID,
       DATE_ADD(CURRENT_DATE,INTERVAL 1 DAY)
FROM vehicle_assignments v
WHERE v.AssignmentDate = CURRENT_DATE ;
/*
SELECT SUM(f.Remaining) INTO @rem
FROM v_fuel_analysis f ;
 if @rem <=500 then
	 INSERT INTO touro_noti (`message` ,`user` ,`provider` ,`title` ,`url` ,`icon` ,`created` ,`expire` ,`type` ,`group` )
				SELECT 
				CONCAT('{"message":"Remaining Fuel cash is below 500 euros","newWindow":false}') message,
				'abil' Username,
				NULL provider, 
				'Fuel cash alert!' AS Title, 
				null url ,
				'glyphicon-info-sign'  icon , 
				CURRENT_TIMESTAMP CreateTmstp,
				ADDTIME(CURRENT_TIMESTAMP,'23:30') Expiration   ,
				0 AS `Type`,
				NULL AS `Group`;
END if; 
*/
 SET @flt:='{"q":"(TransferDate~between~01/02/2025~date2~/*curdt*/)","criteria":null,"f":"(CoachCompanyID~equals~VITO 2022)(CoachCompanyID~equals~E CLASS 2015)(TaxiReceipt_flag~equals~No)"}';
update MyAthensTransfersAPP_settings s SET s.SEARCH = REPLACE( @flt, '/*curdt*/', DATE_FORMAT(CURRENT_DATE, '%d/%m/%Y'))
WHERE `NAME` IN ('TAXI CHECK');
 SET @flt:='{"q":"(TransferDate~between~01/02/2025~date2~/*curdt*/)(TaxiReceipt~empty)(TaxiReceipt_flag~equals~Yes)","criteria":null,"f":"(CoachCompanyID~equals~VITO 2022)(CoachCompanyID~equals~E CLASS 2015)"}';
update MyAthensTransfersAPP_settings s SET s.SEARCH = REPLACE( @flt, '/*curdt*/', DATE_FORMAT(CURRENT_DATE, '%d/%m/%Y'))
WHERE `NAME` IN ('TAXI CHECK2');
END$$

CREATE PROCEDURE `exec` (IN `Param1` INT, IN `Param2` INT)   BEGIN
insert into test (col1, col2) values (Param1, Param2);
END$$

CREATE PROCEDURE `MoveToMyTransfers` (IN `_tid` INT, IN `_supid` TEXT)   BEGIN
if _supid = 'LS' then 


INSERT INTO  transfers 
(  ImportDate , SupplierCode , cID_Customer , `Status`, Transfertype, CarTypeID, Pax, TransferDate , TransferTime , `From` , `To` , Flight_Ship_Num_From,
Assistant, PassengerName, LeadName, LeadPhone, Notes, SalePrice,LesseeName,FLNM ,Attachments )
SELECT CURDATE() ,  CONCAT('LS - ' ,  v.TransferID), 63 , v.`Status`, v.TransferType, v.CarType, v.Pax, v.TransferDate, v.TransferTime, v.`From`, v.`To`, v.Flight_Ship_Num_From, 
v.Assistant, v.PassengerName, v.LeadName, v.LeadPhone, v.Notes, v.PurchasePrice ,(SELECT LesseName FROM counterparties WHERE cid = 63) ,  v.FLNM, v.Attachments
from kwi14599_lstours.v_transfers2 v
WHERE v.TransferID =   _tid ;
SET @id:=LAST_INSERT_ID();
INSERT INTO myat_transfers_files (TransferID, Attachment, Flnm)
SELECT @id, m.Attachment, m.Flnm
FROM kwi14599_lstours.ls_transfers_files m
WHERE m.TransferID = _tid;



ELSEIF _supid = 'Beskos' then


INSERT INTO  transfers 
(  ImportDate , SupplierCode , cID_Customer , `Status`, Transfertype, CarTypeID, Pax, TransferDate , TransferTime , `From` , `To` , Flight_Ship_Num_From,
Assistant, PassengerName, LeadName, LeadPhone, Notes, SalePrice,LesseeName,FLNM ,Attachments )
SELECT CURDATE() ,  CONCAT('Beskos - ' ,  v.TransferID), 16 , v.`Status`, v.TransferType, v.CarType, v.Pax, v.TransferDate, v.TransferTime, v.`From`, v.`To`, v.Flight_Ship_Num_From, 
v.Assistant, v.PassengerName, v.LeadName, v.LeadPhone, v.Notes, v.PurchasePrice ,(SELECT LesseName FROM counterparties WHERE cid = 16) ,  v.FLNM, v.Attachments
from kwi14599_beskos.v_transfers2 v
WHERE v.TransferID =   _tid ;
SET @id:=LAST_INSERT_ID();
INSERT INTO myat_transfers_files (TransferID, Attachment, Flnm)
SELECT @id, m.Attachment, m.Flnm
FROM kwi14599_beskos.beskos_transfers_files m
WHERE m.TransferID = _tid;



ELSEIF _supid = 'Keep' then

INSERT INTO  transfers 
(  ImportDate , SupplierCode , cID_Customer , `Status`, Transfertype, CarTypeID, Pax, TransferDate , TransferTime , `From` , `To` , Flight_Ship_Num_From,
Assistant, PassengerName, LeadName, LeadPhone, Notes, SalePrice,LesseeName,FLNM ,Attachments )
SELECT CURDATE() ,  CONCAT('Keep - ' ,  v.TransferID), 704 , v.`Status`, v.TransferType, v.CarType, v.Pax, v.TransferDate, v.TransferTime, v.`From`, v.`To`, v.Flight_Ship_Num_From, 
v.Assistant, v.PassengerName, v.LeadName, v.LeadPhone, v.Notes, v.PurchasePrice ,(SELECT LesseName FROM counterparties WHERE cid = 704) ,  v.FLNM, v.Attachments
from kwi14599_keep.v_transfers2 v
WHERE v.TransferID =   _tid ;
SET @id:=LAST_INSERT_ID();
INSERT INTO myat_transfers_files (TransferID, Attachment, Flnm)
SELECT @id, m.Attachment, m.Flnm
FROM kwi14599_keep.keep_transfers_files m
WHERE m.TransferID = _tid;



ELSEIF _supid = 'TTA' then


INSERT INTO  transfers 
(  ImportDate , SupplierCode , cID_Customer , `Status`, Transfertype, CarTypeID, Pax, TransferDate , TransferTime , `From` , `To` , Flight_Ship_Num_From,
Assistant, PassengerName, LeadName, LeadPhone, Notes, SalePrice,LesseeName,FLNM ,Attachments )
SELECT CURDATE() ,  CONCAT('TTA - ' ,  v.TransferID), 114 , v.`Status`, v.TransferType, v.CarType, v.Pax, v.TransferDate, v.TransferTime, v.`From`, v.`To`, v.Flight_Ship_Num_From, 
v.Assistant, v.PassengerName, v.LeadName, v.LeadPhone, v.Notes, v.PurchasePrice ,(SELECT LesseName FROM counterparties WHERE cid = 114) ,  v.FLNM, v.Attachments
from kwi14599_tta.v_transfers2 v
WHERE v.TransferID =   _tid ;
SET @id:=LAST_INSERT_ID();
INSERT INTO myat_transfers_files (TransferID, Attachment, Flnm)
SELECT @id, m.Attachment, m.Flnm
FROM kwi14599_tta.keep_transfers_files m
WHERE m.TransferID = _tid;


ELSEIF _supid = 'EURO' then


INSERT INTO  transfers 
(  ImportDate , SupplierCode , cID_Customer , `Status`, Transfertype, CarTypeID, Pax, TransferDate , TransferTime , `From` , `To` , Flight_Ship_Num_From,
Assistant, PassengerName, LeadName, LeadPhone, Notes, SalePrice,LesseeName,FLNM ,Attachments )
SELECT CURDATE() ,  CONCAT('EURO - ' ,  v.TransferID), 30 , v.`Status`, v.TransferType, v.CarType, v.Pax, v.TransferDate, v.TransferTime, v.`From`, v.`To`, v.Flight_Ship_Num_From, 
v.Assistant, v.PassengerName, v.LeadName, v.LeadPhone, v.Notes, v.PurchasePrice ,(SELECT LesseName FROM counterparties WHERE cid = 30) ,  v.FLNM, v.Attachments
from kwi14599_euro.v_transfers2 v
WHERE v.TransferID =   _tid ;
SET @id:=LAST_INSERT_ID();
INSERT INTO myat_transfers_files (TransferID, Attachment, Flnm)
SELECT @id, m.Attachment, m.Flnm
FROM kwi14599_euro.keep_transfers_files m
WHERE m.TransferID = _tid;


ELSEIF _supid = 'Yomadic' then


INSERT INTO  transfers 
(  ImportDate , SupplierCode , cID_Customer , `Status`, Transfertype, CarTypeID, Pax, TransferDate , TransferTime , `From` , `To` , Flight_Ship_Num_From,
Assistant, PassengerName, LeadName, LeadPhone, Notes, SalePrice,LesseeName,FLNM ,Attachments )
SELECT CURDATE() ,  CONCAT('Yomadic - ' ,  v.TransferID), 93 , v.`Status`, v.TransferType, v.CarType, v.Pax, v.TransferDate, v.TransferTime, v.`From`, v.`To`, v.Flight_Ship_Num_From, 
v.Assistant, v.PassengerName, v.LeadName, v.LeadPhone, v.Notes, v.PurchasePrice ,(SELECT LesseName FROM counterparties WHERE cid = 93) ,  v.FLNM, v.Attachments
from kwi14599_yomadic.v_transfers2 v
WHERE v.TransferID =   _tid ;
SET @id:=LAST_INSERT_ID();
INSERT INTO myat_transfers_files (TransferID, Attachment, Flnm)
SELECT @id, m.Attachment, m.Flnm
FROM kwi14599_yomadic.keep_transfers_files m
WHERE m.TransferID = _tid;



END if;


END$$

CREATE PROCEDURE `MoveToTransfers` (IN `tid` INT, IN `usr` TEXT)   BEGIN
SELECT Sum(p.PriceValue) INTO @tot_val
FROM price_list_load p 
WHERE p.ProductID <> '' and p.LoadID = tid ; 
INSERT INTO transfers (ImportDate, cID_Customer, Pax, SupplierCode, TransferType, 
								TransferDate, TransferTime, `From`, `To`, Flight_Ship_Num_From, Notes, 
								SalePrice, Assistant, PassengerName, CarTypeID, CreatedBy, CreatedTimestamp, 
								LeadName, LeadPhone, LesseeName,TransferLoadID)
SELECT ImportDate,
       cID_Customer,
       Pax,
        SupplierCode  ,
       TransferType,
       TransferDate,
       TransferTime,
       `From`,
       `To`,
       Flight_Ship_Num_From,
       Notes,
       IFNULL(@tot_val, l.Sale_Price),
       Assistant,
       PassengerList,
       CarType,
       IFNULL(l.Createdby, usr),
       IFNULL(l.CreatedDate, CURRENT_TIMESTAMP()),
       l.LeadName,
       l.LeadPhone ,
  (SELECT c.LesseName
   FROM counterparties c
   WHERE c.CID = l.cID_Customer
   LIMIT 1) , tid
FROM transfers_load l
WHERE l.LoadID = tid ;
SET @id:=LAST_INSERT_ID();
INSERT INTO price_list_transfers (TransferID, ProductID, PriceValue, ProductCarType,Category) 
SELECT @id, p.ProductID,p.PriceValue,p.ProductCarType,Category
FROM price_list_load p 
WHERE ProductID <> '' and p.LoadID = tid ;
 INSERT INTO transfer_notes ( TransferID, Note , NoteDate, CreatedTimestamp, CreatedBy, Category, `Status`)
SELECT @id, l.Note, l.NoteDate, l.CreatedTimestamp,l.CreatedBy, 'Operational',''
FROM transfer_notes_load l
WHERE l.LoadID = tid;
INSERT INTO myat_transfers_files (TransferID, Attachment, Flnm, `Type`)
SELECT @id, m.Attachment, m.Flnm, m.`Type`
FROM myat_transfers_load_files m
WHERE m.TransferID = tid;
UPDATE transfers_load l
SET l.Loaded = 1 WHERE l.LoadID = tid ;
DELETE FROM price_list_load WHERE LoadID = tid;
DELETE FROM transfer_notes_load WHERE LoadID = tid ;
END$$

CREATE PROCEDURE `PaymentAllocation` (IN `PmtId` INT, IN `PmtDate` DATE, IN `sup` INT, IN `PmtAmnt` DOUBLE)   BEGIN
SET @xx:=PmtAmnt;
SET @yp:=0;
INSERT INTO paymentlinks (PaymentID, TransferID, PaymentAmount)
 SELECT PmtId, d.TransferId, d.PriceRcvd1
FROM (
 SELECT *,
          if(remainingprice<0, Ypoloipo, Ypoloipo - remainingprice) PriceRcvd1
   FROM ( 
      SELECT b.* ,@yp:=cum_assets - @xx AS remainingprice, cum_assets - @xx < Ypoloipo chk
      FROM
        (SELECT t.TransferID,
                t.SalePrice,
                t.PriceRcvd,
                t.Assets Ypoloipo, TransferDate ,
                 SUM(t.Assets) OVER(ORDER BY t.TransferDate ASC , t.TransferID asc) cum_assets
         FROM transfers t  
         WHERE t.cID_Customer = sup
           AND t.TobeInvoiced <>'Όχι'
           AND `STATUS` = 'Completed' 
           AND t.Assets >0 ) b) c
           WHERE chk =1 
			  )d ;
UPDATE transfers tr
INNER JOIN paymentlinks p
ON p.TransferID = tr.TransferID
SET tr.PriceRcvd = ifnull(tr.PriceRcvd,0) + p.PaymentAmount
WHERE p.PaymentID =  PmtId ;
END$$

CREATE PROCEDURE `PaymentAllocation_Rem` (IN `PmtId` INT, IN `sup` INT, IN `PmtAmnt` DOUBLE)   BEGIN
SET @xx:=PmtAmnt;
SET @yp:=0;
INSERT INTO paymentlinks (PaymentID, TransferID, PaymentAmount)
 SELECT PmtId, d.TransferId, d.PriceRcvd1
FROM (
 SELECT *,
          if(remainingprice<0, Ypoloipo, Ypoloipo - remainingprice) PriceRcvd1
   FROM ( 
      SELECT b.* ,@yp:=cum_assets - @xx AS remainingprice, cum_assets - @xx < Ypoloipo chk
      FROM
        (SELECT t.TransferID,
                t.SalePrice,
                t.PriceRcvd,
                t.Assets Ypoloipo, TransferDate ,
                 SUM(t.Assets) OVER(ORDER BY t.TransferDate ASC , t.TransferID asc) cum_assets
         FROM transfers t  
         WHERE t.cID_Customer = sup
           AND t.TobeInvoiced <>'Όχι'
           AND `STATUS` = 'Completed' 
           AND t.Assets >0 ) b) c
           WHERE chk =1 
			  )d ;
UPDATE transfers tr
INNER JOIN paymentlinks p
ON p.TransferID = tr.TransferID
SET tr.PriceRcvd = ifnull(tr.PriceRcvd,0) + p.PaymentAmount
WHERE p.PaymentID =  PmtId  AND tr.Assets <> 0;
SELECT sum(p.PaymentAmount) INTO @rem
from paymentlinks p
WHERE p.PaymentID = PmtId; 
UPDATE PaymentsReceived r SET r.Remaining = r.PaymentAmount - @rem WHERE r.PaymentID = PmtId;
END$$

CREATE PROCEDURE `Refresh_Settings` ()   BEGIN
DELETE FROM  MyAthensTransfersAPP_settings 
 WHERE USERNAME IN ('abil_1') ;
INSERT into MyAthensTransfersAPP_settings (`TYPE`, `NAME`, USERNAME, COOKIE, SEARCH, TABLENAME)
 SELECT `TYPE`, `NAME`, USERNAME, COOKIE, SEARCH, TABLENAME
 FROM  MyAthensTransfersAPP_settings_copy m
 WHERE USERNAME IN ('abil_1');
END$$

CREATE PROCEDURE `test` ()   BEGIN
SELECT *
FROM price_list_load ;
END$$

CREATE PROCEDURE `Update_Drivers_Holidays` (IN `_dt` DATE)   BEGIN
INSERT INTO drivers_holidays_master ( DriverID, HolidayStartDate, HolidayEndDate, HolidayType)
SELECT r.resid,r.startdate, r.enddate, if(r.title = '0' , 'ΡΕΠΟ' , 'ΡΕΠΟ_B')
FROM rc_resourcedetails r
WHERE r.startdate >= _dt AND r.title IN (0,4) ;
END$$

CREATE PROCEDURE `Update_Import` (IN `usr` TEXT)   BEGIN
UPDATE transfers t
INNER JOIN import_transfers i
ON t.TransferID = i.TransferID
SET t.PricePaid = if(ISNULL( i.PricePaid), t.PricePaid, i.PricePaid) ,
t.PriceRcvd = if(ISNULL( i.PriceRcvd), t.PriceRcvd, i.PriceRcvd) ,
t.PlatformPrice = if(ISNULL( i.PlatformPrice), t.PlatformPrice, i.PlatformPrice) ,
t.InvoicedIssued = if(ISNULL( i.InvoicedIssued), t.InvoicedIssued, i.InvoicedIssued) ,
t.InvoicedRcvd = if(ISNULL( i.InvoicedRcvd), t.InvoicedRcvd, i.InvoicedRcvd) ,
t.SalesInvoiceNum = if(ISNULL( i.SalesInvoiceNum), t.SalesInvoiceNum, i.SalesInvoiceNum) ,
t.PurchaseInvoiceNum = if(ISNULL( i.PurchaseInvoiceNum), t.PurchaseInvoiceNum, i.PurchaseInvoiceNum) 
WHERE i.userid = usr;
DELETE FROM import_transfers where userid = usr;
END$$

CREATE PROCEDURE `Update_leads` ()   BEGIN
UPDATE transfers t
SET t.LeadPhone = case when  LEft(trim(SPLIT_STR(t.Notes, '/', 2)),1) REGEXP '^[0-9]+$' then trim(SPLIT_STR(t.Notes, '/', 2)) 
when LEFT(trim(SPLIT_STR(t.Notes, '/', 2)),1)   = '+' then trim(SPLIT_STR(t.Notes, '/', 2)) 
ELSE '' END ;
UPDATE transfers t
SET t.LeadName=  Trim(SPLIT_STR(t.Notes, '/', 1));
END$$

CREATE PROCEDURE `update_Tours_tbl` ()   BEGIN
INSERT INTO tours(Tourname)
SELECT DISTINCT tt FROM (
 SELECT DISTINCT `From` tt FROM transfers t
 UNION all
SELECT  Distinct t.`To`
FROM transfers t
WHERE ifnull(t.TransferType,'') <> 'Tour' ) a
WHERE ifnull(tt,'') <> '';
INSERT INTO tours(DestinationType,Tourname)
SELECT
DISTINCT t.TransferType, t.`To`
FROM transfers t
WHERE t.TransferType = 'Tour';
END$$

CREATE PROCEDURE `Update_Transfers_load_` ()   BEGIN
UPDATE transfers_load l INNER JOIN (
SELECT l.LoadID, Sum(l.PriceValue) PriceValue
FROM price_list_load l
GROUP BY 1  ) p
ON l.LoadID  = p.LoadID
SET l.SalePrice = PriceValue ;
END$$

CREATE PROCEDURE `Update_Transfers_Price` (IN `tid` INT, IN `usr` TEXT)   BEGIN
UPDATE transfers l INNER JOIN (
SELECT l.TransferID, Sum(l.PriceValue) PriceValue
FROM price_list_transfers l
WHERE l.TransferID = tid
GROUP BY 1  ) p
ON l.TransferID  = p.TransferID
SET l.SalePrice = PriceValue,
l.ModifiedBy = usr,
l.ModifiedTimestamp = CURRENT_TIMESTAMP() ;
END$$

CREATE PROCEDURE `VehicleAssignments_NewDay` ()   BEGIN
SELECT max(a.AssignmentDate) INTO @dt
FROM vehicle_assignments a ;
INSERT INTO vehicle_assignments (VehicleId, DriverID, AssignmentDate)
SELECT a.VehicleID, a.DriverID, DATE_ADD(@dt,INTERVAL 1 day)
 FROM vehicle_assignments a
 WHERE a.AssignmentDate = @dt ;
END$$

--
-- Functions
--
CREATE FUNCTION `aa` (`cidd` INT) RETURNS TINYINT(4)  BEGIN
if cidd  in (SELECT cid FROM counterparties c WHERE c.LeadName =1 ) then 
RETURN 1 ;
ELSE
RETURN 0 ;
END if;
END$$

CREATE FUNCTION `Check_Read_Receipt` (`_aid` INT, `_dt` DATE) RETURNS INT(11)  BEGIN


RETURN(
SELECT COUNT(*)
FROM emails_sent e
WHERE e.aID = _aid AND e.TransferDate = _dt);


END$$

CREATE FUNCTION `check_wcr` (`_cid` INT) RETURNS TEXT CHARSET utf8mb3 COLLATE utf8mb3_general_ci  BEGIN
RETURN (SELECT c.WCR FROM counterparties c WHERE c.CID = _cid);
END$$

CREATE FUNCTION `Copy_Transfer` (`Param1` INT) RETURNS INT(11)  BEGIN
INSERT INTO transfers (`ImportDate` , 
`cID_Customer` , 
`Pax` , 
`SupplierCode` , 
`TransferType` , 
`TransferDate` , 
`TransferTime` , 
`From` , 
`Flight_Ship_Num_From` , 
`To` , 
`Assistant` , 
`Assistant_Price` , 
`cID_Assistant` , 
`CoachCompanyID` , 
`CarTypeID` , 
`PassengerName` , 
`LeadName` , 
`LeadPhone` , 
`LesseeName` , 
`Notes` , 
`DriverNotes` , 
`Status` , 
`AssistantStatus` )
SELECT `ImportDate` , 
 `cID_Customer` , 
`Pax` , 
`SupplierCode` , 
`TransferType` , 
`TransferDate` , 
`TransferTime` , 
`From` , 
`Flight_Ship_Num_From` , 
`To` , 
`Assistant` , 
`Assistant_Price` , 
`cID_Assistant` , 
`CoachCompanyID` , 
`CarTypeID` , 
`PassengerName` , 
`LeadName` , 
`LeadPhone` , 
`LesseeName` , 
`Notes` , 
`DriverNotes` , 
`Status` , 
`AssistantStatus`  
FROM transfers t
WHERE t.TransferID = Param1 ;
return LAST_INSERT_ID();
END$$

CREATE FUNCTION `GetVouchername` (`_vname` CHAR(50)) RETURNS INT(11)  BEGIN
RETURN (
SELECT COUNT(*) FROM vehicles v 
WHERE v.VehicleName COLLATE utf16_unicode_ci  = _vname   
);
END$$

CREATE FUNCTION `Get_Driver_name` (`_coachid` TEXT CHARACTER SET utf16 COLLATE utf16_general_ci, `_tdate` DATE) RETURNS TEXT CHARSET utf8mb3 COLLATE utf8mb3_general_ci  BEGIN


     RETURN     (  IFNULL( IFNULL(   (SELECT Concat(d.Fullname )
                 FROM vehicle_assignments va
                 INNER JOIN vehicles v ON va.VehicleID = v.VehicleID
                 INNER JOIN drivers d ON d.DriverID = va.DriverID
                 WHERE va.AssignmentDate = _tdate
                   AND v.VehicleName = _coachid
                 LIMIT 1) ,   (SELECT Concat(c.CounterShortName ) FROM counterparties  c WHERE c.coachname = _coachid AND IFNULL(c.CoachName,'') <> ''  LIMIT 1)      ) ,'') );  


END$$

CREATE FUNCTION `Get_Review_Link` (`_frm` TEXT CHARACTER SET utf16 COLLATE utf16_general_ci, `_to` TEXT CHARACTER SET utf16 COLLATE utf16_general_ci, `_trns_type` TEXT CHARACTER SET utf16 COLLATE utf16_general_ci) RETURNS TEXT CHARSET utf16 COLLATE utf16_general_ci  BEGIN

 DECLARE rv_link1 TEXT

        CHARACTER SET utf16 COLLATE utf16_general_ci

        DEFAULT NULL;

    DECLARE rv_link2 TEXT

        CHARACTER SET utf16 COLLATE utf16_general_ci

        DEFAULT NULL;

    DECLARE rv_link3 TEXT

        CHARACTER SET utf16 COLLATE utf16_general_ci

        DEFAULT NULL;
        
        
SELECT rv.ReviewLink    INTO rv_link1
FROM   review_links rv
WHERE rv.`From` = ifnull(_frm,'')   AND rv.`To` = ifnull(_to,'')   AND rv.TransferType = ifnull(_trns_type,'')   ;



SELECT rv.ReviewLink  INTO rv_link2
FROM   review_links rv
WHERE rv.`From` = ifnull(_frm,'')   AND rv.`To` = ''   AND rv.TransferType = ifnull(_trns_type,'')  ;


SELECT rv.ReviewLink  INTO rv_link3
FROM   review_links rv
WHERE rv.`From` = ''    AND rv.`To` = ifnull(_to,'')   AND rv.TransferType = ifnull(_trns_type,'')   ;


RETURN COALESCE(rv_link1  , rv_link2 , rv_link3) ;


END$$

CREATE FUNCTION `Get_Review_option` (`_cid` INT) RETURNS TINYINT(4)  BEGIN
return(
SELECT c.GetReview
FROM counterparties c
WHERE c.CID = _cid ) ;
END$$

CREATE FUNCTION `Get_Taxi_flnm` (`_tid` INT) RETURNS INT(11)  BEGIN
RETURN(
SELECT COUNT(*) FROM myat_transfers_files f WHERE f.`Type` = 1363 AND f.TransferID=_tid );
END$$

CREATE FUNCTION `Get_Tour_Tickets` (`_tr` TEXT) RETURNS INT(11)  BEGIN
SET @tck:=0;
SELECT t.Tickets INTO @tck
 FROM tours t
WHERE t.TourName COLLATE UTF16_GENERAL_CI = _tr  ; 
RETURN @tck;
END$$

CREATE FUNCTION `Get_Vehicle_Taxi` (`_vnm` TEXT) RETURNS INT(11)  BEGIN
RETURN(
SELECT v.VehicleTaxi FROM vehicles v WHERE v.VehicleName = _vnm);
END$$

CREATE FUNCTION `Move_Transfers_from_Load` (`tid` INT, `usr` TEXT) RETURNS TINYINT(4)  BEGIN
SELECT Sum(p.PriceValue) INTO @tot_val
FROM price_list_load p 
WHERE p.LoadID = tid ; 
INSERT INTO transfers (ImportDate, cID_Customer, Pax, SupplierCode, TransferType, 
								TransferDate, TransferTime, `From`, `To`, Flight_Ship_Num_From,
								Notes, SalePrice, Assistant, PassengerName, CarTypeID,CreatedBy,CreatedTimestamp , LesseeName)
					SELECT ImportDate, cID_Customer, Pax, SupplierCode, TransferType, TransferDate, 
							 TransferTime, `From`, `To`, Flight_Ship_Num_From, Notes, @tot_val, Assistant, PassengerList, CarType,usr ,CURRENT_TIMESTAMP(),
							 (SELECT c.LesseName FROM counterparties c	WHERE c.CID = cID_Customer  LIMIT 1)
					FROM transfers_load		l
					WHERE l.LoadID = tid ;
SET @id:=LAST_INSERT_ID();
INSERT INTO price_list_transfers (TransferID, ProductID, PriceValue, ProductCarType) 
SELECT @id, p.ProductID,p.PriceValue,p.ProductCarType
FROM price_list_load p 
WHERE p.LoadID = tid ;
DELETE FROM transfers_load WHERE LoadID = tid ;
DELETE FROM price_list_load WHERE LoadID = tid;
RETURN  1;
END$$

CREATE FUNCTION `SPLIT_STR` (`x` VARCHAR(500), `delim` VARCHAR(50), `pos` INT) RETURNS VARCHAR(255) CHARSET utf8mb3 COLLATE utf8mb3_general_ci  BEGIN
    RETURN REPLACE(SUBSTRING(SUBSTRING_INDEX(x, delim, pos),
       LENGTH(SUBSTRING_INDEX(x, delim, pos -1)) + 1),
       delim, '');
RETURN 1;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `access_log`
--

CREATE TABLE `access_log` (
  `ID` int(11) NOT NULL,
  `userid` varchar(250) NOT NULL,
  `AccessStatus` varchar(250) NOT NULL,
  `LogDate` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `app_logs`
--

CREATE TABLE `app_logs` (
  `log_id` int(11) NOT NULL,
  `Timestamp` varchar(255) DEFAULT NULL,
  `Action` varchar(255) DEFAULT NULL,
  `TableName` varchar(255) DEFAULT NULL,
  `RecordID` varchar(255) DEFAULT NULL,
  `SqlQuery` varchar(255) DEFAULT NULL,
  `UserID` varchar(255) DEFAULT NULL,
  `ServerIP` varchar(255) DEFAULT NULL,
  `RequestUrl` text DEFAULT NULL,
  `RequestData` text DEFAULT NULL,
  `RequestCompleted` varchar(255) DEFAULT NULL,
  `RequestMsg` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `a_`
-- (See below for the actual view)
--
CREATE TABLE `a_` (
`1` int(1)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `calcalendar`
-- (See below for the actual view)
--
CREATE TABLE `calcalendar` (
`id` int(11)
,`DateField` date
,`Description` varchar(203)
,`Subject` varchar(203)
,`TimeField` time /* mariadb-5.3 */
,`EndTime` time /* mariadb-5.3 */
,`DayEvent` int(1)
,`EndDate` date
,`Period` binary(0)
,`PeriodDays` binary(0)
,`Recurrence` binary(0)
,`Category` int(11)
,`OwnerID` binary(0)
,`RecurType` varchar(5)
,`RecurOnValue` date
,`RecurAfterValue` int(1)
,`notiValue` int(1)
,`notiName` varchar(6)
,`notiSent` binary(0)
,`notiTime` binary(0)
,`notiUse` binary(0)
,`TransferType` binary(0)
,`Coach` binary(0)
,`VehicleName` binary(0)
,`MyATCoach` binary(0)
,`CarType` binary(0)
,`HolidayType` varchar(50)
);

-- --------------------------------------------------------

--
-- Table structure for table `calcategory`
--

CREATE TABLE `calcategory` (
  `id` int(11) NOT NULL,
  `Category` varchar(50) DEFAULT NULL,
  `Color` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf16 COLLATE=utf16_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `calendar`
--

CREATE TABLE `calendar` (
  `StartDate` date DEFAULT NULL,
  `EndDate` date DEFAULT NULL,
  `MONTH` int(11) DEFAULT NULL,
  `YEAR` int(11) DEFAULT NULL,
  `MONTHNAME` varchar(50) DEFAULT NULL,
  `QUARTER` int(11) DEFAULT NULL,
  `QuarterEnd` date DEFAULT NULL,
  `YearEnd` date DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `calendardates`
--

CREATE TABLE `calendardates` (
  `ReferenceDate` date DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `calendar_days`
--

CREATE TABLE `calendar_days` (
  `ID` int(11) NOT NULL,
  `CalDate` date DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `calfirstday`
--

CREATE TABLE `calfirstday` (
  `id` int(11) NOT NULL,
  `dayvalue` int(11) DEFAULT NULL,
  `dayname` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `calglobals`
--

CREATE TABLE `calglobals` (
  `id` int(11) NOT NULL,
  `TimePeriod` int(11) DEFAULT NULL,
  `FirstDayOfWeek` int(11) NOT NULL,
  `EditInPast` int(11) NOT NULL,
  `DefaultColor` varchar(200) NOT NULL DEFAULT '#FFFFFF',
  `HolidayColor` varchar(200) NOT NULL DEFAULT '#EDDFDF',
  `WeekEndColor` varchar(200) NOT NULL DEFAULT '#FEF6F6',
  `RangedColor` varchar(200) NOT NULL DEFAULT '#9FC6E7',
  `SubjectLength` int(11) NOT NULL,
  `DescriptionLength` int(11) NOT NULL,
  `CountEvents` int(11) NOT NULL,
  `StartTime` varchar(20) DEFAULT NULL,
  `EndTime` varchar(20) DEFAULT NULL,
  `ClickEvent` varchar(50) DEFAULT NULL,
  `cronTime` int(11) DEFAULT NULL,
  `cronTZone` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `calholidays`
--

CREATE TABLE `calholidays` (
  `id` int(11) NOT NULL,
  `name` varchar(200) DEFAULT NULL,
  `date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `callocale`
--

CREATE TABLE `callocale` (
  `id` int(11) NOT NULL,
  `fullcalendarlocale` varchar(10) DEFAULT NULL,
  `runnerlocale` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `calperiod`
--

CREATE TABLE `calperiod` (
  `id` int(11) NOT NULL,
  `Period` varchar(255) DEFAULT NULL,
  `ValueField` varchar(5) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `caltimezone`
--

CREATE TABLE `caltimezone` (
  `id` int(11) NOT NULL,
  `timezone` varchar(100) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `order` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `calusers`
--

CREATE TABLE `calusers` (
  `id` int(11) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `password` varchar(100) DEFAULT NULL,
  `email` varchar(50) DEFAULT NULL,
  `group` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `calweekdays`
--

CREATE TABLE `calweekdays` (
  `id` int(11) NOT NULL,
  `daynum` int(11) DEFAULT NULL,
  `dayname` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cash`
--

CREATE TABLE `cash` (
  `ID` int(11) NOT NULL,
  `DT` date NOT NULL,
  `ERB` double NOT NULL DEFAULT 0,
  `PrepaidERB` double NOT NULL DEFAULT 0,
  `PrepaidAlpha` double NOT NULL DEFAULT 0,
  `Viva` double NOT NULL DEFAULT 0,
  `Cash` double NOT NULL DEFAULT 0,
  `Paypal` double NOT NULL DEFAULT 0,
  `Ziraat` double NOT NULL DEFAULT 0,
  `BoxGR` double NOT NULL DEFAULT 0,
  `Box_Out` double NOT NULL DEFAULT 0,
  `LittleBox` double NOT NULL DEFAULT 0,
  `Total` double GENERATED ALWAYS AS (`ERB` + `PrepaidERB` + `PrepaidAlpha` + `Viva` + `Cash` + `Paypal` + `Ziraat` + `BoxGR` + `Box_Out` + `LittleBox`) VIRTUAL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `CashRegister`
--

CREATE TABLE `CashRegister` (
  `ID` int(11) DEFAULT NULL,
  `Before2023` double DEFAULT NULL,
  `Others` double DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `codes`
--

CREATE TABLE `codes` (
  `SupID` int(11) DEFAULT NULL,
  `OldTo` varchar(500) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT NULL,
  `NewTo` varchar(500) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `counterparties`
--

CREATE TABLE `counterparties` (
  `CID` int(11) NOT NULL,
  `CounterShortName` varchar(500) DEFAULT NULL,
  `DOY` varchar(150) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `LesseName` varchar(150) DEFAULT NULL,
  `VAT` varchar(150) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `BankName` varchar(150) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `InvoiceType` varchar(150) DEFAULT NULL,
  `PaymentType` varchar(150) DEFAULT NULL,
  `BankAccount` varchar(150) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `IBAN` varchar(150) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `Notes` varchar(1500) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `Phones` varchar(500) DEFAULT NULL,
  `Email` varchar(500) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `Active` varchar(15) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT 'Yes',
  `LastUpdatedBy` varchar(150) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `LastUpdatedDate` datetime DEFAULT NULL,
  `CreatedBy` varchar(150) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `CreatedDate` datetime DEFAULT NULL,
  `UserID` int(11) DEFAULT NULL,
  `Address` varchar(500) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `oldid` int(11) DEFAULT NULL,
  `IsSupplier` tinyint(4) DEFAULT 0,
  `IsAssistant` tinyint(4) DEFAULT 0,
  `IsCoach` tinyint(4) DEFAULT 0,
  `ElorusID` varchar(150) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT 'NotRegistered',
  `MydataDocumentType` varchar(150) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT 'NotRegistered',
  `MydataClassCategory` varchar(150) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT 'NotRegistered',
  `MydataClassType` varchar(150) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT 'NotRegistered',
  `DocumentType` varchar(150) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT 'NotRegistered',
  `CoachName` varchar(150) NOT NULL DEFAULT 'NotRegistered',
  `SupplierName` varchar(150) NOT NULL DEFAULT 'NotRegistered',
  `AssistantNum` varchar(150) DEFAULT NULL,
  `AssistantAMKA` varchar(150) DEFAULT NULL,
  `AssistantName` varchar(150) DEFAULT NULL,
  `LeadName` tinyint(4) DEFAULT 0,
  `WCR` varchar(50) DEFAULT NULL,
  `GetReview` smallint(6) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf16 COLLATE=utf16_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `drivers`
--

CREATE TABLE `drivers` (
  `DriverID` int(11) NOT NULL,
  `Fullname` varchar(150) DEFAULT NULL,
  `LicenceNumber` varchar(150) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `IDNumber` varchar(150) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `UserID` int(11) DEFAULT NULL,
  `Files` mediumtext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `MobilePhone` varchar(50) DEFAULT NULL,
  `Email` varchar(150) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `StartDate` date DEFAULT NULL,
  `EndDate` date DEFAULT NULL,
  `ContractType` varchar(150) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `LicenceType` varchar(150) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `Salary` double DEFAULT NULL,
  `Benefits` double DEFAULT NULL,
  `Active` tinyint(4) DEFAULT NULL,
  `Shortname` varchar(50) DEFAULT NULL,
  `CalOrder` int(11) DEFAULT 0,
  `Flag` int(11) DEFAULT NULL,
  `TelegramID` bigint(20) DEFAULT NULL,
  `CalColor` varchar(20) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf16 COLLATE=utf16_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `drivers_holidays_master`
--

CREATE TABLE `drivers_holidays_master` (
  `ID` int(11) NOT NULL,
  `DriverID` int(11) DEFAULT NULL,
  `HolidayStartDate` date DEFAULT NULL,
  `HolidayEndDate` date DEFAULT NULL,
  `HolidayID` bigint(21) DEFAULT NULL,
  `HolidayType` varchar(50) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT NULL,
  `HolidayDays` int(11) GENERATED ALWAYS AS (to_days(`HolidayEndDate`) - to_days(`HolidayStartDate`) + 1) VIRTUAL,
  `HolidayPeriod` date GENERATED ALWAYS AS (last_day(`HolidayStartDate`)) VIRTUAL,
  `ActiveHoliday` varchar(50) GENERATED ALWAYS AS (if(curdate() between `HolidayStartDate` and `HolidayEndDate`,'ΝΑΙ','ΟΧΙ')) VIRTUAL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `elorus_Settings`
--

CREATE TABLE `elorus_Settings` (
  `OrgID` varchar(50) DEFAULT NULL,
  `apikey` varchar(50) DEFAULT NULL,
  `PrivateIndividualID` varchar(50) DEFAULT NULL,
  `DefaultProductID` varchar(50) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `emails_sent`
--

CREATE TABLE `emails_sent` (
  `ID` int(11) NOT NULL,
  `TransferDate` date DEFAULT NULL,
  `aID` int(11) NOT NULL DEFAULT 0
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `ExpenseID` int(11) NOT NULL,
  `ExpenseDate` date NOT NULL,
  `ExpenseCategory` varchar(150) DEFAULT NULL,
  `ExpenseReason` varchar(500) DEFAULT NULL,
  `ExpenseAmountRcvd` double DEFAULT 0,
  `ExpenseAmountPaid` double DEFAULT 0,
  `VehicleID` int(11) DEFAULT NULL,
  `DriverID` int(11) DEFAULT NULL,
  `CreatedBy` varchar(150) DEFAULT NULL,
  `CreatedDate` datetime DEFAULT NULL,
  `MofifiedBy` varchar(150) DEFAULT NULL,
  `ModifiedDate` datetime DEFAULT NULL,
  `order` int(11) DEFAULT NULL,
  `Files` mediumtext DEFAULT NULL,
  `Flag` varchar(50) DEFAULT NULL,
  `Company` varchar(50) DEFAULT NULL,
  `ExpenseCategoryID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf16 COLLATE=utf16_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fuel_register`
--

CREATE TABLE `fuel_register` (
  `ID` int(11) NOT NULL,
  `FuelDate` date DEFAULT NULL,
  `DriverID` int(11) DEFAULT NULL,
  `VehicleID` int(11) DEFAULT NULL,
  `FuelAmount` double DEFAULT 0,
  `CreatedBy` varchar(50) DEFAULT NULL,
  `CreatedDate` date DEFAULT NULL,
  `ModifiedBy` varchar(50) DEFAULT NULL,
  `ModifiedDate` date DEFAULT NULL,
  `ApprovedBy` varchar(50) DEFAULT NULL,
  `ApprovedDate` date DEFAULT NULL,
  `ApprovedStatus` varchar(50) DEFAULT NULL,
  `ApprovedReason` varchar(500) DEFAULT NULL,
  `Files` mediumtext DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `MyAthensTransfersAPP6uggroups`
--

CREATE TABLE `MyAthensTransfersAPP6uggroups` (
  `GroupID` int(11) NOT NULL,
  `Label` varchar(300) DEFAULT NULL,
  `Provider` varchar(10) DEFAULT '',
  `Comment` mediumtext DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `MyAthensTransfersAPP6ugmembers`
--

CREATE TABLE `MyAthensTransfersAPP6ugmembers` (
  `UserName` varchar(255) NOT NULL,
  `GroupID` int(11) NOT NULL,
  `Provider` varchar(10) NOT NULL DEFAULT ''
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `myathenstransfersapp6ugmembers`
--

CREATE TABLE `myathenstransfersapp6ugmembers` (
  `UserName` varchar(255) NOT NULL,
  `GroupID` int(11) NOT NULL,
  `Provider` varchar(10) NOT NULL DEFAULT ''
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `MyAthensTransfersAPP6ugrights`
--

CREATE TABLE `MyAthensTransfersAPP6ugrights` (
  `TableName` varchar(255) NOT NULL,
  `GroupID` int(11) NOT NULL,
  `AccessMask` varchar(10) DEFAULT NULL,
  `Page` mediumtext DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `myathenstransfersapp6ugrights`
--

CREATE TABLE `myathenstransfersapp6ugrights` (
  `TableName` varchar(255) NOT NULL,
  `GroupID` int(11) NOT NULL,
  `AccessMask` varchar(10) DEFAULT NULL,
  `Page` mediumtext DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `MyAthensTransfersAPP_settings`
--

CREATE TABLE `MyAthensTransfersAPP_settings` (
  `ID` int(11) NOT NULL,
  `TYPE` int(11) DEFAULT 1,
  `NAME` mediumtext DEFAULT NULL,
  `USERNAME` mediumtext DEFAULT NULL,
  `COOKIE` varchar(500) DEFAULT NULL,
  `SEARCH` mediumtext DEFAULT NULL,
  `TABLENAME` varchar(300) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `MyAthensTransfersAPP_settings_copy`
--

CREATE TABLE `MyAthensTransfersAPP_settings_copy` (
  `ID` int(11) NOT NULL,
  `TYPE` int(11) DEFAULT 1,
  `NAME` mediumtext DEFAULT NULL,
  `USERNAME` mediumtext DEFAULT NULL,
  `COOKIE` varchar(500) DEFAULT NULL,
  `SEARCH` mediumtext DEFAULT NULL,
  `TABLENAME` varchar(300) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `MyAthensTransfersAPP_users`
--

CREATE TABLE `MyAthensTransfersAPP_users` (
  `ID` int(11) NOT NULL,
  `username` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `fullname` varchar(255) DEFAULT NULL,
  `groupid` varchar(255) DEFAULT NULL,
  `active` int(11) DEFAULT NULL,
  `ext_security_id` varchar(100) DEFAULT NULL,
  `userpic` mediumblob DEFAULT NULL,
  `apikey` varchar(50) DEFAULT NULL,
  `Createnew` varchar(250) DEFAULT NULL,
  `CreateNewDate` date DEFAULT NULL,
  `PasswordStatus` tinyint(4) DEFAULT NULL,
  `LastLoginDateTime` datetime DEFAULT NULL,
  `OrgID` varchar(150) DEFAULT NULL,
  `SupplierID` int(11) DEFAULT NULL,
  `remember_token` varchar(64) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `MyAthensTransfersAPP_users_copy`
--

CREATE TABLE `MyAthensTransfersAPP_users_copy` (
  `ID` int(11) NOT NULL,
  `username` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `fullname` varchar(255) DEFAULT NULL,
  `groupid` varchar(255) DEFAULT NULL,
  `active` int(11) DEFAULT NULL,
  `ext_security_id` varchar(100) DEFAULT NULL,
  `userpic` mediumblob DEFAULT NULL,
  `apikey` varchar(50) DEFAULT NULL,
  `Createnew` varchar(250) DEFAULT NULL,
  `CreateNewDate` date DEFAULT NULL,
  `PasswordStatus` tinyint(4) DEFAULT NULL,
  `LastLoginDateTime` datetime DEFAULT NULL,
  `OrgID` varchar(150) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `myat_audit_audit`
--

CREATE TABLE `myat_audit_audit` (
  `id` int(11) NOT NULL,
  `datetime` datetime NOT NULL,
  `ip` varchar(40) NOT NULL,
  `user` varchar(255) DEFAULT NULL,
  `table` varchar(300) DEFAULT NULL,
  `action` varchar(250) NOT NULL,
  `description` mediumtext DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `myat_partner_files`
--

CREATE TABLE `myat_partner_files` (
  `ID` int(11) NOT NULL,
  `cid` int(11) DEFAULT 0,
  `Attachment` mediumblob DEFAULT NULL,
  `Flnm` varchar(250) DEFAULT NULL,
  `Type` int(11) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `myat_transfers_files`
--

CREATE TABLE `myat_transfers_files` (
  `ID` int(11) NOT NULL,
  `TransferID` int(11) DEFAULT 0,
  `Attachment` mediumblob DEFAULT NULL,
  `Flnm` varchar(250) DEFAULT NULL,
  `Type` int(11) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Triggers `myat_transfers_files`
--
DELIMITER $$
CREATE TRIGGER `myat_transfers_files_before_insert` BEFORE INSERT ON `myat_transfers_files` FOR EACH ROW BEGIN
if NEW.Type = 1357 then 
	UPDATE transfers t SET t.FLNM = 'ΑΠΥ' WHERE t.TransferID = NEW.TransferID;
END if;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `myat_transfers_load_files`
--

CREATE TABLE `myat_transfers_load_files` (
  `ID` int(11) NOT NULL,
  `TransferID` int(11) DEFAULT 0,
  `Attachment` mediumblob DEFAULT NULL,
  `Flnm` varchar(250) DEFAULT NULL,
  `Type` varchar(250) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `optionvalues`
--

CREATE TABLE `optionvalues` (
  `OptionID` int(11) NOT NULL,
  `Option` varchar(500) DEFAULT NULL,
  `Value` varchar(500) DEFAULT NULL,
  `Group` varchar(150) DEFAULT NULL,
  `Color` varchar(150) DEFAULT NULL,
  `DriverView` int(11) DEFAULT 0,
  `PartnerView` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `paymentlinks`
--

CREATE TABLE `paymentlinks` (
  `ID` int(11) NOT NULL,
  `PaymentID` int(11) DEFAULT NULL,
  `TransferID` int(11) DEFAULT NULL,
  `PaymentAmount` double DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf16 COLLATE=utf16_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `PaymentsReceived`
--

CREATE TABLE `PaymentsReceived` (
  `PaymentID` int(11) NOT NULL,
  `CID_Customer` int(11) DEFAULT NULL,
  `PaymentDate` date DEFAULT NULL,
  `PaymentAmount` double DEFAULT NULL,
  `PaymentInvoiceNumber` varchar(150) DEFAULT NULL,
  `CreatedBy` varchar(150) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `CreatedDate` date DEFAULT NULL,
  `ModifiedBy` varchar(150) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `ModifiedDate` date DEFAULT NULL,
  `PaymentFile` mediumtext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `Remaining` double DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf16 COLLATE=utf16_general_ci;

--
-- Triggers `PaymentsReceived`
--
DELIMITER $$
CREATE TRIGGER `PaymentsReceived_after_delete` AFTER DELETE ON `PaymentsReceived` FOR EACH ROW BEGIN
UPDATE transfers t
INNER JOIN paymentlinks p ON t.TransferID = p.TransferID
SET t.PriceRcvd =ifnull(t.PriceRcvd,0) - p.PaymentAmount,
t.PurchaseInvoiceNum = REPLACE(t.PurchaseInvoiceNum, OLD.PaymentInvoiceNumber ,'')
WHERE p.PaymentID = OLD.PaymentID; 
DELETE
FROM paymentlinks
WHERE PaymentID = OLD.PaymentID;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `PaymentsReceived_after_insert` AFTER INSERT ON `PaymentsReceived` FOR EACH ROW BEGIN
  CALL PaymentAllocation(NEW.PaymentID, NEW.PaymentDate, NEW.CID_Customer, NEW.PaymentAmount );
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `PaymentsReceived_after_update` AFTER UPDATE ON `PaymentsReceived` FOR EACH ROW BEGIN
CALL PaymentAllocation(NEW.PaymentID, NEW.PaymentDate, NEW.CID_Customer, NEW.PaymentAmount );
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `PaymentsReceived_before_insert` BEFORE INSERT ON `PaymentsReceived` FOR EACH ROW BEGIN
SET @xx:=NEW.PaymentAmount ;
SET @yp:=0;
SET NEW.Remaining = NEW.PaymentAmount - (
 SELECT   sum(PriceRcvd1)
FROM ( 
 SELECT *,
          if(remainingprice<0, Ypoloipo, Ypoloipo - remainingprice) PriceRcvd1
   FROM ( 
      SELECT b.* ,@yp:=cum_assets - @xx AS remainingprice, cum_assets - @xx < Ypoloipo chk
      FROM
        (SELECT t.TransferID,
                t.SalePrice,
                t.PriceRcvd,
                t.Assets Ypoloipo, TransferDate ,
                 SUM(t.Assets) OVER(ORDER BY t.TransferDate ASC , t.TransferID asc) cum_assets
         FROM transfers t  
         WHERE t.cID_Customer = NEW.CID_Customer
           AND t.TobeInvoiced <>'Όχι'
           AND `STATUS` = 'Completed' 
           AND t.Assets >0 ) b) c
           WHERE chk =1  
			   )d ); 
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `PaymentsReceived_before_update` BEFORE UPDATE ON `PaymentsReceived` FOR EACH ROW BEGIN
UPDATE transfers t
INNER JOIN paymentlinks p ON t.TransferID = p.TransferID
SET t.PriceRcvd =t.PriceRcvd - p.PaymentAmount
WHERE p.PaymentID = NEW.PaymentID;
DELETE
FROM paymentlinks
WHERE PaymentID = NEW.PaymentID;
SET @xx:=NEW.PaymentAmount ;
SET @yp:=0;
SET NEW.Remaining = NEW.PaymentAmount - (
 SELECT   sum(PriceRcvd1)
FROM ( 
 SELECT *,
          if(remainingprice<0, Ypoloipo, Ypoloipo - remainingprice) PriceRcvd1
   FROM ( 
      SELECT b.* ,@yp:=cum_assets - @xx AS remainingprice, cum_assets - @xx < Ypoloipo chk
      FROM
        (SELECT t.TransferID,
                t.SalePrice,
                t.PriceRcvd,
                t.Assets Ypoloipo, TransferDate ,
                 SUM(t.Assets) OVER(ORDER BY t.TransferDate ASC , t.TransferID asc) cum_assets
         FROM transfers t  
         WHERE t.cID_Customer = NEW.CID_Customer
           AND t.TobeInvoiced <>'Όχι'
           AND `STATUS` = 'Completed' 
           AND t.Assets >0 ) b) c
           WHERE chk =1  
			   )d ); 
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `price_list`
--

CREATE TABLE `price_list` (
  `ID` int(11) NOT NULL,
  `cid_Customer` int(11) NOT NULL,
  `Product` varchar(250) NOT NULL DEFAULT '',
  `Product2` varchar(250) NOT NULL DEFAULT '',
  `CarType` varchar(250) NOT NULL DEFAULT '',
  `PriceValue` double NOT NULL DEFAULT 0,
  `Category` varchar(50) NOT NULL DEFAULT ''
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Table structure for table `price_list_load`
--

CREATE TABLE `price_list_load` (
  `ID` int(11) NOT NULL,
  `LoadID` int(11) NOT NULL,
  `ProductID` varchar(250) NOT NULL DEFAULT '',
  `PriceValue` double DEFAULT 0,
  `ProductCarType` varchar(50) NOT NULL DEFAULT '0',
  `Category` varchar(50) NOT NULL DEFAULT ''
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Triggers `price_list_load`
--
DELIMITER $$
CREATE TRIGGER `price_list_load_after_delete` AFTER DELETE ON `price_list_load` FOR EACH ROW BEGIN
SET @tid:=old.LoadID;
SELECT  SUM(PriceValue) INTO @PriceValue FROM price_list_load WHERE productid <> ''  AND LoadID = @tid ;
UPDATE transfers_load t
SET t.SalePrice_c =  @PriceValue
WHERE t.LoadID = @tid;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `price_list_load_after_insert` AFTER INSERT ON `price_list_load` FOR EACH ROW BEGIN
SELECT  SUM(PriceValue) INTO @PriceValue FROM price_list_load WHERE productid <> ''  AND LoadID = NEW.LoadID ;
UPDATE transfers_load t
SET t.SalePrice_c =  @PriceValue
WHERE t.LoadID = NEW.LoadID;
INSERT INTO test values(NEW.LoadID);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `price_list_load_after_update` AFTER UPDATE ON `price_list_load` FOR EACH ROW BEGIN
SELECT  SUM(PriceValue) INTO @PriceValue FROM price_list_load WHERE productid <> ''  AND LoadID = NEW.LoadID ;
UPDATE transfers_load t
SET t.SalePrice_c =  @PriceValue
WHERE t.LoadID = NEW.LoadID;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `price_list_old`
--

CREATE TABLE `price_list_old` (
  `ID` int(11) NOT NULL,
  `cid_Customer` int(11) NOT NULL,
  `Product` varchar(250) NOT NULL DEFAULT '',
  `Product2` varchar(250) NOT NULL DEFAULT '',
  `CarType` varchar(250) NOT NULL DEFAULT '',
  `PriceValue` double NOT NULL DEFAULT 0,
  `Category` varchar(50) NOT NULL DEFAULT ''
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `price_list_transfers`
--

CREATE TABLE `price_list_transfers` (
  `ID` int(11) NOT NULL,
  `TransferID` int(11) NOT NULL,
  `ProductID` varchar(250) NOT NULL DEFAULT '',
  `PriceValue` double DEFAULT 0,
  `ProductCarType` varchar(50) NOT NULL DEFAULT '0',
  `Category` varchar(50) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Triggers `price_list_transfers`
--
DELIMITER $$
CREATE TRIGGER `price_list_transfers_after_delete` AFTER DELETE ON `price_list_transfers` FOR EACH ROW BEGIN
UPDATE transfers t
INNER JOIN (
SELECT TransferID, SUM(PriceValue) PriceValue FROM price_list_transfers WHERE ProductID <> '' and TransferID = old.TransferID GROUP BY 1  ) p
 ON t.TransferID = p.TransferID
SET t.SalePrice =  p.PriceValue; 
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `price_list_transfers_after_insert` AFTER INSERT ON `price_list_transfers` FOR EACH ROW BEGIN
UPDATE transfers t
INNER JOIN (
SELECT TransferID, SUM(PriceValue) PriceValue FROM price_list_transfers WHERE ProductID <> '' and TransferID = NEW.TransferID GROUP BY 1  ) p
 ON t.TransferID = p.TransferID
SET t.SalePrice =  p.PriceValue;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `price_list_transfers_after_update` AFTER UPDATE ON `price_list_transfers` FOR EACH ROW BEGIN
UPDATE transfers t
INNER JOIN (
SELECT TransferID, SUM(PriceValue) PriceValue FROM price_list_transfers WHERE ProductID <> '' and TransferID = NEW.TransferID GROUP BY 1  ) p
 ON t.TransferID = p.TransferID
SET t.SalePrice =  p.PriceValue;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `price_list_transfers_copy`
--

CREATE TABLE `price_list_transfers_copy` (
  `ID` int(11) NOT NULL,
  `TransferID` int(11) NOT NULL,
  `ProductID` varchar(250) NOT NULL DEFAULT '',
  `PriceValue` double DEFAULT 0,
  `ProductCarType` varchar(50) NOT NULL DEFAULT '0',
  `Category` varchar(50) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Table structure for table `rc_categories`
--

CREATE TABLE `rc_categories` (
  `id` int(11) NOT NULL,
  `cname` varchar(50) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rc_holidays`
--

CREATE TABLE `rc_holidays` (
  `id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `hdate` date DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rc_locales`
--

CREATE TABLE `rc_locales` (
  `id` int(11) NOT NULL,
  `calendarlocale` varchar(10) DEFAULT NULL,
  `runnerlocale` varchar(30) DEFAULT NULL,
  `dateformat` varchar(20) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rc_resourcedetails`
--

CREATE TABLE `rc_resourcedetails` (
  `id` int(11) NOT NULL,
  `resid` int(11) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `startdate` datetime DEFAULT NULL,
  `enddate` datetime DEFAULT NULL,
  `unavailable` int(11) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rc_resources`
--

CREATE TABLE `rc_resources` (
  `id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `categoryid` int(11) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rc_resweekdays`
--

CREATE TABLE `rc_resweekdays` (
  `id` int(11) NOT NULL,
  `num` int(11) DEFAULT NULL,
  `name` varchar(20) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rc_settings`
--

CREATE TABLE `rc_settings` (
  `id` int(11) NOT NULL,
  `ndays` int(11) DEFAULT NULL,
  `cweekend` varchar(50) DEFAULT NULL,
  `choliday` varchar(50) DEFAULT NULL,
  `cday1` varchar(50) DEFAULT NULL,
  `cday2` varchar(50) DEFAULT NULL,
  `cunavailable` varchar(50) DEFAULT NULL,
  `cunavailabletext` varchar(50) DEFAULT NULL,
  `tooltipfields` varchar(1000) DEFAULT NULL,
  `adddays` varchar(50) DEFAULT NULL,
  `intName` varchar(50) DEFAULT NULL,
  `intValue` int(11) DEFAULT NULL,
  `weekends` varchar(50) DEFAULT NULL,
  `minTime` datetime DEFAULT NULL,
  `maxTime` datetime DEFAULT NULL,
  `clickEvent` varchar(50) DEFAULT NULL,
  `showTitle` int(11) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `review_links`
--

CREATE TABLE `review_links` (
  `ID` int(11) NOT NULL,
  `TransferType` varchar(500) NOT NULL,
  `From` varchar(500) NOT NULL,
  `To` varchar(500) NOT NULL,
  `ReviewLink` varchar(1500) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf16 COLLATE=utf16_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `schedule`
--

CREATE TABLE `schedule` (
  `id` int(11) NOT NULL,
  `driver_id` int(11) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `status` int(11) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sms`
--

CREATE TABLE `sms` (
  `ID` int(11) NOT NULL,
  `Type` varchar(50) DEFAULT NULL,
  `SMS_content` varchar(1000) DEFAULT NULL,
  `SMS_Header` varchar(1000) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf16 COLLATE=utf16_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `status_values`
--

CREATE TABLE `status_values` (
  `StatusValue` varchar(150) DEFAULT NULL,
  `Status_Group` varchar(150) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `telegram_messages`
--

CREATE TABLE `telegram_messages` (
  `id` int(11) NOT NULL,
  `direction` enum('in','out') NOT NULL,
  `chat_id` bigint(20) NOT NULL,
  `user_name` varchar(100) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `telegram_date` datetime DEFAULT NULL,
  `logged_at` datetime DEFAULT current_timestamp(),
  `telegram_msg_id` int(11) DEFAULT NULL,
  `reply_to` int(11) DEFAULT NULL,
  `tid` int(11) DEFAULT NULL,
  `tdate` date DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `telegram_users`
--

CREATE TABLE `telegram_users` (
  `id` int(11) NOT NULL,
  `telegram_id` bigint(20) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `language_code` varchar(10) DEFAULT NULL,
  `joined_at` datetime DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `test`
--

CREATE TABLE `test` (
  `test` int(11) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `testapi`
--

CREATE TABLE `testapi` (
  `ID` int(11) NOT NULL,
  `fname` varchar(50) DEFAULT NULL,
  `lname` varchar(50) DEFAULT NULL,
  `dt` date DEFAULT NULL,
  `val` double DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `touro_groups`
--

CREATE TABLE `touro_groups` (
  `GroupID` int(11) NOT NULL,
  `Group` varchar(50) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `touro_noti`
--

CREATE TABLE `touro_noti` (
  `id` int(11) NOT NULL,
  `message` mediumtext DEFAULT NULL,
  `user` varchar(255) DEFAULT NULL,
  `provider` varchar(5) DEFAULT NULL,
  `title` varchar(300) DEFAULT NULL,
  `url` mediumtext DEFAULT NULL,
  `icon` varchar(300) DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `expire` datetime DEFAULT NULL,
  `type` int(11) DEFAULT NULL,
  `group` varchar(200) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tours`
--

CREATE TABLE `tours` (
  `TourID` int(11) NOT NULL,
  `TourName` varchar(500) NOT NULL,
  `TourDescription` varchar(5000) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `TourDurationHours` double DEFAULT 0,
  `TourDurationDays` double DEFAULT 0,
  `order` int(11) DEFAULT NULL,
  `DestinationType` varchar(150) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `Files` longtext DEFAULT NULL,
  `Tickets` tinyint(4) DEFAULT 0
) ENGINE=MyISAM DEFAULT CHARSET=utf16 COLLATE=utf16_general_ci;

--
-- Triggers `tours`
--
DELIMITER $$
CREATE TRIGGER `tours_after_insert` AFTER INSERT ON `tours` FOR EACH ROW BEGIN
if NEW.TourName LIKE '%- MyAT' then 
UPDATE transfers t
SET t.TicketsFlag = NEW.Tickets
WHERE t.cID_Customer IN (11 , 12 , 17 , 721 , 18 , 37 , 38 , 73 , 117 , 118 ) AND t.`To` = NEW.TourName ;
END if;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tours_after_update` AFTER UPDATE ON `tours` FOR EACH ROW BEGIN
if NEW.TourName LIKE '%- MyAT' then 
UPDATE transfers t
SET t.TicketsFlag = NEW.Tickets
WHERE t.cID_Customer IN (11 , 12 , 17 , 721 , 18 , 37 , 38 , 73 , 117 , 118 ) AND t.`To` = NEW.TourName ;
END if;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `transfers`
--

CREATE TABLE `transfers` (
  `TransferID` int(11) NOT NULL,
  `ImportDate` date DEFAULT NULL,
  `cID_Customer` int(11) DEFAULT NULL,
  `Pax` varchar(50) DEFAULT NULL,
  `SupplierCode` varchar(50) DEFAULT NULL,
  `TransferType` varchar(150) DEFAULT NULL,
  `TransferDate` date DEFAULT NULL,
  `TransferTime` varchar(50) DEFAULT NULL,
  `TransferTime_sort` time GENERATED ALWAYS AS (if(str_to_date(`TransferTime`,'%H:%i') is null,NULL,str_to_date(`TransferTime`,'%H:%i'))) VIRTUAL,
  `From` varchar(150) DEFAULT NULL,
  `Flight_Ship_Num_From` varchar(150) DEFAULT NULL,
  `OLP` tinyint(4) DEFAULT 0,
  `To` varchar(150) DEFAULT NULL,
  `Assistant` varchar(500) DEFAULT NULL,
  `Assistant_Price` double DEFAULT NULL,
  `cID_Assistant` int(11) DEFAULT NULL,
  `CoachCompanyID` varchar(250) DEFAULT NULL,
  `vid` varchar(250) DEFAULT NULL,
  `CarTypeID` varchar(50) DEFAULT NULL,
  `PassengerName` varchar(1000) DEFAULT NULL,
  `LeadName` varchar(1000) DEFAULT NULL,
  `LeadPhone` varchar(250) DEFAULT NULL,
  `LesseeName` varchar(500) DEFAULT NULL,
  `Notes` varchar(4000) DEFAULT NULL,
  `DriverNotes` varchar(2500) DEFAULT NULL,
  `Status` varchar(150) DEFAULT 'Pending',
  `Platform` varchar(250) DEFAULT '',
  `ReferencePeriod` date GENERATED ALWAYS AS (last_day(`TransferDate`)) VIRTUAL,
  `ReferenceYear` int(11) GENERATED ALWAYS AS (year(`TransferDate`)) VIRTUAL,
  `PurchasePrice` double DEFAULT 0 COMMENT 'Τιμή Αγ.',
  `SalePrice` double DEFAULT 0 COMMENT 'Τιμή Πώλ.',
  `PricePaid` double DEFAULT 0 COMMENT 'Πληρ.',
  `PriceRcvd` double DEFAULT 0 COMMENT 'Εισπρ.',
  `Expense` double DEFAULT 0 COMMENT 'Έξοδα',
  `ExpenseRcvd` varchar(15) DEFAULT '' COMMENT 'Παρ. εξ.',
  `ExpensePaid` varchar(15) DEFAULT '' COMMENT 'Απ. Εξ.',
  `TobeInvoiced` varchar(50) DEFAULT '' COMMENT 'Τιμ.',
  `InvoicedIssued` varchar(50) DEFAULT '' COMMENT 'Έχω εκδ.',
  `InvoicedRcvd` varchar(50) DEFAULT '' COMMENT 'Έχω λάβει τιμ.',
  `SalesInvoiceNum` varchar(500) DEFAULT NULL COMMENT 'A/A Τιμολογίου',
  `PurchaseInvoiceNum` varchar(500) DEFAULT NULL,
  `PlatformPrice` double DEFAULT NULL,
  `Assets` double GENERATED ALWAYS AS (if(`Status` = 'Completed',ifnull(`SalePrice`,0) - ifnull(`PriceRcvd`,0),0)) STORED COMMENT 'Απαιτήσεις',
  `ExtraIncome` double DEFAULT 0,
  `Liabilities` double GENERATED ALWAYS AS (if(`Status` = 'Completed',ifnull(`PurchasePrice`,0) - ifnull(`PricePaid`,0),0)) STORED COMMENT 'Υποχρεώσεις',
  `CashRegister` double GENERATED ALWAYS AS (if(`Status` = 'Completed',ifnull(`PriceRcvd`,0) - ifnull(`PricePaid`,0) - ifnull(`Expense`,0) + ifnull(`ExtraIncome`,0),0)) STORED COMMENT 'Ταμείο',
  `Income` double GENERATED ALWAYS AS (if(`Status` = 'Completed',ifnull(`SalePrice`,0) - ifnull(`PurchasePrice`,0) - ifnull(`Expense`,0) + ifnull(`ExtraIncome`,0),0)) STORED COMMENT 'Έσοδο',
  `CreatedTimestamp` datetime DEFAULT NULL,
  `ModifiedTimestamp` datetime DEFAULT NULL,
  `CreatedBy` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `ModifiedBy` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `AssistantStatus` varchar(150) DEFAULT NULL,
  `VPOS` varchar(500) DEFAULT NULL,
  `PaymentType` varchar(150) DEFAULT NULL,
  `PaymentOnHoldFlag` varchar(50) DEFAULT '',
  `Voucher_StartDt` date DEFAULT NULL,
  `Voucher_StartTime` time DEFAULT NULL,
  `Voucher_EndDt` date DEFAULT NULL,
  `Voucher_EndTime` time DEFAULT NULL,
  `order` varchar(500) DEFAULT NULL,
  `OperationalIssues` int(11) DEFAULT NULL,
  `FinancialIssues` int(11) DEFAULT NULL,
  `OpenCharges` int(11) DEFAULT NULL,
  `TicketsRequested` varchar(150) DEFAULT NULL,
  `ElorusID` varchar(50) DEFAULT NULL,
  `order1` int(11) DEFAULT NULL,
  `Voucher_Start_Date_Calc` date GENERATED ALWAYS AS (cast(subtime(str_to_date(concat(convert(`TransferDate` using utf16),' ',`TransferTime`),'%Y-%m-%d %H:%i:%s'),'1:30') as date)) STORED,
  `Voucher_Start_StartTime_Calc` time GENERATED ALWAYS AS (cast(subtime(str_to_date(concat(convert(`TransferDate` using utf16),' ',`TransferTime`),'%Y-%m-%d %H:%i:%s'),'1:30') as time)) STORED,
  `Voucher_End_Date_Calc` date GENERATED ALWAYS AS (cast(addtime(str_to_date(concat(convert(`TransferDate` using utf16),' ',`TransferTime`),'%Y-%m-%d %H:%i:%s'),'1:30') as date)) STORED,
  `Voucher_End_Time_Calc` time GENERATED ALWAYS AS (cast(addtime(str_to_date(concat(convert(`TransferDate` using utf16),' ',`TransferTime`),'%Y-%m-%d %H:%i:%s'),'1:30') as time)) STORED,
  `OperationalIssuesFlag` varchar(50) GENERATED ALWAYS AS (if(ifnull(`OperationalIssues`,0) = 0,'No','Yes')) STORED,
  `FinancialIssuesFlag` varchar(50) GENERATED ALWAYS AS (if(ifnull(`FinancialIssues`,0) = 0,'No','Yes')) STORED,
  `OpenExpensesFlag` varchar(50) DEFAULT NULL,
  `OpenChargesFlag` varchar(50) GENERATED ALWAYS AS (if(ifnull(`OpenCharges`,0) = 0,'No','Yes')) STORED,
  `AssetsFlag` varchar(50) GENERATED ALWAYS AS (if(`Status` = 'Completed' and `Assets` > 0,'Ναί','Όχι')) VIRTUAL,
  `LiabilitiesFlag` varchar(50) GENERATED ALWAYS AS (if(`Status` = 'Completed' and `Liabilities` > 0,'Ναί','Όχι')) VIRTUAL,
  `Files` mediumtext DEFAULT NULL,
  `Attachments` mediumblob DEFAULT NULL,
  `FLNM` varchar(250) DEFAULT NULL,
  `DriverReceiptType` varchar(250) DEFAULT NULL,
  `DriverReceipt` int(11) DEFAULT 0,
  `TicketsValue` double DEFAULT NULL,
  `SupplierModified` int(11) DEFAULT 0,
  `TransferLoadID` int(11) DEFAULT NULL,
  `pricecheck` varchar(50) DEFAULT NULL,
  `TaxiReceipt` varchar(50) DEFAULT NULL,
  `TaxiReceiptDate` date DEFAULT NULL,
  `TaxiReceipt_Issued` varchar(50) DEFAULT 'No',
  `TaxiReceiptAmount` double DEFAULT NULL,
  `ElorusTaxiNumber` varchar(150) DEFAULT NULL,
  `TicketsFlag` tinyint(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf16 COLLATE=utf16_general_ci;

--
-- Triggers `transfers`
--
DELIMITER $$
CREATE TRIGGER `Check_New_Customer_After_Import_Transfer` AFTER INSERT ON `transfers` FOR EACH ROW BEGIN
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `transfers_before_insert` BEFORE INSERT ON `transfers` FOR EACH ROW BEGIN
		SET @vlic:='';
				 SELECT v.VehicleLicence INTO @vlic FROM vehicles v
								WHERE v.VehicleName = NEW.CoachCompanyID  LIMIT 1;
				SET @c_id:=0;
					SELECT  cid INTO @c_id FROM counterparties c WHERE c.CoachName = NEW.CoachCompanyID LIMIT 1;			
					if @c_id >0 then 
					SET NEW.vid= 'Third';
					SET NEW.InvoicedRcvd ='';
					SET NEW.Platform = 'Όχι';
					SET NEW.PlatformPrice = 0;
					Else
						if @vlic<>'' then
							SET NEW.vid= @vlic;
							SET NEW.PurchasePrice = 0;
							SET NEW.PricePaid = 0;
							SET NEW.InvoicedRcvd ='Δε θα λάβω';
							if @vlic ='IKE' then
								SET NEW.Platform = 'Ναί';
							else
								SET NEW.Platform = 'Όχι';
								SET NEW.PlatformPrice = 0;							
							END if;
						END if;	
				 END if;
if NEW.LesseeName = '' then
	if NEW.cID_Customer IN (SELECT cid FROM counterparties c WHERE c.LeadName = 1) then 
		SET NEW.LesseeName = NEW.LeadName;
	else
		SET NEW.LesseeName = (SELECT c.LesseName FROM counterparties c	WHERE c.CID = NEW.cID_Customer  LIMIT 1);
	END if;
END if;
if NEW.TobeInvoiced = '' then 
SET NEW.TobeInvoiced = (SELECT c.InvoiceType FROM counterparties c	WHERE c.CID = NEW.cID_Customer  LIMIT 1);
END if;
if NEW.PaymentType = '' then
SET NEW.PaymentType = (SELECT c.PaymentType FROM counterparties c	WHERE c.CID = NEW.cID_Customer  LIMIT 1);
END if;
if NEW.cID_Customer = 112 then 
SET NEW.DriverNotes = '*****';
END if;
if check_wcr(NEW.cID_Customer) IS NOT NULL  then 
SET NEW.DriverNotes = CONCAT(NEW.DriverNotes, '  ', check_wcr(NEW.cID_Customer)) ;
END if;
SET NEW.LeadName = REPLACE(NEW.LeadName,  '\r\n','') ;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `transfers_before_update` BEFORE UPDATE ON `transfers` FOR EACH ROW BEGIN
if (NEW.CoachCompanyID <> OLD.CoachCompanyID) OR (IFNULL(OLD.CoachCompanyID,'')='' IS NULL AND IFNULL(NEW.CoachCompanyID,'') <> '')  then 
				SET @vlic:='';
				 SELECT v.VehicleLicence INTO @vlic FROM vehicles v WHERE v.VehicleName = NEW.CoachCompanyID  LIMIT 1;
				SET @c_id:=0;
					SELECT  cid INTO @c_id FROM counterparties c WHERE c.CoachName = NEW.CoachCompanyID LIMIT 1;			
					if IFNULL(@c_id,0) >0 then 
					SET NEW.vid= 'Third';
					SET NEW.InvoicedRcvd ='';
					SET NEW.Platform = 'Όχι';
					SET NEW.PlatformPrice = 0;
					Else
							SET NEW.vid= @vlic;
							SET NEW.PurchasePrice = 0;
							SET NEW.PricePaid = 0;
							SET NEW.InvoicedRcvd ='Δε θα λάβω';
							if @vlic ='IKE' then
								SET NEW.Platform = 'Ναι';
							else
								SET NEW.Platform = 'Όχι';
								SET NEW.PlatformPrice = 0;							
							END if;
				 END if;
END if;
		if NEW.cID_Customer <> OLD.cID_Customer then
					if NEW.cID_Customer IN (SELECT cid FROM counterparties c WHERE c.LeadName = 1) then 
								SET NEW.LesseeName = NEW.LeadName;
					else
								SET NEW.LesseeName = (SELECT c.LesseName FROM counterparties c	WHERE c.CID = NEW.cID_Customer  LIMIT 1);	
					END if;
					SET NEW.TobeInvoiced = (SELECT c.InvoiceType FROM counterparties c	WHERE c.CID = NEW.cID_Customer  LIMIT 1);
					SET NEW.PaymentType = (SELECT c.PaymentType FROM counterparties c	WHERE c.CID = NEW.cID_Customer  LIMIT 1);
										if check_wcr(NEW.cID_Customer) IS NOT NULL  then 
										SET NEW.DriverNotes = CONCAT(NEW.DriverNotes, '  ', check_wcr(NEW.cID_Customer)) ;
										END if;	
		END if;
		if NEW.CoachCompanyID = 'NOT PROVIDED' AND NEW.Status = 'Completed' then
		 			SET NEW.Platform = 'Όχι';
		 			SET NEW.PlatformPrice = 0;
 					SET NEW.PurchasePrice = 0;
					SET NEW.InvoicedRcvd ='Δε θα λάβω';
					SET NEW.PricePaid = 0;
		END if ;
		if NEW.Status <> OLD.Status then
				if  NEW.Status = 'Cancelled' then
					SET NEW.TransferTime = '00:00';
					SET NEW.CarTypeID = '';
					SET NEW.TobeInvoiced = 'Όχι';
		 			SET NEW.InvoicedRcvd ='Δε θα λάβω';
		 			SET NEW.Platform = 'Όχι';
		 			SET NEW.PlatformPrice = 0;
		 			SET NEW.SalePrice = 0;
		 			SET NEW.PaymentType='';
		 			SET NEW.OperationalIssues = NULL;
		 			SET NEW.FinancialIssues = NULL;
		 			SET NEW.CoachCompanyID = NULL;
		 			SET NEW.PriceRcvd = 0;
		 			SET NEW.Flight_Ship_Num_From = REPLACE(NEW.Flight_Ship_Num_From,'TBD','');
					 SET NEW.Flight_Ship_Num_From = REPLACE(NEW.Flight_Ship_Num_From,'TBD','');
		 			SET NEW.From = REPLACE(NEW.From,'TBD','');
		 			SET NEW.To = REPLACE(NEW.To,'TBD','');
		 			SET NEW.Notes = REPLACE(NEW.Notes,'TBD','');
		 			SET NEW.LeadName = REPLACE(NEW.LeadName,'TBD','');
		 			SET NEW.LeadPhone = REPLACE(NEW.LeadPhone,'TBD','');
		 			SET NEW.LesseeName = REPLACE(NEW.LesseeName,'TBD','');
		 			SET NEW.Assistant = REPLACE(NEW.Assistant,'TBA','');
		 			SET NEW.PassengerName = REPLACE(NEW.PassengerName,'TBA','');
		 			SET NEW.transfertime = REPLACE(NEW.transfertime,'TBD','');
		 		END if;	
		END if;
	SELECT u.groupid INTO @grpid
FROM MyAthensTransfersAPP_users u
WHERE u.username = NEW.ModifiedBy;
		if @grpid = 'Supplier' OR @grpid = 'Partner' then
			SET NEW.SupplierModified = 1 ;
			else
			SET NEW.SupplierModified = 0 ;
	END if;
SET NEW.pricecheck =  
case  when GetVouchername(NEW.CoachCompanyID) =0 then 
	case 
	when IFNULL(NEW.PurchasePrice,0) <> 0 AND IFNULL(NEW.SalePrice,0) = 0 then 'Check'
	when  ROUND((1- ifnull(NEW.PurchasePrice,0) / ifnull(new.SalePrice,0))*100,2)   > (SELECT p.`Value` FROM optionvalues p WHERE p.`Option` = 'PriceCheck')/1 then 'Check'
	  when  ROUND((1- ifnull(NEW.PurchasePrice,0) / ifnull(NEW.SalePrice,0))*100,2)   < (SELECT p.`Value` FROM optionvalues p WHERE p.`Option` = 'PriceCheck')*(-1) then 'Check'
	  ELSE 'ok'
	  end ELSE 'ok' END ;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `transfers_archive`
--

CREATE TABLE `transfers_archive` (
  `TransferID` int(11) NOT NULL DEFAULT 0,
  `ImportDate` date DEFAULT NULL,
  `cID_Customer` int(11) DEFAULT NULL,
  `Pax` varchar(50) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT NULL,
  `SupplierCode` varchar(50) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT NULL,
  `TransferType` varchar(150) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT NULL,
  `TransferDate` date DEFAULT NULL,
  `TransferTime` varchar(50) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT NULL,
  `TransferTime_sort` time DEFAULT NULL,
  `From` varchar(150) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT NULL,
  `Flight_Ship_Num_From` varchar(150) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT NULL,
  `To` varchar(150) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT NULL,
  `Assistant` varchar(500) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT NULL,
  `Assistant_Price` double DEFAULT NULL,
  `cID_Assistant` int(11) DEFAULT NULL,
  `CoachCompanyID` varchar(250) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT NULL,
  `vid` varchar(250) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT NULL,
  `CarTypeID` varchar(50) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT NULL,
  `PassengerName` varchar(1000) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT NULL,
  `LeadName` varchar(1000) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT NULL,
  `LeadPhone` varchar(250) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT NULL,
  `LesseeName` varchar(500) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT NULL,
  `Notes` varchar(4000) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT NULL,
  `DriverNotes` varchar(2500) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT NULL,
  `Status` varchar(150) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT 'Pending',
  `Platform` varchar(250) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT '',
  `ReferencePeriod` date DEFAULT NULL,
  `ReferenceYear` int(11) DEFAULT NULL,
  `PurchasePrice` double DEFAULT 0 COMMENT 'Τιμή Αγ.',
  `SalePrice` double DEFAULT 0 COMMENT 'Τιμή Πώλ.',
  `PricePaid` double DEFAULT 0 COMMENT 'Πληρ.',
  `PriceRcvd` double DEFAULT 0 COMMENT 'Εισπρ.',
  `Expense` double DEFAULT 0 COMMENT 'Έξοδα',
  `ExpenseRcvd` varchar(15) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT '' COMMENT 'Παρ. εξ.',
  `ExpensePaid` varchar(15) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT '' COMMENT 'Απ. Εξ.',
  `TobeInvoiced` varchar(50) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT '' COMMENT 'Τιμ.',
  `InvoicedIssued` varchar(50) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT '' COMMENT 'Έχω εκδ.',
  `InvoicedRcvd` varchar(50) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT '' COMMENT 'Έχω λάβει τιμ.',
  `SalesInvoiceNum` varchar(500) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT NULL COMMENT 'A/A Τιμολογίου',
  `PurchaseInvoiceNum` varchar(500) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT NULL,
  `PlatformPrice` double DEFAULT NULL,
  `Assets` double DEFAULT NULL COMMENT 'Απαιτήσεις',
  `ExtraIncome` double DEFAULT 0,
  `Liabilities` double DEFAULT NULL COMMENT 'Υποχρεώσεις',
  `CashRegister` double DEFAULT NULL COMMENT 'Ταμείο',
  `Income` double DEFAULT NULL COMMENT 'Έσοδο',
  `CreatedTimestamp` datetime DEFAULT NULL,
  `ModifiedTimestamp` datetime DEFAULT NULL,
  `CreatedBy` varchar(50) DEFAULT NULL,
  `ModifiedBy` varchar(50) DEFAULT NULL,
  `AssistantStatus` varchar(150) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT NULL,
  `VPOS` varchar(500) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT NULL,
  `PaymentType` varchar(150) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT NULL,
  `PaymentOnHoldFlag` varchar(50) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT '',
  `Voucher_StartDt` date DEFAULT NULL,
  `Voucher_StartTime` time DEFAULT NULL,
  `Voucher_EndDt` date DEFAULT NULL,
  `Voucher_EndTime` time DEFAULT NULL,
  `order` varchar(500) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT NULL,
  `OperationalIssues` int(11) DEFAULT NULL,
  `FinancialIssues` int(11) DEFAULT NULL,
  `OpenCharges` int(11) DEFAULT NULL,
  `TicketsRequested` varchar(150) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT NULL,
  `ElorusID` varchar(50) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT NULL,
  `order1` int(11) DEFAULT NULL,
  `Voucher_Start_Date_Calc` date DEFAULT NULL,
  `Voucher_Start_StartTime_Calc` time DEFAULT NULL,
  `Voucher_End_Date_Calc` date DEFAULT NULL,
  `Voucher_End_Time_Calc` time DEFAULT NULL,
  `OperationalIssuesFlag` varchar(50) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT NULL,
  `FinancialIssuesFlag` varchar(50) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT NULL,
  `OpenExpensesFlag` varchar(50) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT NULL,
  `OpenChargesFlag` varchar(50) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT NULL,
  `AssetsFlag` varchar(50) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT NULL,
  `LiabilitiesFlag` varchar(50) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT NULL,
  `Files` mediumtext CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT NULL,
  `Attachments` mediumblob DEFAULT NULL,
  `FLNM` varchar(250) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT NULL,
  `DriverReceiptType` varchar(250) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT NULL,
  `DriverReceipt` int(11) DEFAULT 0,
  `TicketsValue` double DEFAULT NULL,
  `SupplierModified` int(11) DEFAULT 0,
  `TransferLoadID` int(11) DEFAULT NULL,
  `pricecheck` varchar(50) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transfers_comms`
--

CREATE TABLE `transfers_comms` (
  `ID` int(11) NOT NULL,
  `TransferID` int(11) NOT NULL,
  `Notes` text NOT NULL,
  `CreatedDate` datetime NOT NULL,
  `CreatedBy` varchar(100) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transfers_load`
--

CREATE TABLE `transfers_load` (
  `LoadID` int(11) NOT NULL,
  `ImportDate` date DEFAULT NULL,
  `cID_Customer` int(11) DEFAULT NULL,
  `Pax` varchar(50) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT NULL,
  `SupplierCode` varchar(50) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT NULL,
  `TransferType` varchar(150) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT NULL,
  `TransferDate` date DEFAULT NULL,
  `TransferTime` varchar(50) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT NULL,
  `From` varchar(150) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT NULL,
  `To` varchar(150) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT NULL,
  `Flight_Ship_Num_From` varchar(150) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT NULL,
  `Notes` varchar(2500) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT NULL,
  `SalePrice` double DEFAULT NULL COMMENT 'Τιμή Πώλ.',
  `Assistant` varchar(500) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT NULL,
  `PassengerList` varchar(500) DEFAULT NULL,
  `CarType` varchar(150) DEFAULT NULL,
  `LeadName` varchar(150) DEFAULT NULL,
  `LeadPhone` varchar(150) DEFAULT NULL,
  `SalePrice_C` double DEFAULT NULL,
  `Sale_Price` double DEFAULT NULL,
  `Sale_Price_Cleaned` double GENERATED ALWAYS AS (if(ifnull(`SalePrice_C`,0) = 0,ifnull(`Sale_Price`,0),`SalePrice_C`)) VIRTUAL,
  `Createdby` varchar(150) DEFAULT NULL,
  `CreatedDate` datetime DEFAULT NULL,
  `Loaded` int(11) DEFAULT 0
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Triggers `transfers_load`
--
DELIMITER $$
CREATE TRIGGER `transfers_load_after_insert` BEFORE INSERT ON `transfers_load` FOR EACH ROW BEGIN
SET NEW.SalePrice = (SELECT Sum(l.PriceValue) PriceValue FROM price_list_load l WHERE l.LoadID = NEW.LoadID )  ;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `transfer_charges`
--

CREATE TABLE `transfer_charges` (
  `ID` int(11) NOT NULL,
  `TransferID` int(11) NOT NULL,
  `TransferCharge` varchar(500) DEFAULT NULL,
  `ChargeDate` date DEFAULT NULL,
  `ChargeValue` double DEFAULT 0,
  `Expense` double DEFAULT NULL,
  `ExpenseReason` varchar(50) DEFAULT NULL,
  `ExpenseReceived` varchar(50) DEFAULT NULL,
  `ExpensePaid` varchar(50) DEFAULT NULL,
  `PaymentVia` varchar(250) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT NULL,
  `TobeInvoiced` tinyint(4) DEFAULT 0,
  `Invoiced` tinyint(4) DEFAULT 0,
  `ElorusID` varchar(50) DEFAULT NULL,
  `InvoiceNum` varchar(250) CHARACTER SET utf16 COLLATE utf16_general_ci DEFAULT NULL,
  `PaymentStatus` varchar(50) DEFAULT NULL,
  `ReceiptDate` date DEFAULT NULL,
  `CreatedBy` varchar(50) DEFAULT NULL,
  `CreatedDate` datetime DEFAULT NULL,
  `ModifiedBy` varchar(50) DEFAULT NULL,
  `ModifiedDate` datetime DEFAULT NULL,
  `PaymentCode` varchar(150) DEFAULT NULL,
  `ExpenseChargeCategory` int(11) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Triggers `transfer_charges`
--
DELIMITER $$
CREATE TRIGGER `transfer_charges_after_delete` AFTER DELETE ON `transfer_charges` FOR EACH ROW BEGIN
SELECT   COUNT(*) INTO @recs
		FROM transfer_charges c
			WHERE ChargeValue <> 0 and ifnull(c.PaymentStatus,'') = ''     AND c.TransferID = old.TransferID ;
UPDATE transfers t
SET t.OpenCharges = 
 if(	@recs = 0 ,NULL ,@recs)
WHERE t.TransferID = old.TransferID;
SELECT   SUM(c.ChargeValue) INTO @val
		FROM transfer_charges c
			WHERE ChargeValue <> 0 and c.PaymentStatus = 'Paid'   AND c.TransferID = old.TransferID ;
UPDATE transfers t
SET t.ExtraIncome = IFNULL(@val,0)  
WHERE t.TransferID = old.TransferID;
SELECT COUNT(*) INTO @recs
FROM transfer_charges c
WHERE c.Expense <> 0 
AND c.ExpenseReceived = 'Όχι' AND c.TransferID = old.TransferID;
UPDATE transfers t
SET t.OpenExpensesFlag = if(	@recs = 0 ,NULL ,1)
WHERE t.TransferID = old.TransferID;
SELECT   SUM(c.Expense) INTO @val
		FROM transfer_charges c
			WHERE   c.TransferID = old.TransferID ;
UPDATE transfers t
SET t.Expense = IFNULL(@val,0)  
WHERE t.TransferID = old.TransferID;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `transfer_charges_after_insert` AFTER INSERT ON `transfer_charges` FOR EACH ROW BEGIN
SELECT   COUNT(*) INTO @recs
		FROM transfer_charges c
			WHERE c.ChargeValue <> 0 and ifnull(c.PaymentStatus,'') = ''   AND c.TransferID = NEW.TransferID ;
UPDATE transfers t
SET t.OpenCharges = 
 if(	@recs = 0 ,NULL ,@recs)
WHERE t.TransferID = NEW.TransferID;
SELECT   SUM(c.ChargeValue) INTO @val
		FROM transfer_charges c
			WHERE c.PaymentStatus = 'Paid'   AND c.TransferID = NEW.TransferID ;
UPDATE transfers t
SET t.ExtraIncome = IFNULL(@val,0)  
WHERE t.TransferID = NEW.TransferID;
SELECT COUNT(*) INTO @recs
FROM transfer_charges c
WHERE c.Expense <> 0 
AND c.ExpenseReceived = 'Όχι' AND c.TransferID = NEW.TransferID;
UPDATE transfers t
SET t.OpenExpensesFlag = if(	@recs = 0 ,NULL ,1)
WHERE t.TransferID = NEW.TransferID;
SELECT   SUM(c.Expense) INTO @val
		FROM transfer_charges c
			WHERE   c.TransferID = NEW.TransferID ;
UPDATE transfers t
SET t.Expense = IFNULL(@val,0)  
WHERE t.TransferID = NEW.TransferID;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `transfer_charges_after_update` AFTER UPDATE ON `transfer_charges` FOR EACH ROW BEGIN
SELECT   COUNT(*) INTO @recs
		FROM transfer_charges c
			WHERE c.ChargeValue <> 0 and ifnull(c.PaymentStatus,'') = ''   AND c.TransferID = NEW.TransferID ;
UPDATE transfers t
SET t.OpenCharges = 
 if(	@recs = 0 ,NULL ,@recs)
WHERE t.TransferID = NEW.TransferID;
SELECT   SUM(c.ChargeValue) INTO @val
		FROM transfer_charges c
			WHERE c.PaymentStatus = 'Paid'   AND c.TransferID = NEW.TransferID ;
UPDATE transfers t
SET t.ExtraIncome = IFNULL(@val,0)  
WHERE t.TransferID = NEW.TransferID;
SELECT COUNT(*) INTO @recs
FROM transfer_charges c
WHERE c.Expense <> 0 
AND c.ExpenseReceived = 'Όχι' AND c.TransferID = NEW.TransferID;
UPDATE transfers t
SET t.OpenExpensesFlag = if(	@recs = 0 ,NULL ,1)
WHERE t.TransferID = NEW.TransferID;
SELECT   SUM(c.Expense) INTO @val
		FROM transfer_charges c
			WHERE   c.TransferID = NEW.TransferID ;
UPDATE transfers t
SET t.Expense = IFNULL(@val,0)  
WHERE t.TransferID = NEW.TransferID;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `transfer_notes`
--

CREATE TABLE `transfer_notes` (
  `ID` int(11) NOT NULL,
  `TransferID` int(11) DEFAULT 0,
  `Note` varchar(5000) DEFAULT NULL,
  `NoteDate` date DEFAULT NULL,
  `CreatedTimestamp` datetime DEFAULT NULL,
  `NoteDateModified` datetime DEFAULT NULL,
  `CreatedBy` varchar(50) DEFAULT NULL,
  `ModifiedBy` varchar(50) DEFAULT NULL,
  `Category` varchar(150) DEFAULT NULL,
  `Status` varchar(150) DEFAULT NULL,
  `Alert` tinyint(4) DEFAULT 0
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Triggers `transfer_notes`
--
DELIMITER $$
CREATE TRIGGER `transfer_notes_after_delete` AFTER DELETE ON `transfer_notes` FOR EACH ROW BEGIN
SELECT   COUNT(*) INTO @recs
		FROM transfer_notes t
			WHERE ifnull(t.`Status`,'') = '' AND  t.Category = 'Operational' AND t.TransferID = old.TransferID ;
UPDATE transfers t
SET t.OperationalIssues = if(	@recs = 0 ,NULL ,@recs) 
WHERE t.TransferID = old.TransferID;
SELECT   COUNT(*) INTO @recs
		FROM transfer_notes t
			WHERE ifnull(t.`Status`,'') = '' AND  t.Category = 'Financial' AND t.TransferID = old.TransferID ;
UPDATE transfers t
SET t.FinancialIssues = if(	@recs = 0 ,NULL ,@recs) 
WHERE t.TransferID = old.TransferID;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `transfer_notes_after_insert` AFTER INSERT ON `transfer_notes` FOR EACH ROW BEGIN
SELECT   COUNT(*) INTO @recs
		FROM transfer_notes t
			WHERE ifnull(t.`Status`,'') = '' AND  t.Category = 'Operational' AND t.TransferID = NEW.TransferID ;
UPDATE transfers t
SET t.OperationalIssues = if(	@recs = 0 ,NULL ,@recs) 
WHERE t.TransferID = NEW.TransferID;
SELECT   COUNT(*) INTO @recs
		FROM transfer_notes t
			WHERE ifnull(t.`Status`,'') = '' AND  t.Category = 'Financial' AND t.TransferID = NEW.TransferID ;
UPDATE transfers t
SET t.FinancialIssues = if(	@recs = 0 ,NULL ,@recs) 
WHERE t.TransferID = NEW.TransferID;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `transfer_notes_after_update` AFTER UPDATE ON `transfer_notes` FOR EACH ROW BEGIN
SELECT   COUNT(*) INTO @recs
		FROM transfer_notes t
			WHERE ifnull(t.`Status`,'') = '' AND  t.Category = 'Operational' AND t.TransferID = NEW.TransferID ;
UPDATE transfers t
SET t.OperationalIssues = if(	@recs = 0 ,NULL ,@recs) 
WHERE t.TransferID = NEW.TransferID;
SELECT   COUNT(*) INTO @recs
		FROM transfer_notes t
			WHERE ifnull(t.`Status`,'') = '' AND  t.Category = 'Financial' AND t.TransferID = NEW.TransferID ;
UPDATE transfers t
SET t.FinancialIssues = if(	@recs = 0 ,NULL ,@recs) 
WHERE t.TransferID = NEW.TransferID;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `transfer_notes_load`
--

CREATE TABLE `transfer_notes_load` (
  `ID` int(11) NOT NULL,
  `LoadID` int(11) DEFAULT 0,
  `Note` varchar(5000) DEFAULT NULL,
  `NoteDate` date DEFAULT NULL,
  `CreatedTimestamp` datetime DEFAULT NULL,
  `NoteDateModified` datetime DEFAULT NULL,
  `CreatedBy` varchar(50) DEFAULT NULL,
  `ModifiedBy` varchar(50) DEFAULT NULL,
  `Category` varchar(150) DEFAULT NULL,
  `Status` varchar(150) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trans_copy`
--

CREATE TABLE `trans_copy` (
  `ID` int(11) DEFAULT NULL,
  `Inv` int(11) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--

CREATE TABLE `vehicles` (
  `VehicleID` int(11) NOT NULL,
  `VehicleName` varchar(150) DEFAULT NULL,
  `VoucherName` varchar(150) DEFAULT NULL,
  `VehicleBrand` varchar(150) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `VehicleYear` int(11) DEFAULT NULL,
  `LicencePlate` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `PurchaseDate` date DEFAULT NULL,
  `Passengers` int(11) DEFAULT NULL,
  `VehicleType` varchar(150) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `VehicleLicence` varchar(150) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `VehicleTaxi` tinyint(4) DEFAULT NULL,
  `SoldDate` date DEFAULT NULL,
  `Active` tinyint(4) DEFAULT 1,
  `Tolls_Attiki` varchar(50) DEFAULT NULL,
  `Tyres` varchar(50) DEFAULT NULL,
  `Tolls_NeaOdos` varchar(50) DEFAULT NULL,
  `Tolls_Group` varchar(50) DEFAULT NULL,
  `AIA` varchar(50) DEFAULT NULL,
  `AIA_Expiry` date DEFAULT NULL,
  `order` int(11) DEFAULT NULL,
  `Picture` mediumblob DEFAULT NULL,
  `flnm` varchar(250) DEFAULT NULL,
  `FreeText` varchar(500) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf16 COLLATE=utf16_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vehicles_klm`
--

CREATE TABLE `vehicles_klm` (
  `ID` int(11) NOT NULL,
  `VehicleID` int(11) NOT NULL,
  `KLM` double NOT NULL,
  `RecordDate` date NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vehicles_log`
--

CREATE TABLE `vehicles_log` (
  `LogID` int(11) NOT NULL,
  `VehicleID` int(11) NOT NULL,
  `LogDate` date NOT NULL,
  `Kilometers` double NOT NULL DEFAULT 0,
  `LogType` varchar(250) NOT NULL,
  `Description` varchar(2000) NOT NULL,
  `Created` date DEFAULT NULL,
  `Modified` date DEFAULT NULL,
  `CreatedBy` varchar(50) NOT NULL,
  `ModifiedBy` varchar(50) NOT NULL,
  `Files` mediumtext DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_assignments`
--

CREATE TABLE `vehicle_assignments` (
  `ID` int(11) NOT NULL,
  `VehicleID` int(11) DEFAULT NULL,
  `DriverID` int(11) NOT NULL,
  `AssignmentDate` date NOT NULL,
  `Away` varchar(150) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_assets`
-- (See below for the actual view)
--
CREATE TABLE `v_assets` (
`Supplier` varchar(500)
,`CoachCompanyID` varchar(250)
,`ReferencePeriod` date
,`TobeInvoiced` varchar(50)
,`Status` varchar(150)
,`InvoicedIssued` varchar(50)
,`InvoicedRcvd` varchar(50)
,`Assets` double
,`Liabilities` double
,`SalePrice` double
,`PurchasePrice` double
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_coach`
-- (See below for the actual view)
--
CREATE TABLE `v_coach` (
`CounterShortName` varchar(150)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_daily_transfers_emails`
-- (See below for the actual view)
--
-- v_daily_transfers_emails: NOT INCLUDED. On the live server this view fails with
-- "#1271 Illegal mix of collations for operation 'UNION'", so phpMyAdmin could not export it.
-- Recreate it here once it is fixed on the server.

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_drivers_files`
-- (See below for the actual view)
--
CREATE TABLE `v_drivers_files` (
`ID` varchar(13)
,`TransferID` int(11)
,`Attachment` mediumblob
,`Flnm` varchar(250)
,`Type` int(11)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_drivers_holidays_view`
-- (See below for the actual view)
--
CREATE TABLE `v_drivers_holidays_view` (
`DriverID` int(11)
,`CalDate` date
,`HolidayType` varchar(9)
,`username` varchar(255)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_drivers_stats`
-- (See below for the actual view)
--
CREATE TABLE `v_drivers_stats` (
`VehicleName` varchar(150)
,`Fullname` varchar(150)
,`Period` date
,`TransferType` varchar(150)
,`Transfers_r` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_drivers_stats2`
-- (See below for the actual view)
--
CREATE TABLE `v_drivers_stats2` (
`VehicleID` int(11)
,`DriverID` int(11)
,`Period` date
,`TransferType` varchar(150)
,`Transfers_r` int(1)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_duplicates`
-- (See below for the actual view)
--
CREATE TABLE `v_duplicates` (
`SupplierName` varchar(150)
,`SupplierCode` varchar(50)
,`TransferDate` date
,`Transfers` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_fuel_analysis`
-- (See below for the actual view)
--
CREATE TABLE `v_fuel_analysis` (
`EndDate` varchar(10)
,`ExpenseAmount` double
,`FuelAmount` double
,`Remaining` double
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_myat_drivers_files`
-- (See below for the actual view)
--
CREATE TABLE `v_myat_drivers_files` (
`ID` varchar(13)
,`TransferID` int(11)
,`Attachment` mediumblob
,`Flnm` varchar(250)
,`Type` int(11)
,`Value` varchar(500)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_netposition`
-- (See below for the actual view)
--
CREATE TABLE `v_netposition` (
`Touro_Cash` double
,`Before2023` double
,`ike` double
,`atomiki` double
,`Due` double
,`Require` double
,`CashInHand` double
,`TotalIncome` double
,`CashRegister` double
,`DiffInCash` double
,`NetAssets` double
,`NetPosition` double
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_pl`
-- (See below for the actual view)
--
CREATE TABLE `v_pl` (
`EndDate` date
,`Touro_Cash` double
,`ExpensesValue` double
,`Required` double
,`Due` double
,`NetProfit` double
,`NetProfit_perc` double
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_receipts`
-- (See below for the actual view)
--
CREATE TABLE `v_receipts` (
`SalesInvoiceNum` varchar(500)
,`TransferID` int(11)
,`PaymentType` varchar(250)
,`TransferDate` date
,`ReferencePeriod` date
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_stats`
-- (See below for the actual view)
--
CREATE TABLE `v_stats` (
`ReferencePeriod` date
,`Cartype` varchar(50)
,`Status` varchar(150)
,`TransferType` varchar(150)
,`CounterShortName` varchar(500)
,`VehicleName` varchar(150)
,`CoachCompanyID` varchar(250)
,`TobeInvoiced` varchar(3)
,`CoachType` varchar(9)
,`Transfers_count` bigint(21)
,`Τζίρος` double
,`Έσοδο` double
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_stats_2`
-- (See below for the actual view)
--
CREATE TABLE `v_stats_2` (
`TransferDate` date
,`TransferType` varchar(150)
,`CarTypeID` varchar(50)
,`Viator Tours` decimal(23,0)
,`ATT Tours` decimal(23,0)
,`Counter` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_test`
-- (See below for the actual view)
--
CREATE TABLE `v_test` (
`1` int(1)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_transfers`
-- (See below for the actual view)
--
CREATE TABLE `v_transfers` (
`row_num` bigint(21)
,`Tickets_Flag` varchar(161)
,`TicketsRequested` varchar(150)
,`TransferID` int(11)
,`ImportDate` date
,`cID_Customer` int(11)
,`Pax` varchar(50)
,`SupplierCode` varchar(50)
,`PaymentType` varchar(150)
,`DriverReceiptType` varchar(250)
,`TransferType` varchar(150)
,`TransferDate` date
,`TransferTime` varchar(50)
,`From` varchar(150)
,`Flight_Ship_Num_From` varchar(150)
,`To` varchar(150)
,`Assistant` text
,`Assistant_Price` double
,`cID_Assistant` varchar(500)
,`CoachCompanyID` varchar(250)
,`CarTypeID` varchar(50)
,`PassengerName` varchar(1000)
,`LeadName` varchar(1000)
,`LeadPhone` varchar(250)
,`LesseeName` text
,`Notes` text
,`Status` varchar(150)
,`Platform` varchar(250)
,`ReferencePeriod` date
,`PurchasePrice` double
,`AssistantStatus` varchar(150)
,`CarType` varchar(50)
,`VehicleName` varchar(150)
,`Fullname` varchar(150)
,`LicenceNumber` varchar(150)
,`IDNumber` varchar(150)
,`username` varchar(255)
,`StartDate` date
,`StartTime` time /* mariadb-5.3 */
,`EndDate` date
,`EndTime` time /* mariadb-5.3 */
,`PlatformPrice` double
,`LicencePlate` varchar(50)
,`duration` varchar(10)
,`Email` varchar(500)
,`VehicleLicence` varchar(150)
,`VoucherName` varchar(150)
,`Runby` varchar(11)
,`aid` double
,`AssistantNum` varchar(150)
,`AssistantAMKA` varchar(150)
,`VAT` varchar(150)
,`TourDescription` varchar(5000)
,`DriverPhone` varchar(50)
,`AssistantEmail` varchar(500)
,`assistantid` double
,`Attachments` binary(0)
,`FLNM` binary(0)
,`Attachment_Contr` binary(0)
,`Flnm_Contract` binary(0)
,`DriverNotes` varchar(2500)
,`TransferTime_sort` time
,`VehicleTaxi` tinyint(4)
,`OLP` tinyint(4)
,`TelegramID` bigint(20)
,`ReviewLink` text
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_transfers2`
-- (See below for the actual view)
--
CREATE TABLE `v_transfers2` (
`row_num` bigint(21)
,`TransferID` int(11)
,`ImportDate` date
,`cID_Customer` int(11)
,`Pax` varchar(50)
,`SupplierCode` varchar(50)
,`TransferType` varchar(150)
,`TransferDate` date
,`TransferTime` varchar(50)
,`From` varchar(150)
,`Flight_Ship_Num_From` varchar(150)
,`To` varchar(150)
,`Assistant` text
,`Assistant_Price` double
,`cID_Assistant` varchar(500)
,`CoachCompanyID` varchar(250)
,`CarTypeID` varchar(50)
,`PassengerName` varchar(1000)
,`LeadName` varchar(1000)
,`LeadPhone` varchar(250)
,`LesseeName` varchar(500)
,`Notes` varchar(2500)
,`Status` varchar(150)
,`Platform` varchar(250)
,`ReferencePeriod` date
,`ReferenceYear` int(11)
,`PurchasePrice` double
,`AssistantStatus` varchar(150)
,`CarType` varchar(50)
,`VehicleName` char(0)
,`Fullname` varchar(150)
,`LicenceNumber` char(0)
,`IDNumber` char(0)
,`username` varchar(255)
,`StartDate` date
,`StartTime` time /* mariadb-5.3 */
,`EndDate` date
,`EndTime` time /* mariadb-5.3 */
,`PlatformPrice` double
,`LicencePlate` char(0)
,`duration` varchar(10)
,`Email` varchar(255)
,`VehicleLicence` char(0)
,`VoucherName` char(0)
,`UserID` int(11)
,`InvoicedRcvd` varchar(50)
,`Attachments` mediumblob
,`FLNM` varchar(250)
,`CoachName` varchar(150)
,`ReviewLink` text
);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `access_log`
--
ALTER TABLE `access_log`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `app_logs`
--
ALTER TABLE `app_logs`
  ADD PRIMARY KEY (`log_id`);

--
-- Indexes for table `calcategory`
--
ALTER TABLE `calcategory`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `calendar_days`
--
ALTER TABLE `calendar_days`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `calfirstday`
--
ALTER TABLE `calfirstday`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `calglobals`
--
ALTER TABLE `calglobals`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `calholidays`
--
ALTER TABLE `calholidays`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `callocale`
--
ALTER TABLE `callocale`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `calperiod`
--
ALTER TABLE `calperiod`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `caltimezone`
--
ALTER TABLE `caltimezone`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `calusers`
--
ALTER TABLE `calusers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `calweekdays`
--
ALTER TABLE `calweekdays`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cash`
--
ALTER TABLE `cash`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `codes`
--
ALTER TABLE `codes`
  ADD KEY `supcode` (`OldTo`(250)) USING BTREE,
  ADD KEY `NewTo` (`NewTo`(250)),
  ADD KEY `SupID` (`SupID`);

--
-- Indexes for table `counterparties`
--
ALTER TABLE `counterparties`
  ADD PRIMARY KEY (`CID`) USING BTREE,
  ADD KEY `CounterShortName` (`CounterShortName`) USING BTREE,
  ADD KEY `SupplierName` (`SupplierName`),
  ADD KEY `CoachName` (`CoachName`);

--
-- Indexes for table `drivers`
--
ALTER TABLE `drivers`
  ADD PRIMARY KEY (`DriverID`),
  ADD KEY `UserID` (`UserID`);

--
-- Indexes for table `drivers_holidays_master`
--
ALTER TABLE `drivers_holidays_master`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `emails_sent`
--
ALTER TABLE `emails_sent`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `TransferDate_aID` (`TransferDate`,`aID`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`ExpenseID`),
  ADD KEY `ExpenseCategory` (`ExpenseCategory`),
  ADD KEY `ExpenseCategoryID` (`ExpenseCategoryID`);

--
-- Indexes for table `fuel_register`
--
ALTER TABLE `fuel_register`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `MyAthensTransfersAPP6uggroups`
--
ALTER TABLE `MyAthensTransfersAPP6uggroups`
  ADD PRIMARY KEY (`GroupID`);

--
-- Indexes for table `MyAthensTransfersAPP6ugmembers`
--
ALTER TABLE `MyAthensTransfersAPP6ugmembers`
  ADD PRIMARY KEY (`UserName`(50),`GroupID`,`Provider`);

--
-- Indexes for table `myathenstransfersapp6ugmembers`
--
ALTER TABLE `myathenstransfersapp6ugmembers`
  ADD PRIMARY KEY (`UserName`(50),`GroupID`,`Provider`);

--
-- Indexes for table `MyAthensTransfersAPP6ugrights`
--
ALTER TABLE `MyAthensTransfersAPP6ugrights`
  ADD PRIMARY KEY (`TableName`(50),`GroupID`);

--
-- Indexes for table `myathenstransfersapp6ugrights`
--
ALTER TABLE `myathenstransfersapp6ugrights`
  ADD PRIMARY KEY (`TableName`(50),`GroupID`);

--
-- Indexes for table `MyAthensTransfersAPP_settings`
--
ALTER TABLE `MyAthensTransfersAPP_settings`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `MyAthensTransfersAPP_users`
--
ALTER TABLE `MyAthensTransfersAPP_users`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `username` (`username`);

--
-- Indexes for table `MyAthensTransfersAPP_users_copy`
--
ALTER TABLE `MyAthensTransfersAPP_users_copy`
  ADD PRIMARY KEY (`ID`) USING BTREE;

--
-- Indexes for table `myat_audit_audit`
--
ALTER TABLE `myat_audit_audit`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `myat_partner_files`
--
ALTER TABLE `myat_partner_files`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `cid` (`cid`),
  ADD KEY `Type` (`Type`);

--
-- Indexes for table `myat_transfers_files`
--
ALTER TABLE `myat_transfers_files`
  ADD PRIMARY KEY (`ID`) USING BTREE,
  ADD KEY `TransferID` (`TransferID`) USING BTREE,
  ADD KEY `Type` (`Type`) USING BTREE;

--
-- Indexes for table `myat_transfers_load_files`
--
ALTER TABLE `myat_transfers_load_files`
  ADD PRIMARY KEY (`ID`) USING BTREE,
  ADD KEY `TransferID` (`TransferID`) USING BTREE;

--
-- Indexes for table `optionvalues`
--
ALTER TABLE `optionvalues`
  ADD PRIMARY KEY (`OptionID`),
  ADD KEY `Option` (`Option`(333)),
  ADD KEY `Value` (`Value`),
  ADD KEY `DriverView` (`DriverView`),
  ADD KEY `PartnerView` (`PartnerView`);

--
-- Indexes for table `paymentlinks`
--
ALTER TABLE `paymentlinks`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `FK_paymentlinks_PaymentsReceived` (`PaymentID`),
  ADD KEY `FK_paymentlinks_transfers` (`TransferID`);

--
-- Indexes for table `PaymentsReceived`
--
ALTER TABLE `PaymentsReceived`
  ADD PRIMARY KEY (`PaymentID`),
  ADD KEY `FK_PaymentsReceived_counterparties` (`CID_Customer`);

--
-- Indexes for table `price_list`
--
ALTER TABLE `price_list`
  ADD PRIMARY KEY (`ID`) USING BTREE,
  ADD KEY `Product2` (`Product2`) USING BTREE,
  ADD KEY `CarType` (`CarType`) USING BTREE,
  ADD KEY `Product` (`Product`) USING BTREE,
  ADD KEY `cid_Customer` (`cid_Customer`) USING BTREE;

--
-- Indexes for table `price_list_load`
--
ALTER TABLE `price_list_load`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `LoadID` (`LoadID`);

--
-- Indexes for table `price_list_old`
--
ALTER TABLE `price_list_old`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `Product2` (`Product2`),
  ADD KEY `CarType` (`CarType`),
  ADD KEY `Product` (`Product`),
  ADD KEY `cid_Customer` (`cid_Customer`);

--
-- Indexes for table `price_list_transfers`
--
ALTER TABLE `price_list_transfers`
  ADD PRIMARY KEY (`ID`) USING BTREE,
  ADD KEY `TransferID` (`TransferID`);

--
-- Indexes for table `price_list_transfers_copy`
--
ALTER TABLE `price_list_transfers_copy`
  ADD PRIMARY KEY (`ID`) USING BTREE,
  ADD KEY `TransferID` (`TransferID`) USING BTREE;

--
-- Indexes for table `rc_categories`
--
ALTER TABLE `rc_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `rc_holidays`
--
ALTER TABLE `rc_holidays`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `rc_locales`
--
ALTER TABLE `rc_locales`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `rc_resourcedetails`
--
ALTER TABLE `rc_resourcedetails`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `rc_resources`
--
ALTER TABLE `rc_resources`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `rc_resweekdays`
--
ALTER TABLE `rc_resweekdays`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `rc_settings`
--
ALTER TABLE `rc_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `review_links`
--
ALTER TABLE `review_links`
  ADD PRIMARY KEY (`ID`) USING BTREE;

--
-- Indexes for table `schedule`
--
ALTER TABLE `schedule`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `driver_id` (`driver_id`,`date`);

--
-- Indexes for table `sms`
--
ALTER TABLE `sms`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `telegram_messages`
--
ALTER TABLE `telegram_messages`
  ADD PRIMARY KEY (`id`) USING BTREE;

--
-- Indexes for table `telegram_users`
--
ALTER TABLE `telegram_users`
  ADD PRIMARY KEY (`id`) USING BTREE,
  ADD UNIQUE KEY `telegram_id` (`telegram_id`) USING BTREE;

--
-- Indexes for table `testapi`
--
ALTER TABLE `testapi`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `touro_groups`
--
ALTER TABLE `touro_groups`
  ADD PRIMARY KEY (`GroupID`);

--
-- Indexes for table `touro_noti`
--
ALTER TABLE `touro_noti`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tours`
--
ALTER TABLE `tours`
  ADD PRIMARY KEY (`TourID`),
  ADD KEY `TourName` (`TourName`(250));

--
-- Indexes for table `transfers`
--
ALTER TABLE `transfers`
  ADD PRIMARY KEY (`TransferID`),
  ADD KEY `FK_transfers_counterparties` (`cID_Customer`),
  ADD KEY `SupplierCode` (`SupplierCode`),
  ADD KEY `ReferenceYear` (`ReferenceYear`),
  ADD KEY `From` (`From`),
  ADD KEY `To` (`To`),
  ADD KEY `CoachCompanyID` (`CoachCompanyID`),
  ADD KEY `ModifiedBy` (`ModifiedBy`),
  ADD KEY `SupplierModified` (`SupplierModified`),
  ADD KEY `TransferDate` (`TransferDate`),
  ADD KEY `TicketsFlag` (`TicketsFlag`);

--
-- Indexes for table `transfers_comms`
--
ALTER TABLE `transfers_comms`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `TransferID` (`TransferID`);

--
-- Indexes for table `transfers_load`
--
ALTER TABLE `transfers_load`
  ADD PRIMARY KEY (`LoadID`);

--
-- Indexes for table `transfer_charges`
--
ALTER TABLE `transfer_charges`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `transfer_notes`
--
ALTER TABLE `transfer_notes`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `FK_transfer_notes_transfers` (`TransferID`);

--
-- Indexes for table `transfer_notes_load`
--
ALTER TABLE `transfer_notes_load`
  ADD PRIMARY KEY (`ID`) USING BTREE,
  ADD KEY `FK_transfer_notes_transfers` (`LoadID`) USING BTREE;

--
-- Indexes for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`VehicleID`),
  ADD KEY `VehicleName` (`VehicleName`),
  ADD KEY `VoucherName` (`VoucherName`);

--
-- Indexes for table `vehicles_klm`
--
ALTER TABLE `vehicles_klm`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `vehicles_log`
--
ALTER TABLE `vehicles_log`
  ADD PRIMARY KEY (`LogID`);

--
-- Indexes for table `vehicle_assignments`
--
ALTER TABLE `vehicle_assignments`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `FK_vehicle_assignments_drivers` (`DriverID`),
  ADD KEY `VehicleID` (`VehicleID`),
  ADD KEY `AssignmentDate` (`AssignmentDate`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `access_log`
--
ALTER TABLE `access_log`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `app_logs`
--
ALTER TABLE `app_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `calcategory`
--
ALTER TABLE `calcategory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `calendar_days`
--
ALTER TABLE `calendar_days`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `calfirstday`
--
ALTER TABLE `calfirstday`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `calglobals`
--
ALTER TABLE `calglobals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `calholidays`
--
ALTER TABLE `calholidays`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `callocale`
--
ALTER TABLE `callocale`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `calperiod`
--
ALTER TABLE `calperiod`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `caltimezone`
--
ALTER TABLE `caltimezone`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `calusers`
--
ALTER TABLE `calusers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `calweekdays`
--
ALTER TABLE `calweekdays`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cash`
--
ALTER TABLE `cash`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `counterparties`
--
ALTER TABLE `counterparties`
  MODIFY `CID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `drivers`
--
ALTER TABLE `drivers`
  MODIFY `DriverID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `drivers_holidays_master`
--
ALTER TABLE `drivers_holidays_master`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `emails_sent`
--
ALTER TABLE `emails_sent`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `ExpenseID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fuel_register`
--
ALTER TABLE `fuel_register`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `MyAthensTransfersAPP6uggroups`
--
ALTER TABLE `MyAthensTransfersAPP6uggroups`
  MODIFY `GroupID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `MyAthensTransfersAPP_settings`
--
ALTER TABLE `MyAthensTransfersAPP_settings`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `MyAthensTransfersAPP_users`
--
ALTER TABLE `MyAthensTransfersAPP_users`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `MyAthensTransfersAPP_users_copy`
--
ALTER TABLE `MyAthensTransfersAPP_users_copy`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `myat_audit_audit`
--
ALTER TABLE `myat_audit_audit`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `myat_partner_files`
--
ALTER TABLE `myat_partner_files`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `myat_transfers_files`
--
ALTER TABLE `myat_transfers_files`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `myat_transfers_load_files`
--
ALTER TABLE `myat_transfers_load_files`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `optionvalues`
--
ALTER TABLE `optionvalues`
  MODIFY `OptionID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `paymentlinks`
--
ALTER TABLE `paymentlinks`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `PaymentsReceived`
--
ALTER TABLE `PaymentsReceived`
  MODIFY `PaymentID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `price_list`
--
ALTER TABLE `price_list`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `price_list_load`
--
ALTER TABLE `price_list_load`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `price_list_old`
--
ALTER TABLE `price_list_old`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `price_list_transfers`
--
ALTER TABLE `price_list_transfers`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `price_list_transfers_copy`
--
ALTER TABLE `price_list_transfers_copy`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rc_categories`
--
ALTER TABLE `rc_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rc_holidays`
--
ALTER TABLE `rc_holidays`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rc_locales`
--
ALTER TABLE `rc_locales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rc_resourcedetails`
--
ALTER TABLE `rc_resourcedetails`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rc_resources`
--
ALTER TABLE `rc_resources`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rc_resweekdays`
--
ALTER TABLE `rc_resweekdays`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rc_settings`
--
ALTER TABLE `rc_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `review_links`
--
ALTER TABLE `review_links`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `schedule`
--
ALTER TABLE `schedule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sms`
--
ALTER TABLE `sms`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `telegram_messages`
--
ALTER TABLE `telegram_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `telegram_users`
--
ALTER TABLE `telegram_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `testapi`
--
ALTER TABLE `testapi`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `touro_groups`
--
ALTER TABLE `touro_groups`
  MODIFY `GroupID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `touro_noti`
--
ALTER TABLE `touro_noti`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tours`
--
ALTER TABLE `tours`
  MODIFY `TourID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transfers`
--
ALTER TABLE `transfers`
  MODIFY `TransferID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transfers_comms`
--
ALTER TABLE `transfers_comms`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transfers_load`
--
ALTER TABLE `transfers_load`
  MODIFY `LoadID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transfer_charges`
--
ALTER TABLE `transfer_charges`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transfer_notes`
--
ALTER TABLE `transfer_notes`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transfer_notes_load`
--
ALTER TABLE `transfer_notes_load`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `VehicleID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vehicles_klm`
--
ALTER TABLE `vehicles_klm`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vehicles_log`
--
ALTER TABLE `vehicles_log`
  MODIFY `LogID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vehicle_assignments`
--
ALTER TABLE `vehicle_assignments`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------

--
-- Structure for view `a_`
--
DROP TABLE IF EXISTS `a_`;

CREATE ALGORITHM=UNDEFINED VIEW `a_`  AS SELECT 1 AS `1` ;

-- --------------------------------------------------------

--
-- Structure for view `calcalendar`
--
DROP TABLE IF EXISTS `calcalendar`;

CREATE ALGORITHM=UNDEFINED VIEW `calcalendar`  AS SELECT `h`.`ID` AS `id`, `h`.`HolidayStartDate` AS `DateField`, concat(`h`.`HolidayType`,' - ',`d`.`Fullname`) AS `Description`, concat(`h`.`HolidayType`,' - ',`d`.`Fullname`) AS `Subject`, cast('02:05' as time) AS `TimeField`, cast('18:00' as time) AS `EndTime`, 0 AS `DayEvent`, `h`.`HolidayEndDate` AS `EndDate`, NULL AS `Period`, NULL AS `PeriodDays`, NULL AS `Recurrence`, `c`.`id` AS `Category`, NULL AS `OwnerID`, 'Never' AS `RecurType`, `h`.`HolidayStartDate` AS `RecurOnValue`, 1 AS `RecurAfterValue`, 0 AS `notiValue`, 'day(s)' AS `notiName`, NULL AS `notiSent`, NULL AS `notiTime`, NULL AS `notiUse`, NULL AS `TransferType`, NULL AS `Coach`, NULL AS `VehicleName`, NULL AS `MyATCoach`, NULL AS `CarType`, `h`.`HolidayType` AS `HolidayType` FROM ((`drivers_holidays_master` `h` left join `drivers` `d` on(`h`.`DriverID` = `d`.`DriverID`)) left join `calcategory` `c` on(`c`.`Category` = `h`.`HolidayType`)) ;

-- --------------------------------------------------------

--
-- Structure for view `v_assets`
--
DROP TABLE IF EXISTS `v_assets`;

CREATE ALGORITHM=UNDEFINED VIEW `v_assets`  AS SELECT `c`.`CounterShortName` AS `Supplier`, `t`.`CoachCompanyID` AS `CoachCompanyID`, `t`.`ReferencePeriod` AS `ReferencePeriod`, `t`.`TobeInvoiced` AS `TobeInvoiced`, `t`.`Status` AS `Status`, if(ifnull(`t`.`InvoicedIssued`,'') = '','Όχι',`t`.`InvoicedIssued`) AS `InvoicedIssued`, if(ifnull(`t`.`InvoicedRcvd`,'') = '','Όχι',`t`.`InvoicedRcvd`) AS `InvoicedRcvd`, sum(`t`.`Assets`) AS `Assets`, sum(`t`.`Liabilities`) AS `Liabilities`, sum(ifnull(`t`.`SalePrice`,0)) AS `SalePrice`, sum(ifnull(`t`.`PurchasePrice`,0)) AS `PurchasePrice` FROM (`transfers` `t` left join `counterparties` `c` on(`t`.`cID_Customer` = `c`.`CID`)) GROUP BY 1, 2, 3, 4, 5, 6, 7 ;

-- --------------------------------------------------------

--
-- Structure for view `v_coach`
--
DROP TABLE IF EXISTS `v_coach`;

CREATE ALGORITHM=UNDEFINED VIEW `v_coach`  AS SELECT `a`.`CounterShortName` AS `CounterShortName` FROM (select `c`.`CoachName` AS `CounterShortName` from `counterparties` `c` where `c`.`IsCoach` = 1 union all select `v`.`VehicleName` AS `VehicleName` from `vehicles` `v` where `v`.`VehicleLicence` not in ('Sold','Rental','PVT')) AS `a` ORDER BY 1 ASC ;

-- --------------------------------------------------------

--
-- Structure for view `v_daily_transfers_emails`
--
DROP TABLE IF EXISTS `v_daily_transfers_emails`;
-- Error reading structure for table v_daily_transfers_emails: #1271 - Illegal mix of collations for operation &#039;UNION&#039;

-- --------------------------------------------------------

--
-- Structure for view `v_drivers_files`
--
DROP TABLE IF EXISTS `v_drivers_files`;

CREATE ALGORITHM=UNDEFINED VIEW `v_drivers_files`  AS SELECT concat(`m`.`ID`,'_0') AS `ID`, `m`.`TransferID` AS `TransferID`, `m`.`Attachment` AS `Attachment`, `m`.`Flnm` AS `Flnm`, `m`.`Type` AS `Type` FROM (`myat_transfers_files` `m` left join `optionvalues` `o` on(`m`.`Type` = `o`.`OptionID`)) WHERE `o`.`DriverView` = 1 union all select concat(`p`.`ID`,'_1') AS `ID`,`t`.`TransferID` AS `TransferID`,`p`.`Attachment` AS `Attachment`,`p`.`Flnm` AS `Flnm`,`p`.`Type` AS `Type` from ((`v_transfers` `t` left join `myat_partner_files` `p` on(`p`.`cid` = `t`.`cID_Customer`)) left join `optionvalues` `o` on(`p`.`Type` = `o`.`OptionID`)) where `o`.`DriverView` = 1  ;

-- --------------------------------------------------------

--
-- Structure for view `v_drivers_holidays_view`
--
DROP TABLE IF EXISTS `v_drivers_holidays_view`;

CREATE ALGORITHM=UNDEFINED VIEW `v_drivers_holidays_view`  AS SELECT `c`.`driver_id` AS `DriverID`, `c`.`date` AS `CalDate`, CASE WHEN `c`.`status` = 0 THEN 'ΡΕΠΟ' WHEN `c`.`status` = 5 THEN 'ΣΥΝΤΗΡΗΣΗ' WHEN `c`.`status` = 9 THEN 'ΑΔΕΙΑ' END AS `HolidayType`, `u`.`username` AS `username` FROM ((`schedule` `c` left join `drivers` `d` on(`c`.`driver_id` = `d`.`DriverID`)) left join `MyAthensTransfersAPP_users` `u` on(`u`.`ID` = `d`.`UserID`)) WHERE `c`.`status` in (0,5,9) ORDER BY `c`.`driver_id` ASC, `c`.`date` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `v_drivers_stats`
--
DROP TABLE IF EXISTS `v_drivers_stats`;

CREATE ALGORITHM=UNDEFINED VIEW `v_drivers_stats`  AS SELECT `vh`.`VehicleName` AS `VehicleName`, `d`.`Fullname` AS `Fullname`, last_day(`v`.`AssignmentDate`) AS `Period`, `tr`.`TransferType` AS `TransferType`, count(0) AS `Transfers_r` FROM (((`vehicle_assignments` `v` left join `drivers` `d` on(`v`.`DriverID` = `d`.`DriverID`)) left join `vehicles` `vh` on(`v`.`VehicleID` = `vh`.`VehicleID`)) left join `transfers` `tr` on(`tr`.`CoachCompanyID` = `vh`.`VehicleName` and `v`.`AssignmentDate` = `tr`.`TransferDate`)) WHERE `v`.`VehicleID` is not null AND `tr`.`TransferType` is not null GROUP BY 1, 2, 3, 4 ;

-- --------------------------------------------------------

--
-- Structure for view `v_drivers_stats2`
--
DROP TABLE IF EXISTS `v_drivers_stats2`;

CREATE ALGORITHM=UNDEFINED VIEW `v_drivers_stats2`  AS SELECT `v`.`VehicleID` AS `VehicleID`, `v`.`DriverID` AS `DriverID`, last_day(`v`.`AssignmentDate`) AS `Period`, `tr`.`TransferType` AS `TransferType`, 1 AS `Transfers_r` FROM ((`vehicle_assignments` `v` join `vehicles` `vh` on(`v`.`VehicleID` = `vh`.`VehicleID`)) join `transfers` `tr` on(`tr`.`CoachCompanyID` = `vh`.`VehicleName` and `v`.`AssignmentDate` = `tr`.`TransferDate`)) ;

-- --------------------------------------------------------

--
-- Structure for view `v_duplicates`
--
DROP TABLE IF EXISTS `v_duplicates`;

CREATE ALGORITHM=UNDEFINED VIEW `v_duplicates`  AS SELECT `c`.`SupplierName` AS `SupplierName`, `t`.`SupplierCode` AS `SupplierCode`, `t`.`TransferDate` AS `TransferDate`, count(0) AS `Transfers` FROM (`transfers` `t` left join `counterparties` `c` on(`t`.`cID_Customer` = `c`.`CID`)) WHERE year(`t`.`TransferDate`) = 2025 AND `t`.`SupplierCode` <> '' GROUP BY 1, 2, 3 HAVING count(0) > 1 ;

-- --------------------------------------------------------

--
-- Structure for view `v_fuel_analysis`
--
DROP TABLE IF EXISTS `v_fuel_analysis`;

CREATE ALGORITHM=UNDEFINED VIEW `v_fuel_analysis`  AS SELECT `c`.`EndDate` AS `EndDate`, ifnull(`ex`.`ExpenseAmount`,0) AS `ExpenseAmount`, ifnull(`f`.`FuelAmount`,0) AS `FuelAmount`, ifnull(`ex`.`ExpenseAmount`,0) - ifnull(`f`.`FuelAmount`,0) AS `Remaining` FROM (((select `c`.`EndDate` AS `EndDate` from `calendar` `c` where `c`.`EndDate` between '2024-01-31' and last_day(curdate())) `c` left join (select last_day(`e`.`ExpenseDate`) AS `ExpenseDate`,sum(`e`.`ExpenseAmountPaid` - `e`.`ExpenseAmountRcvd`) AS `ExpenseAmount` from `expenses` `e` where `e`.`ExpenseCategoryID` = 1341 group by 1) `ex` on(`ex`.`ExpenseDate` = `c`.`EndDate`)) left join (select last_day(`f`.`FuelDate`) AS `FuelDate`,sum(`f`.`FuelAmount`) AS `FuelAmount` from `fuel_register` `f` group by 1) `f` on(`f`.`FuelDate` = `c`.`EndDate`))union all select '2025-12-31' AS `EndDate`,2111.48 AS `914.48`,0 AS `0`,2111.48 AS `914.48`  ;

-- --------------------------------------------------------

--
-- Structure for view `v_myat_drivers_files`
--
DROP TABLE IF EXISTS `v_myat_drivers_files`;

CREATE ALGORITHM=UNDEFINED VIEW `v_myat_drivers_files`  AS SELECT `a`.`ID` AS `ID`, `a`.`TransferID` AS `TransferID`, `a`.`Attachment` AS `Attachment`, `a`.`Flnm` AS `Flnm`, `a`.`Type` AS `Type`, `o`.`Value` AS `Value` FROM ((select concat(`m`.`ID`,'_0') AS `ID`,`m`.`TransferID` AS `TransferID`,`m`.`Attachment` AS `Attachment`,`m`.`Flnm` AS `Flnm`,`m`.`Type` AS `Type` from ((`transfers` `t` join `myat_transfers_files` `m` on(`t`.`TransferID` = `m`.`TransferID` and `t`.`TransferDate` between curdate() + interval 0 day and curdate() + interval 1 day)) join `optionvalues` `o` on(`m`.`Type` = `o`.`OptionID`)) where `o`.`DriverView` = 1 union all select concat(`p`.`ID`,'_1') AS `ID`,`t`.`TransferID` AS `TransferID`,`p`.`Attachment` AS `Attachment`,`p`.`Flnm` AS `Flnm`,`p`.`Type` AS `Type` from ((`transfers` `t` join `myat_partner_files` `p` on(`p`.`cid` = `t`.`cID_Customer` and `t`.`TransferDate` between curdate() + interval 0 day and curdate() + interval 1 day)) join `optionvalues` `o` on(`p`.`Type` = `o`.`OptionID`)) where `o`.`PartnerView` = 1) `a` left join `optionvalues` `o` on(`a`.`Type` = `o`.`OptionID`)) ORDER BY `a`.`TransferID` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `v_netposition`
--
DROP TABLE IF EXISTS `v_netposition`;

CREATE ALGORITHM=UNDEFINED VIEW `v_netposition`  AS SELECT `a`.`Touro_Cash` AS `Touro_Cash`, `a`.`Before2023` AS `Before2023`, `a`.`ike` AS `ike`, `a`.`atomiki` AS `atomiki`, `a`.`Due` AS `Due`, `a`.`Require` AS `Require`, `a`.`CashInHand` AS `CashInHand`, `a`.`Touro_Cash`+ `a`.`Before2023` AS `TotalIncome`, `a`.`Touro_Cash`+ `a`.`Before2023` + `a`.`ike` + `a`.`atomiki` AS `CashRegister`, `a`.`CashInHand`- (`a`.`Touro_Cash` + `a`.`Before2023` + `a`.`ike` + `a`.`atomiki`) AS `DiffInCash`, `a`.`Require`- `a`.`Due` AS `NetAssets`, `a`.`Touro_Cash`+ `a`.`Before2023` + `a`.`ike` + `a`.`atomiki` + (`a`.`Require` - `a`.`Due`) AS `NetPosition` FROM (select (select sum(ifnull(`t`.`CashRegister`,0)) AS `Touro_Cash` from `transfers` `t`) AS `Touro_Cash`,(select `c`.`Before2023` + `c`.`Others` from `CashRegister` `c`) AS `Before2023`,(select sum(ifnull(`e`.`ExpenseAmountRcvd`,0) - ifnull(`e`.`ExpenseAmountPaid`,0)) from `expenses` `e` where `e`.`Company` = 'ΙΚΕ') AS `ike`,(select sum(ifnull(`e`.`ExpenseAmountRcvd`,0) - ifnull(`e`.`ExpenseAmountPaid`,0)) from `expenses` `e` where `e`.`Company` = 'ΑΤΟΜΙΚΗ') AS `atomiki`,(select sum(`t`.`Liabilities`) from `transfers` `t`) AS `Due`,(select sum(`t`.`Assets`) from `transfers` `t`) AS `Require`,(select `cash`.`Total` from `cash` where `cash`.`ID` = (select max(`cash`.`ID`) from `cash`)) AS `CashInHand`) AS `a` ;

-- --------------------------------------------------------

--
-- Structure for view `v_pl`
--
DROP TABLE IF EXISTS `v_pl`;

CREATE ALGORITHM=UNDEFINED VIEW `v_pl`  AS SELECT `pers`.`EndDate` AS `EndDate`, `trns`.`Touro_Cash` AS `Touro_Cash`, `exps`.`ExpensesValue` AS `ExpensesValue`, ifnull(`ast`.`Required`,0) AS `Required`, ifnull(`lbl`.`Due`,0) * -1 AS `Due`, `trns`.`Touro_Cash`+ `exps`.`ExpensesValue` + ifnull(`ast`.`Required`,0) + ifnull(`lbl`.`Due`,0) * -1 AS `NetProfit`, (`trns`.`Touro_Cash` + `exps`.`ExpensesValue` + ifnull(`ast`.`Required`,0) + ifnull(`lbl`.`Due`,0) * -1) / `trns`.`Touro_Cash` AS `NetProfit_perc` FROM (((((select `c`.`EndDate` AS `EndDate` from `calendar` `c` where `c`.`StartDate` between '2023-01-01' and last_day(curdate())) `pers` left join (select last_day(`t`.`TransferDate`) AS `Period`,sum(`t`.`CashRegister`) AS `Touro_Cash` from `transfers` `t` group by 1) `trns` on(`trns`.`Period` = `pers`.`EndDate`)) left join (select last_day(`e`.`ExpenseDate`) AS `Period`,sum(`e`.`ExpenseAmountRcvd` - `e`.`ExpenseAmountPaid`) AS `ExpensesValue` from `expenses` `e` where `e`.`ExpenseDate` >= '2023-01-01' group by 1) `exps` on(`exps`.`Period` = `pers`.`EndDate`)) left join (select last_day(`t`.`TransferDate`) AS `Period`,sum(`t`.`Assets`) AS `Required` from `transfers` `t`) `ast` on(`ast`.`Period` = `pers`.`EndDate`)) left join (select last_day(`t`.`TransferDate`) AS `Period`,sum(`t`.`Liabilities`) AS `Due` from `transfers` `t`) `lbl` on(`lbl`.`Period` = `pers`.`EndDate`)) ;

-- --------------------------------------------------------

--
-- Structure for view `v_receipts`
--
DROP TABLE IF EXISTS `v_receipts`;

CREATE ALGORITHM=UNDEFINED VIEW `v_receipts`  AS SELECT trim(`t`.`SalesInvoiceNum`) AS `SalesInvoiceNum`, `t`.`TransferID` AS `TransferID`, `t`.`PaymentType` AS `PaymentType`, `t`.`TransferDate` AS `TransferDate`, `t`.`ReferencePeriod` AS `ReferencePeriod` FROM `transfers` AS `t`union all select trim(`c`.`InvoiceNum`) AS `InvoiceNum`,`c`.`TransferID` AS `TransferID`,`c`.`PaymentVia` AS `PaymentVia`,`c`.`ChargeDate` AS `ChargeDate`,last_day(`c`.`ChargeDate`) AS `Last_day(c.ChargeDate)` from `transfer_charges` `c`  ;

-- --------------------------------------------------------

--
-- Structure for view `v_stats`
--
DROP TABLE IF EXISTS `v_stats`;

CREATE ALGORITHM=UNDEFINED VIEW `v_stats`  AS SELECT `t`.`ReferencePeriod` AS `ReferencePeriod`, `t`.`CarTypeID` AS `Cartype`, `t`.`Status` AS `Status`, `t`.`TransferType` AS `TransferType`, `c`.`CounterShortName` AS `CounterShortName`, ifnull(`v`.`VehicleName`,'3rd Party') AS `VehicleName`, `t`.`CoachCompanyID` AS `CoachCompanyID`, if(`t`.`TobeInvoiced` = 1,'Yes','No') AS `TobeInvoiced`, if(`v`.`VehicleName` is not null,'Own Fleet','Agency') AS `CoachType`, count(0) AS `Transfers_count`, sum(`t`.`SalePrice`) AS `Τζίρος`, sum(`t`.`Income`) AS `Έσοδο` FROM ((`transfers` `t` left join `counterparties` `c` on(`c`.`CID` = `t`.`cID_Customer`)) left join `vehicles` `v` on(`t`.`CoachCompanyID` = `v`.`VehicleName`)) GROUP BY 1, 2, 3, 4, 5, 6, 7, 8, 9 ;

-- --------------------------------------------------------

--
-- Structure for view `v_stats_2`
--
DROP TABLE IF EXISTS `v_stats_2`;

CREATE ALGORITHM=UNDEFINED VIEW `v_stats_2`  AS SELECT `t`.`TransferDate` AS `TransferDate`, `t`.`TransferType` AS `TransferType`, `t`.`CarTypeID` AS `CarTypeID`, sum(case when `c`.`CounterShortName` = 'Viator' and `t`.`TransferType` = 'Tour' then 1 else 0 end) AS `Viator Tours`, sum(case when `c`.`CounterShortName` = 'ATT' and `t`.`TransferType` = 'Tour' then 1 else 0 end) AS `ATT Tours`, count(`t`.`TransferID`) AS `Counter` FROM (`transfers` `t` left join `counterparties` `c` on(`t`.`cID_Customer` = `c`.`CID`)) WHERE `t`.`Status` <> 'Cancelled' GROUP BY 1, 2, 3 ;

-- --------------------------------------------------------

--
-- Structure for view `v_test`
--
DROP TABLE IF EXISTS `v_test`;

CREATE ALGORITHM=UNDEFINED VIEW `v_test`  AS SELECT 1 AS `1` ;

-- --------------------------------------------------------

--
-- Structure for view `v_transfers`
--
DROP TABLE IF EXISTS `v_transfers`;

CREATE ALGORITHM=TEMPTABLE VIEW `v_transfers`  AS SELECT row_number() over ( partition by ifnull(`d`.`Fullname`,`cnt`.`CoachName`),`t`.`TransferDate` order by `t`.`TransferTime_sort`) AS `row_num`, CASE WHEN ifnull(`t`.`TicketsRequested`,'') = 'Cash' THEN concat('Tickets Cash  -  ',ifnull(`t`.`TicketsValue`,0)) WHEN ifnull(`t`.`TicketsRequested`,'') not in ('','Cash') THEN concat('Tickets -  ',`t`.`TicketsRequested`) ELSE 'No' END AS `Tickets_Flag`, `t`.`TicketsRequested` AS `TicketsRequested`, `t`.`TransferID` AS `TransferID`, `t`.`ImportDate` AS `ImportDate`, `t`.`cID_Customer` AS `cID_Customer`, `t`.`Pax` AS `Pax`, `t`.`SupplierCode` AS `SupplierCode`, `t`.`PaymentType` AS `PaymentType`, `t`.`DriverReceiptType` AS `DriverReceiptType`, `t`.`TransferType` AS `TransferType`, `t`.`TransferDate` AS `TransferDate`, `t`.`TransferTime` AS `TransferTime`, `t`.`From` AS `From`, `t`.`Flight_Ship_Num_From` AS `Flight_Ship_Num_From`, `t`.`To` AS `To`, ifnull(concat(`cnt2`.`CounterShortName`,' - ',`cnt2`.`Phones`),`t`.`Assistant`) AS `Assistant`, `t`.`Assistant_Price` AS `Assistant_Price`, if(`cnt2`.`CounterShortName` is null or `cnt2`.`CounterShortName` = '','xxx',`cnt2`.`CounterShortName`) AS `cID_Assistant`, `t`.`CoachCompanyID` AS `CoachCompanyID`, `t`.`CarTypeID` AS `CarTypeID`, `t`.`PassengerName` AS `PassengerName`, `t`.`LeadName` AS `LeadName`, `t`.`LeadPhone` AS `LeadPhone`, concat(`t`.`LesseeName`,case when `t`.`cID_Customer` in (117,11) then ' / Viator' when `t`.`cID_Customer` = 37 then ' / Get Your Guide' else '' end) AS `LesseeName`, if(`v`.`VoucherName` is null,`t`.`DriverNotes`,if(`t`.`DriverNotes` is null or `t`.`DriverNotes` = '',`t`.`Notes`,`t`.`DriverNotes`)) AS `Notes`, `t`.`Status` AS `Status`, `t`.`Platform` AS `Platform`, `t`.`ReferencePeriod` AS `ReferencePeriod`, `t`.`PurchasePrice` AS `PurchasePrice`, `t`.`AssistantStatus` AS `AssistantStatus`, `t`.`CarTypeID` AS `CarType`, ifnull(`v`.`VehicleName`,`cnt`.`CoachName`) AS `VehicleName`, ifnull(`d`.`Fullname`,`cnt`.`CoachName`) AS `Fullname`, `d`.`LicenceNumber` AS `LicenceNumber`, `d`.`IDNumber` AS `IDNumber`, ifnull(`u`.`username`,`u2`.`username`) AS `username`, ifnull(`t`.`Voucher_StartDt`,`t`.`Voucher_Start_Date_Calc`) AS `StartDate`, ifnull(`t`.`Voucher_StartTime`,`t`.`Voucher_Start_StartTime_Calc`) AS `StartTime`, ifnull(`t`.`Voucher_EndDt`,`t`.`Voucher_End_Date_Calc`) AS `EndDate`, ifnull(`t`.`Voucher_EndTime`,`t`.`Voucher_End_Time_Calc`) AS `EndTime`, `t`.`PlatformPrice` AS `PlatformPrice`, `v`.`LicencePlate` AS `LicencePlate`, date_format(timediff(str_to_date(concat(ifnull(`t`.`Voucher_EndDt`,`t`.`Voucher_End_Date_Calc`),' ',ifnull(`t`.`Voucher_EndTime`,`t`.`Voucher_End_Time_Calc`)),'%Y-%m-%d %H:%i:%s'),str_to_date(concat(ifnull(`t`.`Voucher_StartDt`,`t`.`Voucher_Start_Date_Calc`),' ',ifnull(`t`.`Voucher_StartTime`,`t`.`Voucher_Start_StartTime_Calc`)),'%Y-%m-%d %H:%i:%s')),'%H:%i') AS `duration`, ifnull(`d`.`Email`,`cnt`.`Email`) AS `Email`, ifnull(`v`.`VehicleLicence`,0) AS `VehicleLicence`, ifnull(`v`.`VoucherName`,'Partner Car') AS `VoucherName`, if(`v`.`VoucherName` is null,'Partner Car','MYAT') AS `Runby`, concat(dayofmonth(`t`.`TransferDate`),if(`d`.`DriverID` is null,`cnt`.`CID` + 1000,`d`.`DriverID` + 2000)) / 1 AS `aid`, `cnt2`.`AssistantNum` AS `AssistantNum`, `cnt2`.`AssistantAMKA` AS `AssistantAMKA`, `cnt2`.`VAT` AS `VAT`, `trs`.`Tourdescription` AS `TourDescription`, `d`.`MobilePhone` AS `DriverPhone`, `cnt2`.`Email` AS `AssistantEmail`, concat(dayofmonth(`t`.`TransferDate`),`cnt2`.`CID`) / 1 AS `assistantid`, NULL AS `Attachments`, NULL AS `FLNM`, NULL AS `Attachment_Contr`, NULL AS `Flnm_Contract`, `t`.`DriverNotes` AS `DriverNotes`, `t`.`TransferTime_sort` AS `TransferTime_sort`, `v`.`VehicleTaxi` AS `VehicleTaxi`, `t`.`OLP` AS `OLP`, `d`.`TelegramID` AS `TelegramID`, if(`Get_Review_option`(`t`.`cID_Customer`) = 0,'',coalesce(`rv`.`ReviewLink`,`rv1`.`ReviewLink`,`rv2`.`ReviewLink`,'')) AS `ReviewLink` FROM (((((((((((`transfers` `t` left join `vehicles` `v` on(`t`.`CoachCompanyID` = `v`.`VehicleName`)) left join `vehicle_assignments` `va` on(`va`.`VehicleID` = `v`.`VehicleID` and `va`.`AssignmentDate` = `t`.`TransferDate`)) left join `drivers` `d` on(`d`.`DriverID` = `va`.`DriverID`)) left join `MyAthensTransfersAPP_users` `u` on(`d`.`UserID` = `u`.`ID`)) left join `counterparties` `cnt` on(`cnt`.`CoachName` = if(`t`.`CoachCompanyID` = '','xxx',`t`.`CoachCompanyID`))) left join `MyAthensTransfersAPP_users` `u2` on(`u2`.`ID` = `cnt`.`UserID`)) left join `counterparties` `cnt2` on(`cnt2`.`AssistantName` = if(ifnull(`t`.`Assistant`,'') = '','xxx',`t`.`Assistant`))) left join (select distinct `tours`.`TourName` AS `Tourname`,`tours`.`TourDescription` AS `Tourdescription` from `tours`) `trs` on(`trs`.`Tourname` = `t`.`To`)) left join `review_links` `rv` on(`rv`.`From` = `t`.`From` and `rv`.`To` = `t`.`To` and `rv`.`TransferType` = `t`.`TransferType`)) left join `review_links` `rv1` on(`rv1`.`TransferType` = `t`.`TransferType` and `rv1`.`From` = `t`.`From` and `rv1`.`To` = '')) left join `review_links` `rv2` on(`rv2`.`TransferType` = `t`.`TransferType` and `rv2`.`To` = `t`.`To` and `rv2`.`From` = '')) WHERE `t`.`Status` <> 'Cancelled' AND ifnull(`d`.`Fullname`,`cnt`.`CoachName`) is not null AND `t`.`TransferDate` between curdate() + interval 0 day and curdate() + interval 4 day ORDER BY ifnull(`d`.`Fullname`,`cnt`.`CoachName`) ASC, `t`.`TransferDate` ASC, `t`.`TransferTime_sort` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `v_transfers2`
--
DROP TABLE IF EXISTS `v_transfers2`;

CREATE ALGORITHM=UNDEFINED VIEW `v_transfers2`  AS SELECT row_number() over ( partition by `cnt`.`CoachName`,`t`.`TransferDate` order by `t`.`TransferTime_sort`) AS `row_num`, `t`.`TransferID` AS `TransferID`, `t`.`ImportDate` AS `ImportDate`, `t`.`cID_Customer` AS `cID_Customer`, `t`.`Pax` AS `Pax`, `t`.`SupplierCode` AS `SupplierCode`, `t`.`TransferType` AS `TransferType`, `t`.`TransferDate` AS `TransferDate`, `t`.`TransferTime` AS `TransferTime`, `t`.`From` AS `From`, `t`.`Flight_Ship_Num_From` AS `Flight_Ship_Num_From`, `t`.`To` AS `To`, ifnull(concat(`cnt2`.`CounterShortName`,' - ',`cnt2`.`Phones`),`t`.`Assistant`) AS `Assistant`, `t`.`Assistant_Price` AS `Assistant_Price`, `cnt2`.`CounterShortName` AS `cID_Assistant`, `t`.`CoachCompanyID` AS `CoachCompanyID`, `t`.`CarTypeID` AS `CarTypeID`, `t`.`PassengerName` AS `PassengerName`, `t`.`LeadName` AS `LeadName`, `t`.`LeadPhone` AS `LeadPhone`, `t`.`LesseeName` AS `LesseeName`, `t`.`DriverNotes` AS `Notes`, `t`.`Status` AS `Status`, `t`.`Platform` AS `Platform`, `t`.`ReferencePeriod` AS `ReferencePeriod`, `t`.`ReferenceYear` AS `ReferenceYear`, `t`.`PurchasePrice` AS `PurchasePrice`, `t`.`AssistantStatus` AS `AssistantStatus`, `t`.`CarTypeID` AS `CarType`, '' AS `VehicleName`, `cnt`.`CoachName` AS `Fullname`, '' AS `LicenceNumber`, '' AS `IDNumber`, `u2`.`username` AS `username`, ifnull(`t`.`Voucher_StartDt`,`t`.`Voucher_Start_Date_Calc`) AS `StartDate`, ifnull(`t`.`Voucher_StartTime`,`t`.`Voucher_Start_StartTime_Calc`) AS `StartTime`, ifnull(`t`.`Voucher_EndDt`,`t`.`Voucher_End_Date_Calc`) AS `EndDate`, ifnull(`t`.`Voucher_EndTime`,`t`.`Voucher_End_Time_Calc`) AS `EndTime`, `t`.`PlatformPrice` AS `PlatformPrice`, '' AS `LicencePlate`, date_format(timediff(str_to_date(concat(ifnull(`t`.`Voucher_EndDt`,`t`.`Voucher_End_Date_Calc`),' ',ifnull(`t`.`Voucher_EndTime`,`t`.`Voucher_End_Time_Calc`)),'%Y-%m-%d %H:%i:%s'),str_to_date(concat(ifnull(`t`.`Voucher_StartDt`,`t`.`Voucher_Start_Date_Calc`),' ',ifnull(`t`.`Voucher_StartTime`,`t`.`Voucher_Start_StartTime_Calc`)),'%Y-%m-%d %H:%i:%s')),'%H:%i') AS `duration`, `u2`.`email` AS `Email`, '' AS `VehicleLicence`, '' AS `VoucherName`, `cnt`.`UserID` AS `UserID`, `t`.`InvoicedRcvd` AS `InvoicedRcvd`, `t`.`Attachments` AS `Attachments`, `t`.`FLNM` AS `FLNM`, `cnt`.`CoachName` AS `CoachName`, if(`Get_Review_option`(`t`.`cID_Customer`) = 0,'',coalesce(`rv`.`ReviewLink`,`rv1`.`ReviewLink`,`rv2`.`ReviewLink`,'')) AS `ReviewLink` FROM ((((((`transfers` `t` left join `counterparties` `cnt` on(`cnt`.`CoachName` = if(`t`.`CoachCompanyID` = '','xxx',`t`.`CoachCompanyID`))) left join `MyAthensTransfersAPP_users` `u2` on(`u2`.`ID` = `cnt`.`UserID`)) left join `counterparties` `cnt2` on(`cnt2`.`AssistantName` = if(ifnull(`t`.`Assistant`,'') = '','xxx',`t`.`Assistant`))) left join `review_links` `rv` on(`rv`.`From` = `t`.`From` and `rv`.`To` = `t`.`To` and `rv`.`TransferType` = `t`.`TransferType`)) left join `review_links` `rv1` on(`rv1`.`TransferType` = `t`.`TransferType` and `rv1`.`From` = `t`.`From` and `rv1`.`To` = '')) left join `review_links` `rv2` on(`rv2`.`TransferType` = `t`.`TransferType` and `rv2`.`To` = `t`.`To` and `rv2`.`From` = '')) WHERE `t`.`Status` <> 'Cancelled' AND `cnt`.`CoachName` is not null AND year(`t`.`TransferDate`) >= 2026 ORDER BY `cnt`.`CoachName` ASC, `t`.`TransferDate` ASC, `t`.`TransferTime_sort` ASC ;

DELIMITER $$
--
-- Events
--
CREATE EVENT `Daily_Notifications` ON SCHEDULE EVERY 1 DAY STARTS '2024-01-03 09:41:05' ON COMPLETION NOT PRESERVE ENABLE DO BEGIN
CALL Daily_Notifications();
END$$

DELIMITER ;

-- --------------------------------------------------------
-- Added 2026-10-26 (database/legacy/changes/2026-10-26_tasks.sql)
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

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
