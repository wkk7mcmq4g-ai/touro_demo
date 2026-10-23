<?php

namespace App\Filament\Resources\Expenses;

use App\Filament\Concerns\HandlesLegacyFiles;
use App\Filament\Concerns\UsesPhpRunnerPermissions;
use App\Filament\Resources\Expenses\Pages\CreateExpense;
use App\Filament\Resources\Expenses\Pages\EditExpense;
use App\Filament\Resources\Expenses\Pages\ListExpenses;
use App\Models\Expense;
use App\Support\LegacyFiles;
use App\Support\Lookups;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Count;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * PHPRunner "Συναλλαγές" (expenses): money in and out of the two companies (ΑΤΟΜΙΚΗ / ΙΚΕ)
 * that is not a transfer. The ΙΚΕ and ΑΤΟΜΙΚΗ balances feed the Θέση page (v_netposition).
 */
class ExpenseResource extends Resource
{
    use HandlesLegacyFiles;
    use UsesPhpRunnerPermissions;

    protected static string $permissionTable = 'expenses';

    protected static ?string $model = Expense::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsUpDown;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Συναλλαγές';

    protected static ?string $modelLabel = 'transaction';

    protected static ?string $pluralModelLabel = 'Συναλλαγές';

    protected static ?string $slug = 'expenses';

    public const COMPANIES = ['ΑΤΟΜΙΚΗ' => 'ΑΤΟΜΙΚΗ', 'ΙΚΕ' => 'ΙΚΕ'];

