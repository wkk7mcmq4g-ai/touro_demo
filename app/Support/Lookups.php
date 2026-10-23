<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * The PHPRunner lookups of the transfers pages, read once per request.
 * Each method mirrors the lookup's table, link field, display field and WHERE.
 */
class Lookups
{
    /** @var array<string, mixed> */
    private static array $cache = [];

    /** @return array<string, string> Values from `optionvalues` for one Option. */
    public static function optionValues(string $option): array
    {
        return self::remember("option:{$option}", fn () => DB::table('optionvalues')
            ->where('Option', $option)
            ->orderBy('Value')
            ->pluck('Value', 'Value')
            ->all());
    }

    /** @return array<int, string> OptionID => Value, e.g. file types (`TransferFile`). */
    public static function optionIds(string $option): array
    {
        return self::remember("optionIds:{$option}", fn () => DB::table('optionvalues')
            ->where('Option', $option)
            ->orderBy('Value')
            ->pluck('Value', 'OptionID')
            ->all());
    }

    /**
     * The CSS colour PHPRunner paints a value with (`optionvalues.Color`), or null.
     */
    public static function color(string $option, ?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $colors = self::remember("colors:{$option}", fn () => DB::table('optionvalues')
            ->where('Option', $option)
            ->whereNotNull('Color')
            ->where('Color', '<>', '')
            ->pluck('Color', 'Value')
            ->all());

        $color = $colors[$value] ?? null;

        // Only plain colour names, hex and rgb()/hsl() values go into a style attribute.
        return ($color !== null && preg_match('/^[#a-zA-Z0-9(),.% ]{1,40}$/', $color)) ? trim($color) : null;
    }

    /** The colour of an optionvalues row picked by its OptionID (e.g. ExpenseCategoryID), or null. */
    public static function colorById(string $option, $id): ?string
    {
        $value = self::optionIds($option)[$id] ?? null;

        return self::color($option, $value);
    }

    /**
     * @return array<string, string> Statuses the user's group may set
     *                               (PHPRunner: `Status_Group = ':session.GroupID'`).
     */
    public static function statusesFor(?string $group): array
    {
        $statuses = self::remember("status:{$group}", fn () => DB::table('status_values')
            ->where('Status_Group', $group)
            ->pluck('StatusValue', 'StatusValue')
            ->all());

        return $statuses ?: ['Pending' => 'Pending', 'Completed' => 'Completed', 'Cancelled' => 'Cancelled', 'Cash' => 'Cash'];
    }

    /** @return array<int, string> Customers: counterparties.CID => SupplierName where IsSupplier = 1. */
    public static function customers(): array
    {
        return self::remember('customers', fn () => DB::table('counterparties')
            ->where('IsSupplier', 1)
            ->orderBy('SupplierName')
            ->pluck('SupplierName', 'CID')
            ->all());
    }

    /** @return array<string, string> Assistants by name (counterparties.AssistantName where IsAssistant = 1). */
    public static function assistantNames(): array
    {
        return self::remember('assistantNames', fn () => DB::table('counterparties')
            ->where('IsAssistant', 1)
            ->whereNotNull('AssistantName')
            ->orderBy('AssistantName')
            ->pluck('AssistantName', 'AssistantName')
            ->all());
    }

    /** @return array<int, string> Assistant companies: CID => CounterShortName where IsAssistant = 1. */
    public static function assistantCompanies(): array
    {
        return self::remember('assistantCompanies', fn () => DB::table('counterparties')
            ->where('IsAssistant', 1)
            ->orderBy('CounterShortName')
            ->pluck('CounterShortName', 'CID')
            ->all());
    }

    /** @return array<string, string> Pick-up places: tours that are not tours (PHPRunner `From`). */
    public static function pickupPlaces(): array
    {
        return self::remember('pickup', fn () => DB::table('tours')
            ->whereRaw("ifnull(DestinationType, '') not like 'Tour%'")
            ->orderBy('TourName')
            ->pluck('TourName', 'TourName')
            ->all());
    }

    /** @return array<string, string> All destinations and tours (PHPRunner `To`). */
    public static function destinations(): array
    {
        return self::remember('destinations', fn () => DB::table('tours')
            ->orderBy('TourName')
            ->pluck('TourName', 'TourName')
            ->all());
    }

    /** @return array<string, string> Vehicles and coach companies (`v_coach`). */
    public static function coaches(): array
    {
        return self::remember('coaches', fn () => DB::table('v_coach')
            ->whereNotNull('CounterShortName')
            ->orderBy('CounterShortName')
            ->pluck('CounterShortName', 'CounterShortName')
            ->all());
    }

    /**
     * @return array<string, string> Distinct values of a column (for filters); empty shown as "(empty)".
     */
    public static function distinctValues(string $table, string $column): array
    {
        return self::remember("distinct:{$table}.{$column}", fn () => DB::table($table)
            ->select($column)
            ->distinct()
            ->whereNotNull($column)
            ->orderBy($column)
            ->limit(500)
            ->pluck($column)
            ->mapWithKeys(fn ($value): array => [(string) $value => $value === '' ? '(empty)' : (string) $value])
            ->all());
    }

    /** @return array<int, string> Vehicles by ID; $licences limits to those licences, $exclude leaves them out. */
    public static function vehicles(array $licences = [], array $exclude = []): array
    {
        return self::remember('vehicles:'.implode(',', $licences).'|'.implode(',', $exclude), fn () => DB::table('vehicles')
            ->when($licences !== [], fn ($q) => $q->whereIn('VehicleLicence', $licences))
            ->when($exclude !== [], fn ($q) => $q->where(fn ($q) => $q->whereNotIn('VehicleLicence', $exclude)->orWhereNull('VehicleLicence')))
            ->orderBy('VehicleName')
            ->pluck('VehicleName', 'VehicleID')
            ->all());
    }

    /** @return array<int, string> Drivers by ID (all, or only active ones). */
    public static function drivers(bool $activeOnly = false): array
    {
        return self::remember('drivers:'.(int) $activeOnly, fn () => DB::table('drivers')
            ->when($activeOnly, fn ($q) => $q->where('Active', 1))
            ->orderBy('Fullname')
            ->pluck('Fullname', 'DriverID')
            ->all());
    }

    public static function flush(): void
    {
        self::$cache = [];
    }

    private static function remember(string $key, callable $callback): mixed
    {
        return self::$cache[$key] ??= $callback();
    }
}
