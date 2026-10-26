<?php

return [

    /*
    | Demo showcase: no "Εισερχόμενο έμβασμα" (incoming payment) button, and so no
    | "Τιμολόγηση" button at the top of the Transfers list. TRANSFERS_INCOMING_PAYMENT=true
    | shows it again.
    */
    'incoming_payment' => (bool) env('TRANSFERS_INCOMING_PAYMENT', false),

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

];
