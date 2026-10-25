<?php

/*
| Daily dispatch (PHPRunner v_transfers): addresses from the PHPRunner buttons New_Button66
| (OLP request) and Send_Assistant_Email (tour guide assignment). The business's own
| addresses, phone and signature are in config/business.php.
*/
return [

    // Piraeus Port Authority (OLP) entry requests: the port's addresses, the same for every business.
    'olp' => [
        'to' => env('DISPATCH_OLP_TO', 'crsecurity@olp.gr'),
        'cc' => env('DISPATCH_OLP_CC', 'ksaveri@hcg.gr'),
        // Blind copy to the business; empty: the business's general mailbox.
        'bcc' => env('DISPATCH_OLP_BCC'),
    ],

    // Replies to guide assignments and OLP requests; empty: the operations desk.
    'reply_to' => env('DISPATCH_REPLY_TO'),

];
