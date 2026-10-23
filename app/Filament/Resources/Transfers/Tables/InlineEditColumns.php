<?php

namespace App\Filament\Resources\Transfers\Tables;

use App\Models\Transfer;
use App\Support\Lookups;
use App\Support\TransferRules;
use Closure;
use Filament\Tables\Columns\CheckboxColumn;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextInputColumn;

/**
 * Editable versions of the transfers list columns ("Edit in list"), PHPRunner's inline edit.
 * Every change is saved at once through the Transfer model, so the same stamps, triggers,
 * price procedure and date rules apply as on the edit page.
 */
class InlineEditColumns
{
    private const REQUIRED = ['TransferType', 'TransferDate', 'TransferTime', 'From', 'CarTypeID', 'Status'];

    private const MONEY = ['Assistant_Price', 'PlatformPrice', 'PurchasePrice', 'SalePrice', 'PricePaid', 'PriceRcvd', 'Expense'];

    /**
     * @param  list<Column>  $columns
     * @param  list<string>  $editable
     * @return list<Column>
     */
    public static function apply(array $columns, array $editable): array
    {
        return array_map(
            fn (Column $column): Column => in_array($column->getName(), $editable, true)
                ? self::make($column->getName(), (string) $column->getLabel())
                : $column,
            $columns,
        );
    }

    public static function make(string $name, string $label): Column
    {
        $options = self::options($name);

        $column = match (true) {
            $name === 'OLP' => CheckboxColumn::make($name)
                ->getStateUsing(fn (Transfer $record): bool => (int) $record->OLP === 1)
                ->updateStateUsing(fn (Transfer $record, $state) => self::save($record, $name, $state ? 1 : 0)),
            $options !== null => SelectColumn::make($name)
                // Keep a value PHPRunner stored that is no longer in the list.
                ->options(fn (?Transfer $record): array => $options() + (filled($record?->{$name}) ? [$record->{$name} => $record->{$name}] : []))
                ->selectablePlaceholder(! in_array($name, self::REQUIRED, true)),
            in_array($name, ['TransferDate', 'Voucher_StartDt', 'Voucher_EndDt'], true) => TextInputColumn::make($name)
                ->type('date')
                ->getStateUsing(fn (Transfer $record): ?string => $record->{$name}?->format('Y-m-d')),
            in_array($name, ['Voucher_StartTime', 'Voucher_EndTime'], true) => TextInputColumn::make($name)
                ->type('time')
                ->getStateUsing(fn (Transfer $record): ?string => $record->{$name} ? substr((string) $record->{$name}, 0, 5) : null),
            in_array($name, self::MONEY, true) => TextInputColumn::make($name)
                ->type('number')
                ->step(0.01)
                ->rules(['nullable', 'numeric']),
            default => TextInputColumn::make($name),
        };

        if (in_array($name, self::REQUIRED, true)) {
            $column->rules(fn (?Transfer $record): array => array_filter([
                'required',
                $name === 'TransferDate' ? self::transferDateRule($record) : null,
            ]));
        }

        return $column
            ->label($label)
            ->toggleable()
            ->extraAttributes(['style' => 'min-width: '.self::width($name)]);
    }

    private static function save(Transfer $record, string $name, mixed $value): mixed
    {
        $record->setAttribute($name, $value);
        $record->save();

        return $value;
    }

    private static function transferDateRule(?Transfer $record): Closure
    {
        return function (string $attribute, $value, Closure $fail) use ($record): void {
            if ($error = TransferRules::transferDateError($value, $record, $record?->ImportDate, creating: false)) {
                $fail($error);
            }
        };
    }

    /** @return (Closure(): array<string, string>)|null */
    private static function options(string $name): ?Closure
    {
        return match ($name) {
            'TransferType' => fn (): array => Lookups::optionValues('TransferType'),
            'CarTypeID' => fn (): array => Lookups::optionValues('CarType'),
            'Status' => fn (): array => Lookups::statusesFor(auth()->user()?->groupid),
            'CoachCompanyID' => fn (): array => Lookups::coaches(),
            'From' => fn (): array => Lookups::pickupPlaces(),
            'To' => fn (): array => Lookups::destinations(),
            'Assistant' => fn (): array => Lookups::assistantNames(),
            'TobeInvoiced' => fn (): array => ['Όχι' => 'Όχι', 'Τιμολόγιο' => 'Τιμολόγιο', 'Απόδειξη' => 'Απόδειξη'],
            'Platform' => fn (): array => ['Όχι' => 'Όχι', 'Ναι' => 'Ναι'],
            'PaymentOnHoldFlag' => fn (): array => ['Ναι' => 'Ναι'],
            default => null,
        };
    }

    private static function width(string $name): string
    {
        return match (true) {
            in_array($name, ['Notes', 'DriverNotes', 'PassengerName', 'From', 'To', 'Flight_Ship_Num_From', 'LesseeName', 'LeadName'], true) => '14rem',
            self::options($name) !== null => '11rem',
            in_array($name, self::MONEY, true) || in_array($name, ['Pax', 'TransferTime', 'Voucher_StartTime', 'Voucher_EndTime'], true) => '6.5rem',
            default => '9rem',
        };
    }
}
