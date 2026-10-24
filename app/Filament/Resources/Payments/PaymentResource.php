<?php

namespace App\Filament\Resources\Payments;

use App\Filament\Concerns\UsesPhpRunnerPermissions;
use App\Filament\Resources\Payments\Pages\CreatePayment;
use App\Filament\Resources\Payments\Pages\EditPayment;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\RelationManagers\LinksRelationManager;
use App\Models\PaymentReceived;
use App\Support\Lookups;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * PHPRunner "Εισερχόμενα Εμβάσματα" (PaymentsReceived): customer payments, allocated to
 * their completed, unpaid transfers by the PaymentAllocation_Rem procedure.
 */
class PaymentResource extends Resource
{
    use UsesPhpRunnerPermissions;

    protected static string $permissionTable = 'PaymentsReceived';

    protected static ?string $model = PaymentReceived::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Εισερχόμενα Εμβάσματα';

    /** List page title and breadcrumb: the same as the menu item. */
    protected static ?string $pluralModelLabel = 'Εισερχόμενα Εμβάσματα';

    protected static ?string $modelLabel = 'payment';

    protected static ?string $slug = 'payments';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Στοιχεία Πληρωμής')->columns(2)->columnSpanFull()->schema([
                Select::make('CID_Customer')->label('Customer')->options(fn (): array => Lookups::customers())->searchable()->required(),
                DatePicker::make('PaymentDate')->label('Payment date')->default(now())->required(),
                TextInput::make('PaymentAmount')->label('Amount')->numeric()->prefix('€')->required(),
                TextInput::make('PaymentInvoiceNumber')->label('Invoice no.')->maxLength(150),
                TextInput::make('Remaining')->numeric()->prefix('€')->disabled()->dehydrated(false)
                    ->helperText('Worked out by the database: what is not yet allocated to transfers.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('PaymentDate', 'desc')
            ->paginated([25, 50, 100, 'all'])
            ->defaultPaginationPageOption(50)
            ->columns([
                TextColumn::make('PaymentID')->label('ID')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('CID_Customer')->label('Customer')->formatStateUsing(fn ($state): ?string => Lookups::customers()[$state] ?? null)->sortable(),
                TextColumn::make('PaymentDate')->label('Date')->date('d/m/Y')->sortable(),
                TextColumn::make('PaymentAmount')->label('Amount')->money('EUR', locale: 'el')->alignEnd()->sortable()
                    ->summarize(Sum::make()->money('EUR', locale: 'el')->label('')),
                TextColumn::make('PaymentInvoiceNumber')->label('Invoice no.')->searchable(),
                TextColumn::make('Remaining')->money('EUR', locale: 'el')->alignEnd()->sortable()
                    ->color(fn ($state): ?string => (float) $state !== 0.0 ? 'warning' : null)
                    ->summarize(Sum::make()->money('EUR', locale: 'el')->label('')),
            ])
            ->filters([SelectFilter::make('CID_Customer')->label('Customer')->options(fn (): array => Lookups::customers())->searchable()])
            ->recordActions([
                // PHPRunner's row button (New_Button61), shown while something is left to allocate.
                Action::make('allocate')->label('Allocate')->icon(Heroicon::OutlinedArrowsPointingIn)->color('success')
                    ->visible(fn (PaymentReceived $record): bool => (float) $record->Remaining !== 0.0 && (bool) auth()->user()?->may('PaymentsReceived', 'edit'))
                    ->requiresConfirmation()
                    ->modalDescription('The remaining amount is spread over the customer\'s completed transfers that are still unpaid, oldest first.')
                    ->action(function (PaymentReceived $record): void {
                        DB::statement('CALL PaymentAllocation_Rem(?, ?, ?)', [$record->PaymentID, $record->CID_Customer, (float) $record->Remaining]);
                        Notification::make()->success()->title('Payment allocated')->send();
                    }),
                EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [LinksRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
            'create' => CreatePayment::route('/create'),
            'edit' => EditPayment::route('/{record}/edit'),
        ];
    }
}
