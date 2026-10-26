<?php

namespace App\Filament\Pages;

use App\Services\Elorus\ElorusClient;
use App\Services\Elorus\ElorusException;
use App\Support\Permissions;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use UnitEnum;

/** PHPRunner "Elorus Contacts New": the contacts in Elorus, read live from its API. */
class ElorusContacts extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.reports.list-report';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 93;

    protected static ?string $navigationLabel = 'Elorus Contacts';

    protected static ?string $title = 'Elorus Contacts';

    protected static ?string $slug = 'elorus-contacts';

    protected Width|string|null $maxContentWidth = Width::Full;

    public ?string $problem = null;

    public static function canAccess(): bool
    {
        return config('elorus.buttons') && Permissions::allows(auth()->user(), 'Elorus_Contacts_New', 'view');
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(function (int $page, int $recordsPerPage, ?string $search): LengthAwarePaginator {
                try {
                    $result = app(ElorusClient::class)->contacts($page, $recordsPerPage, $search);
                    $this->problem = null;
                } catch (ElorusException $e) {
                    $this->problem = $e->getMessage();
                    $result = ['count' => 0, 'results' => []];
                }

                $rows = collect($result['results'])->mapWithKeys(fn (array $c): array => [(string) ($c['id'] ?? '') => [
                    'id' => $c['id'] ?? null,
                    'display_name' => $c['display_name'] ?? null,
                    'company' => $c['company'] ?? null,
                    'vat_number' => $c['vat_number'] ?? null,
                    'address' => $c['addresses'][0]['address'] ?? null,
                    'city' => $c['addresses'][0]['city'] ?? null,
                    'zip' => $c['addresses'][0]['zip'] ?? null,
                    'country' => $c['addresses'][0]['country'] ?? null,
                ]]);

                return new LengthAwarePaginator($rows, $result['count'], $recordsPerPage, $page);
            })
            ->searchable()
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->emptyStateHeading(fn (): string => $this->problem ?? 'No contacts')
            ->columns([
                TextColumn::make('display_name')->label('Display name'),
                TextColumn::make('company')->label('Company')->placeholder('—'),
                TextColumn::make('vat_number')->label('VAT number')->placeholder('—'),
                TextColumn::make('address')->label('Address')->placeholder('—'),
                TextColumn::make('city')->label('City')->placeholder('—'),
                TextColumn::make('zip')->label('Zip')->placeholder('—'),
                TextColumn::make('country')->label('Country')->placeholder('—'),
                TextColumn::make('id')->label('Elorus ID')->toggleable(isToggledHiddenByDefault: true),
            ]);
    }
}
