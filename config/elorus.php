<?php

return [

    /*
    | Off unless ELORUS_ENABLED=true: a development copy has the live Elorus key in its
    | elorus_Settings table, so it must not be able to issue real documents by accident.
    */
    'enabled' => (bool) env('ELORUS_ENABLED', false),

    /*
    | Demo showcase: nothing about Elorus on screen — buttons (invoicing actions in Transfers
    | and Charges, "Προσθήκη στο Elorus" on partners), screens (Elorus Settings / Contacts)
    | and fields (Elorus ID, myDATA, Elorus taxi number, the "In Elorus" filter). Hidden
    | fields keep their stored values. Set ELORUS_UI=true to show them again.
    */
    'ui' => (bool) env('ELORUS_UI', false),

    'url' => env('ELORUS_URL', 'https://api.elorus.com/v1.2/'),

    // Override the elorus_Settings table (OrgID / apikey) when set.
    'token' => env('ELORUS_API_TOKEN'),
    'organization' => env('ELORUS_ORGANIZATION_ID'),

    /*
    | Values from the PHPRunner buttons (spec/buttons/New_Button28, 80, 29, Get_Invoice_PDF).
    */

    // Receipts are issued to this counterparty (counterparties.CounterShortName).
    'private_individual' => 'Private Individual',

    // myat_transfers_files.Type of a downloaded invoice/receipt PDF (optionvalues ID).
    'pdf_file_type' => 1357,

    // Added to the receipt line: " / Viator ", " / Get Your Guide ".
    'supplier_labels' => [11 => 'Viator', 117 => 'Viator', 37 => 'Get Your Guide'],

    // "Get invoice: Auto" also downloads the PDF for platform bookings of these customers.
    'auto_pdf_customers' => [17],

    // Taxi credit note (receipt replacement).
    'taxi_document_type' => '2208819517323216748',
    'taxi_mydata_document_type' => '11.4',

];