    public const FLAGS = ['Greg' => 'Greg', 'Aris' => 'Aris', 'Other' => 'Other'];

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make()->columnSpanFull()->tabs([
                Tab::make('Στοιχεία Εξόδων')->columns(3)->schema([
                    Select::make('Company')->label('Εταιρεία')->options(self::COMPANIES)->required(),
                    DatePicker::make('ExpenseDate')->label('Ημερομηνία')->default(now())->required(),
                    Select::make('ExpenseCategoryID')->label('Κατηγορία')->options(fn (): array => Lookups::optionIds('ExpenseCategory'))->searchable(),
                    Textarea::make('ExpenseReason')->label('Αιτιολογία')->required()->rows(2)->maxLength(500)->columnSpanFull(),
                    TextInput::make('ExpenseAmountRcvd')->label('Είσπραξη')->numeric()->prefix('€')->default(0),
                    TextInput::make('ExpenseAmountPaid')->label('Πληρωμή')->numeric()->prefix('€')->default(0),
                    Select::make('Flag')->options(self::FLAGS),
                    Select::make('VehicleID')->label('Όχημα')->options(fn (): array => Lookups::vehicles(exclude: ['Sold']))->searchable(),
                    Select::make('DriverID')->label('Οδηγός')->options(fn (): array => Lookups::drivers(activeOnly: true))->searchable(),
                ]),
                Tab::make('Αρχεία')->schema(self::legacyFilesComponents()),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginated([25, 50, 100, 'all'])
            ->defaultPaginationPageOption(50)
            ->defaultSort('ExpenseDate', 'desc')
            ->columns([
                TextColumn::make('ExpenseDate')->label('Ημερομηνία')->date('d/m/Y')->sortable(),
                TextColumn::make('ExpenseReason')->label('Αιτιολογία')->wrap()->limit(80)->searchable()
                    ->summarize(Count::make()->label('')),
                TextColumn::make('ExpenseAmountRcvd')->label('Είσπραξη')->money('EUR', locale: 'el')->alignEnd()->sortable()
                    ->color(fn ($state): ?string => (float) $state > 0 ? 'success' : null)
                    ->summarize(Sum::make()->money('EUR', locale: 'el')->label('')),
                TextColumn::make('ExpenseAmountPaid')->label('Πληρωμή')->money('EUR', locale: 'el')->alignEnd()->sortable()
                    ->color(fn ($state): ?string => (float) $state > 0 ? 'danger' : null)
                    ->summarize(Sum::make()->money('EUR', locale: 'el')->label('')),
                TextColumn::make('VehicleID')->label('Όχημα')->formatStateUsing(fn ($state): ?string => Lookups::vehicles()[$state] ?? null)->placeholder('—'),
                TextColumn::make('DriverID')->label('Οδηγός')->formatStateUsing(fn ($state): ?string => Lookups::drivers()[$state] ?? null)->placeholder('—'),
                TextColumn::make('Files')->formatStateUsing(fn (?string $state) => LegacyFiles::links($state))->html()->placeholder('—'),
                TextColumn::make('Company')->label('Εταιρεία')->badge()->color(fn (?string $state): string => $state === 'ΙΚΕ' ? 'info' : 'gray')->sortable(),
                TextColumn::make('Flag')->placeholder('—')->sortable(),
                // PHPRunner paints the category with optionvalues.Color (BeforeMoveNextList).
                TextColumn::make('ExpenseCategoryID')->label('Κατηγορία')->sortable()
                    ->formatStateUsing(function ($state): HtmlString|string|null {
                        $label = Lookups::optionIds('ExpenseCategory')[$state] ?? null;
                        $color = Lookups::colorById('ExpenseCategory', $state);

                        return $label !== null && $color !== null
                            ? new HtmlString('<span style="display:inline-block;width:.6rem;height:.6rem;border-radius:999px;margin-right:.4rem;background:'.e($color).'"></span>'.e($label))
                            : $label;
                    })->placeholder('—'),
                TextColumn::make('ExpenseID')->label('ID')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('CreatedBy')->label('Καταχώρηση')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('ExpenseDate')->columns(2)->schema([
                    DatePicker::make('from')->label('Από'),
                    DatePicker::make('until')->label('Έως'),
                ])->query(fn (Builder $query, array $data): Builder => $query
                    ->when($data['from'] ?? null, fn (Builder $query, $d) => $query->whereDate('ExpenseDate', '>=', $d))
                    ->when($data['until'] ?? null, fn (Builder $query, $d) => $query->whereDate('ExpenseDate', '<=', $d))),
                SelectFilter::make('Company')->label('Εταιρεία')->options(self::COMPANIES),
                SelectFilter::make('ExpenseCategoryID')->label('Κατηγορία')->options(fn (): array => Lookups::optionIds('ExpenseCategory'))->multiple(),
                SelectFilter::make('Flag')->options(self::FLAGS),
                SelectFilter::make('VehicleID')->label('Όχημα')->options(fn (): array => Lookups::vehicles())->multiple(),
                SelectFilter::make('DriverID')->label('Οδηγός')->options(fn (): array => Lookups::drivers())->multiple(),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([
                    // PHPRunner "Update selected".
                    BulkAction::make('updateSelected')
                        ->label('Update selected')
                        ->icon(Heroicon::OutlinedPencilSquare)
                        ->visible(fn (): bool => (bool) auth()->user()?->may('expenses', 'edit'))
                        ->modalDescription('Only the fields you fill in are changed.')
                        ->schema([
                            Select::make('Company')->label('Εταιρεία')->options(self::COMPANIES),
                            Select::make('ExpenseCategoryID')->label('Κατηγορία')->options(fn (): array => Lookups::optionIds('ExpenseCategory'))->searchable(),
                            Select::make('Flag')->options(self::FLAGS),
                            DatePicker::make('ExpenseDate')->label('Ημερομηνία'),
                            Select::make('VehicleID')->label('Όχημα')->options(fn (): array => Lookups::vehicles(exclude: ['Sold']))->searchable(),
                            Select::make('DriverID')->label('Οδηγός')->options(fn (): array => Lookups::drivers(activeOnly: true))->searchable(),
                        ])
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records, array $data): void {
                            $changes = array_filter($data, fn ($value): bool => filled($value));
                            if ($changes === []) {
                                Notification::make()->warning()->title('Nothing to change')->send();

                                return;
                            }
                            $records->each(fn (Expense $expense) => $expense->update($changes));
                            Notification::make()->success()->title($records->count().' updated')->send();
                        }),
                    DeleteBulkAction::make()->visible(fn (): bool => (bool) auth()->user()?->may('expenses', 'delete')),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExpenses::route('/'),
            'create' => CreateExpense::route('/create'),
            'edit' => EditExpense::route('/{record}/edit'),
        ];
    }
}
