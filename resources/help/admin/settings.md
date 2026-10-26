---
title: Χρήστες και ρυθμίσεις
group: Ρυθμίσεις
order: 610
pages: UserResource, AccessLogResource, ElorusSettings, ElorusContacts, OptionValueResource
---
## Users & roles
Οι χρήστες είναι **οι ίδιοι με του PHPRunner**: ό,τι αλλάξετε εδώ ισχύει και στις δύο εφαρμογές.
- **Login**: username (μοναδικό), password, **Active (can log in)**.
- **Profile & role**: όνομα, email, **Group**.
  - **Admin**, **Entry (office)**: back office.
  - **Driver**, **Driver (holidays)**, **Driver (vehicle log)**: portal οδηγών (το δεύτερο βλέπει και τις άδειες, το τρίτο το ιστορικό οχημάτων).
  - **Partner**, **Supplier**, **Supplier (hotel)**, **Coach company**: portal πελατών/συνεργατών. Στους Supplier επιλέγετε και ποιον πελάτη βλέπουν.
- **Κωδικός**: υποχρεωτικός σε νέο χρήστη· στην αλλαγή αφήστε κενό για να μείνει ο ίδιος.
- Για οδηγό: συνδέστε το username και στην καρτέλα του οδηγού (**Όνομα χρήστη (Touro)**), δείτε [Στόλος και οδηγοί](help:fleet).

## Access Log
Συνδέσεις και αποσυνδέσεις και από τις δύο εφαρμογές (in πράσινο, out γκρι), με φίλτρα χρήστη και ημερομηνίας.

## Elorus Settings και Elorus Contacts
- **Settings**: Organization ID, API key (δεν εμφανίζεται ποτέ· αφήστε κενό για να μείνει), Private Individual ID, Default Product ID. Κάτω γράφει αν το Elorus είναι ενεργό σε αυτόν τον server.
- **Contacts**: οι επαφές του Elorus, διαβάζονται ζωντανά από το Elorus.

## Field Settings
Οι τιμές των λιστών επιλογής σε όλη την εφαρμογή (τύποι transfer, car types, status, τύποι αρχείων, κατηγορίες εξόδων…) και τα **χρώματά** τους, ομαδοποιημένες ανά λίστα.
- **Drivers see it** / **Partners see it**: π.χ. ποιοι τύποι αρχείων φαίνονται στον οδηγό ή στον συνεργάτη.
- Αν σβήσετε μια τιμή, οι εγγραφές που την έχουν την κρατούν· απλώς δεν προτείνεται πια.
