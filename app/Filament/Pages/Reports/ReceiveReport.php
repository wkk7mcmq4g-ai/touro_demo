<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Reports\AssetsReport;

/** PHPRunner "Receive - Τιμολόγια προς παραλαβή" report: PurchasePrice by period and coach. */
class ReceiveReport extends AssetsReport
{
    protected static string $permissionTable = 'Receive';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Receive - Τιμολόγια προς παραλαβή';

    protected static ?string $title = 'Receive - Τιμολόγια προς παραλαβή';

    protected static ?string $slug = 'reports/receive';

    protected function defaults(): array
    {
        return ['CoachCompanyID', 'month', 'PurchasePrice'];
    }
}
