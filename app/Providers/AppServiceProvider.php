<?php

namespace App\Providers;

use App\Auth\PhpRunnerUserProvider;
use App\Filament\Resources\Transfers\Pages\ListTransfers;
use App\Listeners\LogAccess;
use App\Support\ExcelFilters;
use App\Support\ImmersiveTables;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Support\Enums\Width;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\Columns\Column;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Filament\View\PanelsRenderHook;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Livewire\Component;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // `php artisan serve` hands the dev server only a few environment variables. On Windows
        // PHP then has no TEMP/TMP, can't buffer larger requests (e.g. "Edit in list") and drops them.
        if (PHP_OS_FAMILY === 'Windows') {
            ServeCommand::$passthroughVariables = array_values(array_unique([
                ...ServeCommand::$passthroughVariables, 'TEMP', 'TMP', 'USERPROFILE', 'LOCALAPPDATA',
            ]));
        }
    }

    public function boot(): void
    {
        // Log in against the PHPRunner users table (see config/auth.php).
        Auth::provider('phprunner', fn ($app, array $config) => new PhpRunnerUserProvider($app['hash'], $config['model']));

        // Greek conventions for every date field: dd/mm/yyyy, weeks start on Monday.
        DatePicker::configureUsing(fn (DatePicker $picker) => $picker
            ->native(false)
            ->displayFormat('d/m/Y')
            ->firstDayOfWeek(1)
            ->closeOnDateSelection());
        DateTimePicker::configureUsing(fn (DateTimePicker $picker) => $picker
            ->native(false)
            ->displayFormat('d/m/Y H:i')
            ->firstDayOfWeek(1));

        // Suggestion lists for the type-ahead cells of "Edit in list", sent once per page.
        FilamentView::registerRenderHook(
            PanelsRenderHook::PAGE_END,
            fn () => (Livewire::current()?->inlineEditing ?? false)
                ? view('filament.transfers.inline-lookups')
                : '',
            scopes: ListTransfers::class,
        );

        // Every table: columns can be reordered and hidden (Filament's column manager), resized,
        // and filtered Excel-style from the header. Layouts are saved per user (TableLayouts).
        Table::configureUsing(fn (Table $table) => $table
            // The filter button opens a panel that slides in from the right, one filter per row.
            ->filtersLayout(FiltersLayout::Modal)
            ->filtersTriggerAction(fn (Action $action) => $action->slideOver()->label('Φίλτρα'))
            ->filtersFormWidth(Width::Medium)
            ->filtersFormColumns(1)
            ->reorderableColumns()
            ->persistColumnsInSession()
            ->modifyQueryUsing(fn (Builder $query, $livewire, bool $isResolvingRecord = false): Builder => $livewire instanceof Component
                ? ExcelFilters::apply($query, $livewire, $isResolvingRecord)
                : $query));

        // The real column name on each header, for the Excel-style funnels (Filament's own class is kebab-cased).
        Column::configureUsing(fn (Column $column) => $column->extraHeaderAttributes(
            fn (Column $column): array => ['data-myat-col' => $column->getName()],
            merge: true,
        ));

        // The header funnels and column widths talk to the table's own Livewire component.
        \Livewire\on('call', function ($component, string $method, array $params, $context, $returnEarly) {
            if (! $component instanceof HasTable || ! in_array($method, ['excelFilterValues', 'applyExcelFilter', 'clearExcelFilters', 'saveColumnWidths'], true)) {
                return;
            }

            // Reading a column's values or saving widths changes nothing on screen: answer without
            // re-rendering the whole table (that re-render was most of the wait).
            if (in_array($method, ['excelFilterValues', 'saveColumnWidths'], true)) {
                $component->skipRender();
            }

            match ($method) {
                'excelFilterValues' => $returnEarly(ExcelFilters::values($component, (string) ($params[0] ?? ''))),
                'applyExcelFilter' => $returnEarly(self::filtered($component, fn () => ExcelFilters::set($component, (string) ($params[0] ?? ''), is_array($params[1] ?? null) ? $params[1] : null))),
                'clearExcelFilters' => $returnEarly(self::filtered($component, fn () => ExcelFilters::clear($component))),
                'saveColumnWidths' => $returnEarly(self::saveColumnWidths($component, $params[0] ?? [])),
            };
        });

        FilamentView::registerRenderHook(PanelsRenderHook::BODY_END, fn () => auth()->check() ? view('filament.tables.excel-and-widths').view('filament.tables.immersive') : '');

        // Wide tables that get Full screen + zoom (phones sideways, tablets, computers).
        ImmersiveTables::register([ListTransfers::class]);

        // Same access_log rows the PHPRunner login/logout events wrote.
        Event::listen(Login::class, [LogAccess::class, 'login']);
        Event::listen(Logout::class, [LogAccess::class, 'logout']);
    }

    /** Run a column-filter change, then tell widgets over the table (e.g. transfer totals) to redraw. */
    private static function filtered(Component $component, \Closure $change): null
    {
        $change();
        $component->dispatch('myat-table-filtered');

        return null;
    }

    /** Column widths dragged in a table's header, per user and table (saved by TableLayouts). */
    private static function saveColumnWidths(Component $component, mixed $widths): void
    {
        $clean = collect(is_array($widths) ? $widths : [])
            ->filter(fn ($w, $k) => is_string($k) && preg_match('/^[A-Za-z0-9_.-]{1,64}$/', $k) && is_numeric($w))
            ->map(fn ($w) => max(40, min(1200, (int) $w)))
            ->take(100)->all();

        $all = (array) session('myat.widths', []);
        if ($clean === []) {
            unset($all[$component->getName()]);
        } else {
            $all[$component->getName()] = $clean;
        }
        session()->put('myat.widths', $all);
    }
}
