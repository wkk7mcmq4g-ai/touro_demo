<?php

namespace App\Filament\Concerns;

use App\Filament\Resources\Transfers\Tables\TransfersTable;

/**
 * Keeps the open-items badges (TransfersTable::FLAGS) as the first column, right after the row
 * buttons, whatever order a user saved: Filament adds a column that is new to a saved layout at
 * the end. Runs after Filament has loaded the column state (trait booted hook).
 */
trait PinsFlagsColumn
{
    public function bootedPinsFlagsColumn(): void
    {
        $columns = $this->tableColumns;
        $index = collect($columns)->search(fn (array $item): bool => ($item['name'] ?? null) === TransfersTable::FLAGS);
        if ($index === false || $index === 0) {
            return;
        }

        $flags = $columns[$index];
        unset($columns[$index]);
        // through the column manager, so the table's own column order follows (not only the state)
        $this->applyTableColumnManager([$flags, ...array_values($columns)], wasReordered: true);
    }
}
