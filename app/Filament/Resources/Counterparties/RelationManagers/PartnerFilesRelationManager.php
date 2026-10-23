<?php

namespace App\Filament\Resources\Counterparties\RelationManagers;

use App\Models\MyatPartnerFile;
use App\Support\Lookups;
use App\Support\Permissions;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/** PHPRunner detail `myat_partner_files` ("Αρχεία" tab): contracts and documents as blobs. */
class PartnerFilesRelationManager extends RelationManager
{
    protected static string $relationship = 'files';

    protected static ?string $title = 'Αρχεία';

    private const TABLE = 'myat_partner_files';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Permissions::allows(auth()->user(), self::TABLE, 'view');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            FileUpload::make('upload')->label('File')->storeFiles(false)->maxSize(10 * 1024)->required(),
            Select::make('Type')->options(fn (): array => Lookups::optionIds('PartnerFile')),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withoutBlob())
            ->recordTitleAttribute('Flnm')
            ->modelLabel('file')
            ->emptyStateHeading('No files yet')
            ->defaultSort('ID', 'desc')
            ->columns([
                TextColumn::make('Flnm')->label('File')->icon(Heroicon::OutlinedPaperClip)
                    ->url(fn (MyatPartnerFile $record): string => route('partner-files.download', $record->ID))
                    ->openUrlInNewTab(),
                TextColumn::make('Type')
                    ->formatStateUsing(fn ($state): ?string => Lookups::optionIds('PartnerFile')[$state] ?? null)
                    ->placeholder('—'),
                TextColumn::make('size_bytes')->label('Size')->formatStateUsing(fn ($state): string => Number::fileSize((int) $state)),
            ])
            ->headerActions([
                CreateAction::make()->label('Upload file')->modalHeading('Upload a file')
                    ->visible(fn (): bool => Permissions::allows(auth()->user(), self::TABLE, 'create'))
                    ->using(function (array $data): Model {
                        /** @var TemporaryUploadedFile $upload */
                        $upload = $data['upload'];

                        return $this->getOwnerRecord()->files()->create([
                            'Flnm' => $upload->getClientOriginalName(),
                            'Attachment' => $upload->get(),
                            'Type' => $data['Type'] ?? null,
                        ]);
                    }),
            ])
            ->recordActions([
                Action::make('download')->icon(Heroicon::OutlinedArrowDownTray)
                    ->url(fn (MyatPartnerFile $record): string => route('partner-files.download', $record->ID))
                    ->openUrlInNewTab(),
                DeleteAction::make()->visible(fn (): bool => Permissions::allows(auth()->user(), self::TABLE, 'delete')),
            ]);
    }
}
