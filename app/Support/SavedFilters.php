<?php

namespace App\Support;

use Filament\Tables\Contracts\HasTable;
use Livewire\Component;

/**
 * "Αποθηκευμένα φίλτρα": what the table is filtered by now (filter panel, search, column searches,
 * tab, sort and the Excel-style column filters) saved under a name per user (TablePresets),
 * and put back in one click.
 */
class SavedFilters
{
    public static function key(Component $livewire): string
    {
        return $livewire->getName();
    }

    public static function current(HasTable&Component $livewire): array
    {
        return [
            'filters' => $livewire->tableFilters ?? [],
            'search' => (string) ($livewire->tableSearch ?? ''),
            'columnSearches' => $livewire->tableColumnSearches ?? [],
            'tab' => property_exists($livewire, 'activeTab') ? $livewire->activeTab : null,
            'sort' => $livewire->tableSort ?? null,
            'excel' => ExcelFilters::active($livewire),
        ];
    }

    public static function apply(HasTable&Component $livewire, array $state): void
    {
        if (property_exists($livewire, 'activeTab') && array_key_exists('tab', $state)) {
            $livewire->activeTab = $state['tab'];
            if (method_exists($livewire, 'updatedActiveTab')) {
                $livewire->updatedActiveTab();
            }
        }

        $livewire->tableSearch = (string) ($state['search'] ?? '');
        $livewire->tableColumnSearches = (array) ($state['columnSearches'] ?? []);
        if (array_key_exists('sort', $state)) {
            $livewire->tableSort = $state['sort'];
        }

        ExcelFilters::clear($livewire);
        foreach ((array) ($state['excel'] ?? []) as $column => $values) {
            try {
                ExcelFilters::set($livewire, (string) $column, (array) $values);
            } catch (\Throwable) {
                // a column that no longer exists is skipped
            }
        }

        // Fill the filter panel and apply it (Filament keeps it in the session where asked).
        // Saved before every select filter became multi-select: {"value": x} is now {"values": [x]}.
        $filters = array_map(fn ($f) => is_array($f) && array_key_exists('value', $f) && ! array_key_exists('values', $f)
            ? ['values' => filled($f['value']) ? [$f['value']] : []] + array_diff_key($f, ['value' => true])
            : $f, (array) ($state['filters'] ?? []));
        $livewire->getTableFiltersForm()->fill($filters);
        if ($livewire->getTable()->hasDeferredFilters()) {
            $livewire->applyTableFilters();
        } elseif (method_exists($livewire, 'handleTableFilterUpdates')) {
            (fn () => $this->handleTableFilterUpdates())->call($livewire);
        }

        if (method_exists($livewire, 'resetPage')) {
            $livewire->resetPage();
        }
    }
}
