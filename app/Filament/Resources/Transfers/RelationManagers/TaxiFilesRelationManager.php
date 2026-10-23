<?php

namespace App\Filament\Resources\Transfers\RelationManagers;

/**
 * PHPRunner detail `myat_transfers_files1_taxi`: the taxi receipts (files of type 1363),
 * the ones drivers upload and the taxi receipts are issued from.
 */
class TaxiFilesRelationManager extends FilesRelationManager
{
    protected static ?string $title = 'Taxi receipts';

    protected const TABLE = 'myat_transfers_files1_taxi';

    protected const FILE_TYPE = 1363;
}
