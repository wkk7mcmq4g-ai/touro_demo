<?php

/*
| Daily dispatch (PHPRunner v_transfers): addresses and texts from the PHPRunner buttons
| New_Button66 (OLP request) and Send_Assistant_Email (tour guide assignment).
*/
return [

    'olp' => [
        'to' => env('DISPATCH_OLP_TO', 'crsecurity@olp.gr'),
        'cc' => env('DISPATCH_OLP_CC', 'ksaveri@hcg.gr'),
        'bcc' => env('DISPATCH_OLP_BCC', 'info@myathenstransfers.com'),
    ],

    'reply_to' => env('DISPATCH_REPLY_TO', 'aris@myathenstransfers.com'),

    'operations_phone' => '+30 6976 684 995',

    'signature_html' => '<p><strong>Aris Biliktsis</strong><br>Operations Manager</p>'
        .'<p>My Athens Transfers<br>Tour Operator<br>visit us: www.myathenstransfers.com<br>mob: +30 697 668 4995</p>',

];
