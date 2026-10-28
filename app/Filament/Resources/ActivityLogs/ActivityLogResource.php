<?php

namespace App\Filament\Resources\ActivityLogs;

use App\Filament\Concerns\UsesPhpRunnerPermissions;
use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Models\MyatActivityLog;
use App\Support\ActivityLog;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Ιστορικό ενεργειών of the new app (myat_activity_log, written by App\Support\ActivityLog):
 * logins, logouts and every insert / update / delete, with the app version. Read-only.
 * Same rights as PHPRunner's Access Log. Hidden until the table exists.
 */
class ActivityLogResource extends Resource
{
    use UsesPhpRunnerPermissions;

    protected static string $permissionTable = 'access_log';

    protected static ?string $model = MyatActivityLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 92;

    protected static ?string $navigationLabel = 'Ιστορικό ενεργειών';

    protected static ?string $pluralModelLabel = 'Ιστορικό ενεργειών';

    protected static ?string $modelLabel = 'ενέργεια';

    protected static ?string $slug = 'activity-log';

    public const LABELS = [
        'login' => ['Είσοδος', 'success'], 'logout' => ['Έξοδος', 'gray'], 'insert' => ['Νέο', 'info'],
        'update' => ['Αλλαγή', 'warning'], 'delete' => ['Διαγραφή', 'danger'], 'call' => ['Διαδικασία', 'gray'],
    ];

    public static function canAccess(): bool
    {
        return ActivityLog::enabled() && static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /** One line for the list: "Coach: A → B · Pax: 2 → 3", or the first values of a new / deleted row. */
    public static function summary(MyatActivityLog $log): string
    {
        $changes = $log->Changes ?? [];
        if (isset($changes['sql'])) {
            return Str::limit((string) $changes['sql'], 120);
        }
        $show = fn ($v): string => $v === null || $v === '' ? '—' : (is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE));

        return Str::limit(collect($changes)->map(fn ($v, $field): string => $log->Action === 'update' && is_array($v) && count($v) === 2
            ? $field.': '.$show($v[0]).' → '.$show($v[1])
            : $field.': '.$show($v))->implode(' · '), 160);
    }

    public static function table(Table $table): Table
    {
        $distinct = fn (string $column): array => DB::table('myat_activity_log')->where($column, '<>', '')->distinct()->orderBy($column)->limit(500)->pluck($column, $column)->all();

        return $table
            ->defaultSort('ID', 'desc')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->recordUrl(null)
            ->recordAction('details')
            ->columns([
                TextColumn::make('CreatedAt')->label('Πότε')->dateTime('d/m/Y H:i:s')->sortable(query: fn (Builder $q, string $d) => $q->orderBy('ID', $d)),
                TextColumn::make('Username')->label('Χρήστης')->searchable()->placeholder('σύστημα'),
                TextColumn::make('Action')->label('Ενέργεια')->badge()
                    ->formatStateUsing(fn (string $state): string => self::LABELS[$state][0] ?? $state)
                    ->color(fn (string $state): string => self::LABELS[$state][1] ?? 'gray'),
                TextColumn::make('TableName')->label('Πίνακας')->searchable()->placeholder('—'),
                TextColumn::make('RecordID')->label('Εγγραφή')->searchable()->placeholder('—'),
                TextColumn::make('summary')->label('Αλλαγές')->state(fn (MyatActivityLog $record): string => self::summary($record))
                    ->wrap()->extraAttributes(['class' => 'myat-activity-summary']),
                TextColumn::make('AppVersion')->label('Έκδοση')->toggleable(),
                TextColumn::make('Url')->label('Οθόνη')->limit(40)->tooltip(fn (?string $state): ?string => $state)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('IP')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('Username')->label('Χρήστης')->multiple()->searchable()->options(fn (): array => $distinct('Username')),
                SelectFilter::make('Action')->label('Ενέργεια')->multiple()
                    ->options(collect(self::LABELS)->map(fn (array $l): string => $l[0])->all()),
                SelectFilter::make('TableName')->label('Πίνακας')->multiple()->searchable()->options(fn (): array => $distinct('TableName')),
                Filter::make('CreatedAt')->columns(2)->schema([DatePicker::make('from')->label('Από'), DatePicker::make('until')->label('Έως')])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->where('CreatedAt', '>=', $d.' 00:00:00'))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->where('CreatedAt', '<=', $d.' 23:59:59'))),
            ])
            ->recordActions([
                Action::make('details')->label('Λεπτομέρειες')->icon(Heroicon::OutlinedEye)->iconButton()
                    ->modalHeading(fn (MyatActivityLog $record): string => (self::LABELS[$record->Action][0] ?? $record->Action)
                        .($record->TableName !== '' ? ' · '.$record->TableName.($record->RecordID !== '' ? ' #'.$record->RecordID : '') : ''))
                    ->modalWidth(Width::TwoExtraLarge)
                    ->modalContent(fn (MyatActivityLog $record) => view('filament.activity-log.details', ['log' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Κλείσιμο'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListActivityLogs::route('/')];
    }
}
