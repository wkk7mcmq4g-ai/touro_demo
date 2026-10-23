<?php

namespace App\Filament\Reports;

use App\Models\ReportRow;
use App\Support\CsvDownload;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Column;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * A PHPRunner list page over a view or SQL datasource: read-only rows, totals, filters and
 * an Excel (CSV) export of whatever is filtered.
 */
abstract class ListReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.reports.list-report';

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected Width|string|null $maxContentWidth = Width::Full;

    /** PHPRunner table whose permission mask applies. */
    protected static string $permissionTable;

    abstract protected function source(): Builder;

    /** @return list<Column> */
    abstract protected function columns(): array;

    protected function filters(): array
    {
        return [];
    }

    protected function defaultSort(): ?array
    {
        return null;
    }

    public static function canAccess(): bool
    {
        return Permissions::allows(auth()->user(), static::$permissionTable, 'view');
    }

    public function table(Table $table): Table
    {
        [$column, $direction] = $this->defaultSort() ?? [null, null];

        return $table
            ->query(fn () => ReportRow::over($this->source()))
            ->stackedOnMobile()
            ->columns($this->columns())
            ->filters($this->filters())
            ->when($column, fn (Table $table) => $table->defaultSort($column, $direction))
            ->emptyStateHeading('Δεν βρέθηκαν εγγραφές')
            ->paginated([25, 50, 100, 200])
            ->defaultPaginationPageOption(50)
            ->headerActions([
                Action::make('export')->label('Export')->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
                    ->visible(fn (): bool => Permissions::allows(auth()->user(), static::$permissionTable, 'export'))
                    ->action(fn () => CsvDownload::make(
                        Str::slug(static::getNavigationLabel()) ?: 'report',
                        collect($this->getTable()->getColumns())->mapWithKeys(fn (Column $c): array => [$c->getName() => strip_tags((string) $c->getLabel())])->all(),
                        $this->getFilteredSortedTableQuery()->toBase()->cursor(),
                    )),
            ]);
    }
}
