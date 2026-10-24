<?php

namespace App\Filament\Portal\Pages;

use App\Filament\Reports\ListReport;
use App\Support\Lookups;
use App\Support\Permissions;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Count;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * PHPRunner "Οι αναθέσεις μου" (v_transfers2, owner = the coach company's user): the jobs
 * MyAT gave this partner or coach company, from 2026, cancelled ones left out by the view.
 */
class MyAssignments extends ListReport
{
    protected static string $permissionTable = 'v_transfers2';

    protected static string|UnitEnum|null $navigationGroup = 'Δρομολόγια';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Οι αναθέσεις μου';

    protected static ?string $title = 'Οι αναθέσεις μου';

    protected static ?string $slug = 'assignments';

    protected function source(): Builder
    {
        return DB::table('v_transfers2')->where('UserID', auth()->id() ?? -1)
            ->select(['TransferID', 'Status', 'CarType', 'Pax', 'TransferDate', 'TransferType', 'TransferTime', 'From', 'To', 'Flight_Ship_Num_From',
                'Assistant', 'PassengerName', 'LeadName', 'LeadPhone', 'Notes', 'PurchasePrice', 'InvoicedRcvd', 'ReferencePeriod']);
    }

    protected function defaultSort(): ?array
    {
        return ['TransferDate', 'asc'];
    }

    protected function columns(): array
    {
        return [
            TextColumn::make('TransferID')->label('ID')->searchable()->summarize(Count::make()->label('')),
            TextColumn::make('Status')->badge()->color('gray'),
            TextColumn::make('TransferDate')->label('Date')->date('D d/m/Y')->sortable(),
            TextColumn::make('TransferTime')->label('Time'),
            TextColumn::make('TransferType')->label('Type'),
            TextColumn::make('CarType')->label('Car type'),
            TextColumn::make('Pax'),
            TextColumn::make('From')->wrap()->searchable(),
            TextColumn::make('To')->wrap()->searchable(),
            TextColumn::make('Flight_Ship_Num_From')->label('Flight / ship')->wrap(),
            TextColumn::make('LeadName')->label('Lead name')->searchable(),
            TextColumn::make('LeadPhone')->label('Lead phone'),
            TextColumn::make('Assistant')->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('PassengerName')->label('Passengers')->wrap()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('Notes')->wrap()->limit(100),
            TextColumn::make('PurchasePrice')->label('Price')->money('EUR', locale: 'el')->alignEnd()
                ->summarize(Sum::make()->money('EUR', locale: 'el')->label('')),
            TextColumn::make('InvoicedRcvd')->label('Invoice')->placeholder('—')->toggleable(),
            // PHPRunner's detail list myat_coach_files: the transfer's files of the types marked
            // "Partners see it" (Field Settings), for the groups allowed to see them (Partner; not Coach).
            TextColumn::make('files')->label('Αρχεία')
                ->visible(fn (): bool => Permissions::allows(auth()->user(), 'myat_coach_files', 'view'))
                ->state(fn ($record): ?HtmlString => $this->filesLinks((int) $record->TransferID))
                ->placeholder('—')
                ->wrap(),
        ];
    }

    /** @var array<int, list<object>>|null */
    private ?array $sharedFiles = null;

    /** Links to the transfer's shared files; all rows of the page are read in one query. */
    private function filesLinks(int $transferId): ?HtmlString
    {
        $this->sharedFiles ??= DB::table('myat_transfers_files as m')
            ->join('optionvalues as o', 'o.OptionID', '=', 'm.Type')
            ->where('o.PartnerView', 1)
            ->whereIn('m.TransferID', DB::table('v_transfers2')->where('UserID', auth()->id() ?? -1)->select('TransferID'))
            ->orderBy('m.ID')
            ->get(['m.ID', 'm.TransferID', 'm.Flnm', 'o.Value as TypeName'])
            ->groupBy('TransferID')->map(fn ($files) => $files->all())->all();

        $files = $this->sharedFiles[$transferId] ?? [];
        if ($files === []) {
            return null;
        }

        return new HtmlString(collect($files)->map(fn (object $f): string => '<a class="myat-file-link" target="_blank" rel="noopener" href="'
            .e(route('assignment-files.download', $f->ID)).'" title="'.e((string) $f->TypeName).'">'.e($f->Flnm ?: 'Αρχείο '.$f->ID).'</a>')->implode('<br>'));
    }

    protected function filters(): array
    {
        return [
            SelectFilter::make('ReferencePeriod')->label('Month')
                ->options(fn (): array => collect(range(-2, 12))->map(fn (int $i) => now()->startOfMonth()->subMonths($i))
                    ->mapWithKeys(fn ($m): array => [$m->copy()->endOfMonth()->toDateString() => $m->format('m/Y')])->all()),
            SelectFilter::make('TransferType')->label('Transfer type')->options(fn (): array => Lookups::optionValues('TransferType')),
            SelectFilter::make('CarType')->label('Car type')->options(fn (): array => Lookups::optionValues('CarType')),
        ];
    }
}
