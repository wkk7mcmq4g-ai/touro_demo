<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Reports\ListReport;
use App\Filament\Resources\Transfers\TransferResource;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** PHPRunner "Duplicates" (v_duplicates): the same supplier code entered more than once on a day. */
class Duplicates extends ListReport
{
    protected static string $permissionTable = 'v_duplicates';

    protected static ?int $navigationSort = 12;

    protected static ?string $navigationLabel = 'Duplicates';

    protected static ?string $title = 'Duplicates';

    protected static ?string $slug = 'reports/duplicates';

    protected function source(): Builder
    {
        return DB::table('v_duplicates');
    }

    protected function defaultSort(): ?array
    {
        return ['TransferDate', 'desc'];
    }

    protected function columns(): array
    {
        return [
            TextColumn::make('SupplierName')->label('Supplier')->searchable()->sortable(),
            TextColumn::make('SupplierCode')->label('Supplier code')->searchable()
                ->url(fn ($record): string => TransferResource::getUrl('index', ['search' => $record->SupplierCode, 'tab' => 'all'])),
            TextColumn::make('TransferDate')->label('Transfer date')->date('d/m/Y')->sortable(),
            TextColumn::make('Transfers')->numeric()->alignEnd()->sortable()->badge()->color('danger'),
        ];
    }
}
