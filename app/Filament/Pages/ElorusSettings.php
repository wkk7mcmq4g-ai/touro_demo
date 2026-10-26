<?php

namespace App\Filament\Pages;

use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * PHPRunner "Elorus Settings" (elorus_Settings, a single row): the organisation, API key and
 * default IDs both apps use. The key is never shown; leave it empty to keep it.
 */
class ElorusSettings extends Page
{
    protected string $view = 'filament.pages.elorus-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 92;

    protected static ?string $navigationLabel = 'Elorus Settings';

    protected static ?string $title = 'Elorus Settings';

    protected static ?string $slug = 'elorus-settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return config('elorus.ui') && Permissions::allows(auth()->user(), 'elorus_Settings', 'view');
    }

    public function mount(): void
    {
        $row = DB::table('elorus_Settings')->first();
        $this->form->fill([
            'OrgID' => $row?->OrgID,
            'PrivateIndividualID' => $row?->PrivateIndividualID,
            'DefaultProductID' => $row?->DefaultProductID,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $hasKey = filled(DB::table('elorus_Settings')->value('apikey'));
        $readOnly = ! Permissions::allows(auth()->user(), 'elorus_Settings', 'edit');

        return $schema->statePath('data')->disabled($readOnly)->components([
            Section::make()->columns(2)->schema([
                TextInput::make('OrgID')->label('Organization ID')->maxLength(50)->required(),
                TextInput::make('apikey')->label('API key')->password()->maxLength(50)
                    ->placeholder($hasKey ? '•••••••• (saved, leave empty to keep)' : 'Not set')
                    ->helperText(config('elorus.token') ? 'ELORUS_API_TOKEN in .env is used instead of this key on this server.' : null),
                TextInput::make('PrivateIndividualID')->label('Private Individual ID')->maxLength(50),
                TextInput::make('DefaultProductID')->label('Default Product ID')->maxLength(50),
            ]),
        ]);
    }

    public function save(): void
    {
        abort_unless(Permissions::allows(auth()->user(), 'elorus_Settings', 'edit'), 403);

        $data = $this->form->getState();
        $values = [
            'OrgID' => $data['OrgID'] ?? null,
            'PrivateIndividualID' => $data['PrivateIndividualID'] ?? null,
            'DefaultProductID' => $data['DefaultProductID'] ?? null,
        ] + (filled($data['apikey'] ?? null) ? ['apikey' => $data['apikey']] : []);

        DB::table('elorus_Settings')->exists()
            ? DB::table('elorus_Settings')->update($values)
            : DB::table('elorus_Settings')->insert($values);

        $this->form->fill(['apikey' => null] + $values);
        Notification::make()->success()->title('Saved')->send();
    }

    protected function getFormActions(): array
    {
        return [Action::make('save')->label('Save')->submit('save')
            ->visible(fn (): bool => Permissions::allows(auth()->user(), 'elorus_Settings', 'edit'))];
    }
}
