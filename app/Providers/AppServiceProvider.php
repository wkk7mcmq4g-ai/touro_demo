<?php

namespace App\Providers;

use App\Auth\PhpRunnerUserProvider;
use App\Filament\Resources\Transfers\Pages\ListTransfers;
use App\Listeners\LogAccess;
use App\Navigation\PersonalNavigationManager;
use App\Support\ExcelFilters;
use App\Support\Help;
use App\Support\ImmersiveTables;
use App\Support\SavedFilters;
use App\Support\TableExports;
use App\Support\TablePresets;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Navigation\NavigationManager;
use Filament\Pages\BasePage;
use Filament\Support\Enums\Width;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\Columns\Column;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\ColumnManagerLayout;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Tables\View\TablesRenderHook;
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
        // Each user's own arrangement of the side menu (favourites, order, own groups).
        $this->app->scoped(NavigationManager::class, fn (): NavigationManager => new PersonalNavigationManager);

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

        // Save / Cancel stay in reach at the bottom of the screen on every form page (as on transfers).
        BasePage::$formActionsAreSticky = true;

        // Every select filter lets you pick several values (custom queries use FilterValues).
        SelectFilter::configureUsing(fn (SelectFilter $filter) => $filter->multiple());

        // Every table: columns can be reordered and hidden (Filament's column manager), resized,
        // and filtered Excel-style from the header. Layouts are saved per user (TableLayouts).
        Table::configureUsing(fn (Table $table) => $table
            // Row buttons (edit, ...) on the left of every table, before the columns; card layouts
            // (the drivers' jobs) keep them under the card.
            ->recordActionsPosition(fn (Table $table): ?RecordActionsPosition => $table->getContentGrid() || $table->hasColumnsLayout() ? null : RecordActionsPosition::BeforeColumns)
            // The filter button opens a panel that slides in from the right, one filter per row.
            ->filtersLayout(FiltersLayout::Modal)
            ->filtersTriggerAction(fn (Action $action) => $action->slideOver()->label('Φίλτρα')
                // Saved filters (apply, delete, save the panel's filters) under the filters.
                ->modalContentFooter(fn () => view('filament.tables.saved-in-panel')))
            ->filtersFormWidth(Width::Medium)
            ->filtersFormColumns(1)
            ->reorderableColumns()
            // Long tables (Transfers has 57 columns): the column picker opens as a wide window with
            // the columns in a grid and a search box, instead of one long dropdown list.
            ->columnManagerLayout(fn (Table $table): ColumnManagerLayout => self::hasManyColumns($table) ? ColumnManagerLayout::Modal : ColumnManagerLayout::Dropdown)
            ->columnManagerColumns(fn (Table $table): int|array => self::hasManyColumns($table) ? ['default' => 1, 'sm' => 2, 'lg' => 3, 'xl' => 4] : 1)
            ->columnManagerWidth(fn (Table $table): ?Width => self::hasManyColumns($table) ? Width::FiveExtraLarge : null)
            ->columnManagerTriggerAction(fn (Action $action) => $action
                ->modalHeading('Στήλες')
                ->modalContent(view('filament.tables.column-search')))
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
            if (! $component instanceof HasTable || ! in_array($method, ['excelFilterValues', 'applyExcelFilter', 'clearExcelFilters', 'saveColumnWidths',
                'exportColumns', 'exportTable', 'tablePresets', 'savePreset', 'savePanelPreset', 'deletePreset', 'applyFilterPreset'], true)) {
                return;
            }

            // Reading a column's values or saving widths changes nothing on screen: answer without
            // re-rendering the whole table (that re-render was most of the wait).
            if (in_array($method, ['excelFilterValues', 'saveColumnWidths', 'exportColumns', 'exportTable', 'tablePresets', 'savePreset', 'deletePreset'], true)) {
                $component->skipRender();
            }

            match ($method) {
                'excelFilterValues' => $returnEarly(ExcelFilters::values($component, (string) ($params[0] ?? ''))),
                'applyExcelFilter' => $returnEarly(self::filtered($component, fn () => ExcelFilters::set($component, (string) ($params[0] ?? ''), is_array($params[1] ?? null) ? $params[1] : null))),
                'clearExcelFilters' => $returnEarly(self::filtered($component, fn () => ExcelFilters::clear($component))),
                'saveColumnWidths' => $returnEarly(self::saveColumnWidths($component, $params[0] ?? [])),
                // Excel export with chosen columns, saved exports and saved filters (per user).
                'exportColumns' => $returnEarly(TableExports::allowed($component) ? TableExports::columns($component) : []),
                'exportTable' => $returnEarly(TableExports::export($component, array_values((array) ($params[0] ?? [])), (string) ($params[1] ?? ''))),
                'tablePresets' => $returnEarly(TablePresets::list(($params[0] ?? '') === 'export' ? TablePresets::EXPORT : TablePresets::FILTERS, SavedFilters::key($component))),
                'savePreset' => $returnEarly(($params[0] ?? '') === 'export'
                    ? TablePresets::save(TablePresets::EXPORT, SavedFilters::key($component), (string) ($params[1] ?? ''), ['columns' => array_values(array_filter((array) ($params[2] ?? []), 'is_string'))])
                    : TablePresets::save(TablePresets::FILTERS, SavedFilters::key($component), (string) ($params[1] ?? ''), SavedFilters::current($component))),
                // From the filter side panel: apply what is set there, then save it.
                'savePanelPreset' => $returnEarly(self::savePanelPreset($component, (string) ($params[1] ?? ''))),
                'deletePreset' => $returnEarly(TablePresets::delete(($params[0] ?? '') === 'export' ? TablePresets::EXPORT : TablePresets::FILTERS, SavedFilters::key($component), (int) ($params[1] ?? 0))),
                'applyFilterPreset' => $returnEarly(self::filtered($component, fn () => ($preset = TablePresets::find(TablePresets::FILTERS, SavedFilters::key($component), (int) ($params[0] ?? 0)))
                    ? SavedFilters::apply($component, $preset['data']) : abort(404)) ?? array_keys(ExcelFilters::active($component))),
            };
        });

        // Transfers: the Ημερομηνίες shortcuts menu and the quick "Ακυρωμένα" toggle next to the search box.
        FilamentView::registerRenderHook(TablesRenderHook::TOOLBAR_SEARCH_BEFORE, fn (): string => Livewire::current() instanceof ListTransfers
            ? view('filament.transfers.date-shortcuts')->render().view('filament.transfers.cancelled-toggle')->render() : '');

        // Transfers: the Coach window opened by a double-click on a Coach cell (stays in the browser).
        FilamentView::registerRenderHook(PanelsRenderHook::PAGE_END, fn (): string => auth()->user()?->may('transfers', 'edit')
            ? view('filament.transfers.coach-editor')->render() : '', scopes: ListTransfers::class);

        FilamentView::registerRenderHook(PanelsRenderHook::BODY_END, fn () => auth()->check() ? view('filament.tables.excel-and-widths').view('filament.tables.immersive') : '');

        // The filter panel's dropdowns and date pickers are Filament components loaded on demand,
        // so the first open of the panel waited for them to download. Fetch them with the page.
        FilamentView::registerRenderHook(PanelsRenderHook::HEAD_END, fn (): string => auth()->check()
            ? collect(['select', 'date-time-picker'])
                ->map(fn (string $c): string => '<link rel="modulepreload" href="'.e(FilamentAsset::getAlpineComponentSrc($c, 'filament/forms')).'">')
                ->implode('')
            : '');

        // Every table's toolbar: Excel export (chosen columns, saved exports) and saved filters.
        FilamentView::registerRenderHook(TablesRenderHook::TOOLBAR_END, function (): string {
            $component = Livewire::current();

            return $component instanceof HasTable && auth()->check()
                ? view('filament.tables.export-and-saved', ['canExport' => TableExports::allowed($component), 'hasFilters' => TableExports::columns($component) !== []])->render()
                : '';
        });

        // Βοήθεια: a "?" next to the buttons of every screen that has a help article, and the side
        // panel it opens (App\Support\Help, resources/help).
        FilamentView::registerRenderHook(PanelsRenderHook::PAGE_HEADER_ACTIONS_BEFORE, function (): string {
            $page = Livewire::current();
            $panel = filament()->getCurrentPanel()?->getId();
            if (! $page || ! in_array($panel, Help::PANELS, true) || ! ($article = Help::forPage($panel, $page::class))) {
                return '';
            }

            return view('filament.help.button', ['url' => route('help.content', ['panel' => $panel, 'slug' => $article['slug']])])->render();
        });
        FilamentView::registerRenderHook(PanelsRenderHook::BODY_END, fn (): string => auth()->check() ? view('filament.help.drawer')->render() : '');

        // Wide tables that get Full screen + zoom (phones sideways, tablets, computers).
        ImmersiveTables::register();

        // Same access_log rows the PHPRunner login/logout events wrote.
        Event::listen(Login::class, [LogAccess::class, 'login']);
        Event::listen(Logout::class, [LogAccess::class, 'logout']);
    }

    /** Run a column-filter change, then tell widgets over the table (e.g. transfer totals) to redraw. */
    /** Saving from the filter side panel: what is set in the panel is applied first, then saved. */
    private static function savePanelPreset(Component $component, string $name): int
    {
        if (method_exists($component, 'applyTableFilters')) {
            $component->applyTableFilters();
        }
        $component->dispatch('myat-table-filtered');

        return TablePresets::save(TablePresets::FILTERS, SavedFilters::key($component), $name, SavedFilters::current($component));
    }

    private static function filtered(Component $component, \Closure $change): null
    {
        $change();
        $component->dispatch('myat-table-filtered');

        return null;
    }

    /** Tables whose column picker needs room: more columns than fit a dropdown comfortably. */
    private static function hasManyColumns(Table $table): bool
    {
        return count($table->getColumns()) > 14;
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
