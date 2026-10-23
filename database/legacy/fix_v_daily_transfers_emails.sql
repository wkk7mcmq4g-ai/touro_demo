-- Fixed v_daily_transfers_emails (run in phpMyAdmin on the database, e.g. kwi14599_myatdev):
--  * every text column is converted to one collation (utf8mb4), so the UNION no longer fails (#1271);
--  * empty fields no longer blank the whole SMS/Telegram text (CONCAT with NULL gives NULL);
--  * the unused cross join with the `sms` table is removed.
CREATE OR REPLACE VIEW `v_daily_transfers_emails` AS
select `v`.`aid` AS `ID`,
       `v`.`TransferDate` AS `TransferDate`,
       CONVERT(`v`.`Fullname` USING utf8mb4) COLLATE utf8mb4_general_ci AS `Fullname`,
       CONVERT(`v`.`Email` USING utf8mb4) COLLATE utf8mb4_general_ci AS `Email`,
       CONVERT('Driver' USING utf8mb4) COLLATE utf8mb4_general_ci AS `Type`,
       CONVERT(`v`.`VehicleName` USING utf8mb4) COLLATE utf8mb4_general_ci AS `VehicleName`,
       count(0) AS `Transfers_c`,
       0 AS `CheckPassenger`,
       CONVERT(group_concat(concat('*_Δρομολόγιο_* #',`v`.`row_num`,'\r\n\r\n','*Ημερομηνία:* ',date_format(`v`.`TransferDate`,'%d/%m/%Y'),'\r','*Ωρα Pickup:* ',ifnull(`v`.`TransferTime`,''),'\r','*Άτομα:* ',ifnull(`v`.`Pax`,''),'\r','*Τύπος:* ',ifnull(`v`.`TransferType`,''),'\r','*Σημείο Παραλαβής:* ',ifnull(`v`.`From`,''),'\r*Σημείο Αποβίβασης:* ',ifnull(`v`.`To`,''),'\r','*Πληροφορίες:* ',ifnull(`v`.`Flight_Ship_Num_From`,''),'\r','*Όνομα επιβάτη:* ',ifnull(`v`.`LeadName`,''),'\r','*Τηλέφωνο επιβάτη:* ',ifnull(`v`.`LeadPhone`,''),'\r','*Συνοδός - Ξεναγός:* ',ifnull(`v`.`Assistant`,''),'\r','*Οδηγός:* ',ifnull(`v`.`Fullname`,''),'-',ifnull(`v`.`DriverPhone`,''),'\r','*Παρατηρήσεις:* ',ifnull(`v`.`Notes`,''),'\r','*Review Link:* ',ifnull(`v`.`ReviewLink`,''),'\r','*Επιβάτες:* ',ifnull(`v`.`PassengerName`,'')) order by `v`.`row_num` ASC separator '\r\n\r\n') USING utf8mb4) COLLATE utf8mb4_general_ci AS `sms`,
       CONVERT(concat('*My Athens Transfers IKE: 0261E60000721601*','\r\n\r\n','*Ημερομηνία:* ',date_format(`v`.`TransferDate`,'%d/%m/%Y'),'\r\n\r\n','*Οδηγός:* ',ifnull(`v`.`Fullname`,''),'\r\n\r\n','*Συνολικά Δρομολόγια:* ',count(0),'\r\n\r\n','---------------------------------------','\r\n\r\n',group_concat(concat('*_Δρομολόγιο_* #',`v`.`row_num`,'\r\n\r\n','*Ημερομηνία:* ',date_format(`v`.`TransferDate`,'%d/%m/%Y'),'\n\n','*Ωρα Pickup:* ',ifnull(`v`.`TransferTime`,''),'\n\n','*Άτομα:* ',ifnull(`v`.`Pax`,''),'\n\n','*Τύπος:* ',ifnull(`v`.`TransferType`,''),'\n\n','*Σημείο Παραλαβής:* ',ifnull(`v`.`From`,''),'\n\n','*Σημείο Αποβίβασης:* ',ifnull(`v`.`To`,''),'\n\n','*Πληροφορίες:* ',ifnull(`v`.`Flight_Ship_Num_From`,''),'\n\n','*Όνομα επιβάτη:* ',ifnull(`v`.`LeadName`,''),'\n\n','*Τηλέφωνο επιβάτη:* ',ifnull(`v`.`LeadPhone`,''),'\n\n','*Συνοδός - Ξεναγός:* ',ifnull(`v`.`Assistant`,''),'\n\n','*Οδηγός:* ',ifnull(`v`.`Fullname`,''),'-',ifnull(`v`.`DriverPhone`,''),'\n\n','*Παρατηρήσεις:* ',ifnull(`v`.`Notes`,''),'\n\n','*Επιβάτες:* ',ifnull(`v`.`PassengerName`,'')) order by `v`.`row_num` ASC separator '\r\n\r\n')) USING utf8mb4) COLLATE utf8mb4_general_ci AS `telsms`,
       CONVERT(if(`Check_Read_Receipt`(`v`.`aid`,`v`.`TransferDate`) > 0,'Read','Not read') USING utf8mb4) COLLATE utf8mb4_general_ci AS `SendStatus`,
       `v`.`TelegramID` AS `TelegramID`,
       CONVERT(`v`.`username` USING utf8mb4) COLLATE utf8mb4_general_ci AS `username`
from `v_transfers` `v` group by 1,2,3,4,5,6
UNION ALL
select `v`.`assistantid` AS `ID`,
       `v`.`TransferDate` AS `TransferDate`,
       CONVERT(`v`.`cID_Assistant` USING utf8mb4) COLLATE utf8mb4_general_ci AS `Fullname`,
       CONVERT(`v`.`AssistantEmail` USING utf8mb4) COLLATE utf8mb4_general_ci AS `Email`,
       CONVERT('Assistant' USING utf8mb4) COLLATE utf8mb4_general_ci AS `Assistant`,
       CONVERT('' USING utf8mb4) COLLATE utf8mb4_general_ci AS `Name_exp_6`,
       count(0) AS `Transfers_c`,
       0 AS `CheckPassenger`,
       CONVERT(group_concat(concat('*Ημερομηνία:* ',date_format(`v`.`TransferDate`,'%d/%m/%Y'),'\r','*Ωρα Pickup:* ',ifnull(`v`.`TransferTime`,''),'\r','*Άτομα:* ',ifnull(`v`.`Pax`,''),'\r','*Τύπος:* ',ifnull(`v`.`TransferType`,''),'\r','*Σημείο Παραλαβής:* ',ifnull(`v`.`From`,''),'\r*Σημείο Αποβίβασης:* ',ifnull(`v`.`To`,''),'\r','*Πληροφορίες:* ',ifnull(`v`.`Flight_Ship_Num_From`,''),'\r','*Όνομα επιβάτη:* ',ifnull(`v`.`LeadName`,''),'\r','*Τηλέφωνο επιβάτη:* ',ifnull(`v`.`LeadPhone`,''),'\r','*Συνοδός - Ξεναγός:* ',ifnull(`v`.`Assistant`,''),'\r','*Οδηγός:* ',ifnull(`v`.`Fullname`,''),'-',ifnull(`v`.`DriverPhone`,''),'\r','*Παρατηρήσεις:* ',ifnull(`v`.`Notes`,''),'\r','*Επιβάτες:* ',ifnull(`v`.`PassengerName`,'')) separator '\r\n\r\n') USING utf8mb4) COLLATE utf8mb4_general_ci AS `sms`,
       CONVERT(group_concat(concat('*Ημερομηνία:* ',date_format(`v`.`TransferDate`,'%d/%m/%Y'),'\r','*Ωρα Pickup:* ',ifnull(`v`.`TransferTime`,''),'\r','*Άτομα:* ',ifnull(`v`.`Pax`,''),'\r','*Τύπος:* ',ifnull(`v`.`TransferType`,''),'\r','*Σημείο Παραλαβής:* ',ifnull(`v`.`From`,''),'\r*Σημείο Αποβίβασης:* ',ifnull(`v`.`To`,''),'\r','*Πληροφορίες:* ',ifnull(`v`.`Flight_Ship_Num_From`,''),'\r','*Όνομα επιβάτη:* ',ifnull(`v`.`LeadName`,''),'\r','*Τηλέφωνο επιβάτη:* ',ifnull(`v`.`LeadPhone`,''),'\r','*Συνοδός - Ξεναγός:* ',ifnull(`v`.`Assistant`,''),'\r','*Οδηγός:* ',ifnull(`v`.`Fullname`,''),'-',ifnull(`v`.`DriverPhone`,''),'\r','*Παρατηρήσεις:* ',ifnull(`v`.`Notes`,''),'\r','*Επιβάτες:* ',ifnull(`v`.`PassengerName`,'')) separator '\r\n\r\n') USING utf8mb4) COLLATE utf8mb4_general_ci AS `telsms`,
       CONVERT(if(`s`.`ID` is null,'Not read','Read') USING utf8mb4) COLLATE utf8mb4_general_ci AS `SendStatus`,
       `v`.`TelegramID` AS `TelegramID`,
       CONVERT(`v`.`username` USING utf8mb4) COLLATE utf8mb4_general_ci AS `username`
from (`v_transfers` `v` left join `emails_sent` `s` on(`v`.`assistantid` = `s`.`aID` and `v`.`TransferDate` = `s`.`TransferDate`)) where `v`.`assistantid` is not null group by 1,2,3,4,5,6;
