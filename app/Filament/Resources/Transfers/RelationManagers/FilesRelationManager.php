<?php

namespace App\Filament\Resources\Transfers\RelationManagers;

use App\Models\MyatTransfersFile;
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

/**
 * PHPRunner detail table `myat_transfers_files`. Files live in the `Attachment`
 * blob, exactly as PHPRunner stores them, so both apps see the same files.
 */
class FilesRelationManager extends RelationManager
{
    protected static string $relationship = 'files';

    protected static ?string $title = 'Files';

    /** PHPRunner table whose permissions apply. */
    protected const TABLE = 'myat_transfers_files';

    /** Only files of this optionvalues type (null: all), set on upload. */
    protected const FILE_TYPE = null;

    /** mediumblob holds 16 MB; keep well under the server's packet limit. */
    private const MAX_KILOBYTES = 10 * 1024;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Permissions::allows(auth()->user(), static::TABLE, 'view');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('upload')
                    ->label('File')
                    ->storeFiles(false)
                    ->maxSize(self::MAX_KILOBYTES)
                    ->required(),
                Select::make('Type')
                    ->options(fn (): array => Lookups::optionIds('TransferFile'))
                    ->visible(static::FILE_TYPE === null),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withoutBlob()
                ->when(static::FILE_TYPE !== null, fn (Builder $query) => $query->where('Type', static::FILE_TYPE)))
            ->recordTitleAttribute('Flnm')
            ->defaultSort('ID', 'desc')
            ->columns([
                TextColumn::make('Flnm')
                    ->label('File')
                    ->icon(Heroicon::OutlinedPaperClip)
                    ->url(fn (MyatTransfersFile $record): string => route('transfer-files.download', $record->ID))
                    ->openUrlInNewTab(),
                TextColumn::make('Type')
                    ->formatStateUsing(fn ($state): ?string => Lookups::optionIds('TransferFile')[$state] ?? null)
                    ->placeholder('—'),
                TextColumn::make('size_bytes')
                    ->label('Size')
                    ->formatStateUsing(fn ($state): string => Number::fileSize((int) $state)),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Upload file')
                    ->modalHeading('Upload a file')
                    ->visible(fn (): bool => Permissions::allows(auth()->user(), static::TABLE, 'create'))
                    ->using(function (array $data): Model {
                        /** @var TemporaryUploadedFile $upload */
                        $upload = $data['upload'];

                        return $this->getOwnerRecord()->files()->create([
                            'Flnm' => $upload->getClientOriginalName(),
                            'Attachment' => $upload->get(),
                            'Type' => static::FILE_TYPE ?? $data['Type'] ?? null,
                        ]);
                    }),
            ])
            ->recordActions([
                Action::make('download')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->url(fn (MyatTransfersFile $record): string => route('transfer-files.download', $record->ID))
                    ->openUrlInNewTab(),
                DeleteAction::make()
                    ->visible(fn (): bool => Permissions::allows(auth()->user(), static::TABLE, 'delete')),
            ]);
    }
}
