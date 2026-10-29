<?php

namespace App\Filament\Concerns;

use App\Support\TransferCellEdit;
use Filament\Notifications\Notification;

/**
 * For a Livewire page or widget that shows the quick-edit window (view filament/transfers/cell-editor):
 * the window calls saveTransferCell() on Αποθήκευση. On a refused value nothing is redrawn and the
 * window shows the message; when saved, the component redraws with the new values.
 */
trait EditsTransferCells
{
    public function saveTransferCell(int $id, string $field, ?string $value): ?string
    {
        if (($error = TransferCellEdit::save($id, $field, $value)) !== null) {
            $this->skipRender();

            return $error;
        }

        Notification::make()->success()->title(TransferCellEdit::savedMessage($id, $field, $value))->send();

        return null;
    }
}
