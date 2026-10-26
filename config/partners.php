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
/*
| Demo showcase: no partners. MyAT's entries would read LS Tours', Beskos', Keep's, TTA's,
| Euro's and Yomadic's real jobs. With none, the Partner jobs page and the dashboard's
| "Νέες αναθέσεις συνεργατών" panel are not shown.
*/
return [];
