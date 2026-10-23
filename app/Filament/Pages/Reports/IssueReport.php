<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Reports\AssetsReport;

/** PHPRunner "Issue - Τιμολόγια προς έκδοση" report: SalePrice by period and supplier. */
class IssueReport extends AssetsReport
{
    protected static string $permissionTable = 'Issue';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Issue - Τιμολόγια προς έκδοση';

    protected static ?string $title = 'Issue - Τιμολόγια προς έκδοση';

    protected static ?string $slug = 'reports/issue';

    protected function defaults(): array
    {
        return ['month', 'Supplier', 'SalePrice'];
    }
}
