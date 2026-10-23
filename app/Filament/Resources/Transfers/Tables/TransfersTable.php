<?php

namespace App\Filament\Resources\Transfers\Tables;

use App\Models\Transfer;
use App\Support\ColorDot;
use App\Support\Lookups;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TransfersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Upcoming tabs read like a run sheet (earliest first); "All" shows the newest first.
            ->defaultSort(function (Builder $query, $livewire): Builder {
                $direction = ($livewire->activeTab ?? null) === 'all' ? 'desc' : 'asc';

                return $query->orderBy('TransferDate', $direction)->orderBy('TransferTime_sort', $direction);
            })
            ->striped()
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->columns([
                TextColumn::make('TransferID')
                    ->label('#')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->icon(fn (Transfer $record) => $record->placeholderFields() ? Heroicon::ExclamationTriangle : null)
                    ->iconColor('warning')
                    ->tooltip(fn (Transfer $record): ?string => ($fields = $record->placeholderFields())
                        ? 'Still to be confirmed: '.implode(', ', $fields)
                        : null),
                TextColumn::make('TransferDate')
                    ->label('Date')
                    ->date('D d/m/Y')
                    ->sortable()
                    ->description(fn (Transfer $record): ?string => $record->TransferTime ?: null),
                TextColumn::make('TransferType')
                    ->label('Type')
                    ->formatStateUsing(fn (?string $state) => ColorDot::render($state, Lookups::color('TransferType', $state)))
                    ->sortable(),
                TextColumn::make('From')
                    ->label('Route')
                    ->formatStateUsing(fn (Transfer $record): string => trim(($record->From ?: '?').' → '.($record->To ?: '?')))
                    ->description(fn (Transfer $record): ?string => $record->Flight_Ship_Num_From ?: null)
                    ->searchable(['From', 'To', 'Flight_Ship_Num_From'])
                    ->limit(48)
                    ->tooltip(fn (Transfer $record): string => trim(($record->From ?: '?').' → '.($record->To ?: '?'))),
                TextColumn::make('Pax')
                    ->alignEnd(),
                TextColumn::make('customer.SupplierName')
                    ->label('Customer')
                    ->description(fn (Transfer $record): ?string => $record->SupplierCode ?: null)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->orWhere('SupplierCode', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn (Builder $customer) => $customer->where('SupplierName', 'like', "%{$search}%"))),
                TextColumn::make('CoachCompanyID')
                    ->label('Vehicle')
                    ->placeholder('Not assigned')
                    ->description(fn (Transfer $record) => ColorDot::render($record->CarTypeID, Lookups::color('CarType', $record->CarTypeID)))
                    ->sortable(),
                TextColumn::make('LeadName')
                    ->label('Lead passenger')
                    ->description(fn (Transfer $record): ?string => $record->LeadPhone ?: null)
                    ->searchable(['LeadName', 'LeadPhone', 'PassengerName'])
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('Status')
                    ->formatStateUsing(fn (?string $state) => ColorDot::render($state, Lookups::color('Status', $state)))
                    ->sortable(),
                TextColumn::make('SalePrice')
                    ->label('Sale')
                    ->money('EUR', locale: 'el')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('TobeInvoiced')
                    ->label('Invoice')
                    ->badge()
                    ->getStateUsing(fn (Transfer $record): string => $record->TobeInvoiced ?: 'Missing')
                    ->color(fn (string $state): string => $state === 'Missing' ? 'danger' : 'gray')
                    ->toggleable(),
                TextColumn::make('PaymentType')
                    ->label('Payment')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('TicketsRequested')
                    ->label('Tickets')
                    ->formatStateUsing(fn (?string $state) => ColorDot::render($state, Lookups::color('TicketsRequested', $state)))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('AssistantStatus')
                    ->label('Assistant')
                    ->formatStateUsing(fn (?string $state) => ColorDot::render($state, Lookups::color('AssistantStatus', $state)))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('Status')
                    ->multiple()
                    ->options(fn (): array => Lookups::statusesFor('Admin')),
                SelectFilter::make('cID_Customer')
                    ->label('Customer')
                    ->options(fn (): array => Lookups::customers())
                    ->searchable(),
                SelectFilter::make('TransferType')
                    ->label('Type')
                    ->options(fn (): array => Lookups::optionValues('TransferType')),
                SelectFilter::make('CoachCompanyID')
                    ->label('Vehicle')
                    ->options(fn (): array => Lookups::coaches())
                    ->searchable(),
                Filter::make('dates')
                    ->schema([
                        DatePicker::make('from')->label('Date from'),
                        DatePicker::make('until')->label('Date until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, $date) => $query->whereDate('TransferDate', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, $date) => $query->whereDate('TransferDate', '<=', $date))),
                TernaryFilter::make('issues')
                    ->label('Open issues')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->where(fn (Builder $query) => $query
                            ->where('OperationalIssuesFlag', 'Yes')->orWhere('FinancialIssuesFlag', 'Yes')),
                        false: fn (Builder $query): Builder => $query->where(fn (Builder $query) => $query
                            ->where('OperationalIssuesFlag', '<>', 'Yes')->orWhereNull('OperationalIssuesFlag'))
                            ->where(fn (Builder $query) => $query
                                ->where('FinancialIssuesFlag', '<>', 'Yes')->orWhereNull('FinancialIssuesFlag')),
                    ),
                TernaryFilter::make('invoice_missing')
                    ->label('Invoice type missing')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->where(fn (Builder $query) => $query
                            ->where('TobeInvoiced', '')->orWhereNull('TobeInvoiced')),
                        false: fn (Builder $query): Builder => $query->where('TobeInvoiced', '<>', ''),
                    ),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
