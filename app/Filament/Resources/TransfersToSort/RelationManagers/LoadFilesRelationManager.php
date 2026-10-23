<?php

namespace App\Filament\Resources\TransfersToSort\RelationManagers;

use App\Models\MyatTransfersLoadFile;
use App\Support\Permissions;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/** PHPRunner detail `myat_transfers_load_files`: files of a booking to sort (blobs). */
class LoadFilesRelationManager extends RelationManager
{
    protected static string $relationship = 'files';

    protected static ?string $title = 'Αρχεία';

    private const TABLE = 'myat_transfers_load_files';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Permissions::allows(auth()->user(), self::TABLE, 'view');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([FileUpload::make('upload')->label('File')->storeFiles(false)->maxSize(10 * 1024)->required()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withoutBlob())
            ->modelLabel('file')
            ->emptyStateHeading('No files yet')
            ->columns([
                TextColumn::make('Flnm')->label('File')->icon(Heroicon::OutlinedPaperClip)
                    ->url(fn (MyatTransfersLoadFile $r): string => route('load-files.download', $r->ID))->openUrlInNewTab(),
                TextColumn::make('size_bytes')->label('Size')->formatStateUsing(fn ($state): string => Number::fileSize((int) $state)),
            ])
            ->headerActions([
                CreateAction::make()->label('Upload file')
                    ->visible(fn (): bool => Permissions::allows(auth()->user(), self::TABLE, 'create'))
                    ->using(function (array $data): Model {
                        /** @var TemporaryUploadedFile $upload */
                        $upload = $data['upload'];

                        return $this->getOwnerRecord()->files()->create(['Flnm' => $upload->getClientOriginalName(), 'Attachment' => $upload->get()]);
                    }),
            ])
            ->recordActions([DeleteAction::make()->visible(fn (): bool => Permissions::allows(auth()->user(), self::TABLE, 'delete'))]);
    }
}
