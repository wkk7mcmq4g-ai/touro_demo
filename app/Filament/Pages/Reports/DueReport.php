<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Reports\AssetsReport;

/** PHPRunner "Due - Χρωστάω" report: Liabilities by period and coach. */
class DueReport extends AssetsReport
{
    protected static string $permissionTable = 'Due';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Due - Χρωστάω';

    protected static ?string $title = 'Due - Χρωστάω';

    protected static ?string $slug = 'reports/due';

    protected function defaults(): array
    {
        return ['month', 'CoachCompanyID', 'Liabilities'];
    }
}
