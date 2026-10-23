<?php

/*
| Partner companies that run on their own copy of the app (PHPRunner "LS Tours", "Beskos",
| ... jobs lists). Their jobs for MyAT are read from their database's v_transfers2 view;
| "move" copies one into our transfers with the MoveToMyTransfers procedure, which has
| the same database names and codes built in.
|
| database      their database on this server
| code          the MoveToMyTransfers code (also in the procedure)
| prefix        how a copied job's SupplierCode starts here ("LS - 123")
| coach         the name MyAT has as coach company in their app
| our_customer  MyAT's customer ID in their app (the jobs we give them)
*/
return [
    'ls' => ['label' => 'LS Tours', 'database' => env('PARTNER_DB_LS', 'kwi14599_lstours'), 'code' => 'LS', 'prefix' => 'LS - ', 'coach' => 'MyAthensTransfers', 'our_customer' => 71],
    'beskos' => ['label' => 'Beskos', 'database' => env('PARTNER_DB_BESKOS', 'kwi14599_beskos'), 'code' => 'Beskos', 'prefix' => 'Beskos - ', 'coach' => 'MYATHENS TRANSFER', 'our_customer' => 3],
    'keep' => ['label' => 'Keep', 'database' => env('PARTNER_DB_KEEP', 'kwi14599_keep'), 'code' => 'Keep', 'prefix' => 'Keep - ', 'coach' => 'MYAT', 'our_customer' => 58],
    'tta' => ['label' => 'TTA', 'database' => env('PARTNER_DB_TTA', 'kwi14599_tta'), 'code' => 'TTA', 'prefix' => 'TTA - ', 'coach' => 'MyAthensTransfers', 'our_customer' => 112],
    'euro' => ['label' => 'Euro', 'database' => env('PARTNER_DB_EURO', 'kwi14599_euro'), 'code' => 'EURO', 'prefix' => 'EURO - ', 'coach' => 'ΜΠΙΛΙΚΤΣΗΣ', 'our_customer' => 112],
    'yomadic' => ['label' => 'Yomadic', 'database' => env('PARTNER_DB_YOMADIC', 'kwi14599_yomadic'), 'code' => 'Yomadic', 'prefix' => 'Yomadic - ', 'coach' => 'My Athens Transfer', 'our_customer' => 105],
];
