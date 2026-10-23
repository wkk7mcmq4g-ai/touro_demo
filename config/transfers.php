<?php

return [

    /*
    | Columns that can be edited directly in the transfers list ("Edit in list").
    | The equivalent of PHPRunner's inline-edit field setting; this list is exactly
    | the one from the PHPRunner project. Add or remove column names here.
    */
    'inline_edit' => [
        'LesseeName', 'PassengerName', 'Pax', 'TransferTime', 'Flight_Ship_Num_From', 'Notes',
        'Assistant_Price', 'To', 'From', 'TransferDate', 'TransferType', 'LeadPhone', 'LeadName',
        'CoachCompanyID', 'CarTypeID', 'Status', 'PlatformPrice', 'PurchasePrice', 'SalePrice',
        'PricePaid', 'PriceRcvd', 'Expense', 'TobeInvoiced', 'DriverNotes', 'PaymentOnHoldFlag',
        'Assistant', 'Platform', 'Voucher_StartDt', 'Voucher_StartTime', 'Voucher_EndDt',
        'Voucher_EndTime', 'OLP',
    ],

    /*
    | Customers (cID_Customer) for whom the lessee is the lead passenger: on a new
    | transfer, typing the lead name fills the lessee too. From the PHPRunner add page.
    */
    'lessee_is_lead_for_customers' => [11, 37, 117],

];
