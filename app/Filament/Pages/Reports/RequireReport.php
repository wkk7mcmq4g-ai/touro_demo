<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Reports\AssetsReport;

/** PHPRunner "Require - Μου χρωστάνε" report: Assets by period and supplier. */
class RequireReport extends AssetsReport
{
    protected static string $permissionTable = 'Require';

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'Require - Μου χρωστάνε';

    protected static ?string $title = 'Require - Μου χρωστάνε';

    protected static ?string $slug = 'reports/require';

    protected function defaults(): array
    {
        return ['month', 'Supplier', 'Assets'];
    }
}
