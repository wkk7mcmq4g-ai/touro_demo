<?php

namespace App\Filament\Widgets\Dashboard;

use App\Filament\Resources\DailyTransfers\DailyTransfersResource;
use App\Filament\Resources\Transfers\TransferResource;
use App\Filament\Resources\TransfersToSort\TransferToSortResource;
use App\Models\DailyRecipient;
use App\Support\DashboardData;
use Filament\Widgets\Widget;
use Throwable;

/** What someone has to act on: jobs without a vehicle, open issues, bookings to sort, unread sends. */
class NeedsAttention extends Widget
{
    protected static ?int $sort = 3;

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 4];

    protected string $view = 'filament.dashboard.needs-attention';

    protected function getViewData(): array
    {
        $items = [];

        if (TransferResource::canAccess()) {
            $items[] = ['label' => 'Χωρίς όχημα (σήμερα & αύριο)', 'hint' => 'Δεν έχει δοθεί όχημα', 'count' => DashboardData::unassignedNextHours(),
                'tone' => 'amber', 'icon' => 'heroicon-o-truck', 'url' => TransferResource::getUrl('index', ['tab' => 'today'])];
            $items[] = ['label' => 'Θέματα (7 ημέρες)', 'hint' => 'TBD, κενή διαδρομή ή πτήση', 'count' => DashboardData::issuesNextDays(),
                'tone' => 'rose', 'icon' => 'heroicon-o-exclamation-triangle', 'url' => TransferResource::getUrl('index', ['tab' => 'issues'])];
        }
        if (TransferToSortResource::canAccess()) {
            $items[] = ['label' => 'Προς διαλογή', 'hint' => 'Κρατήσεις συνεργατών που περιμένουν', 'count' => DashboardData::toSort(),
                'tone' => 'azure', 'icon' => 'heroicon-o-inbox-arrow-down', 'url' => TransferToSortResource::getUrl()];
        }
        if (DailyTransfersResource::canAccess()) {
            try {
                $unread = DailyRecipient::source()->where('SendStatus', '<>', 'Read')->count();
            } catch (Throwable $e) {
                report($e);
                $unread = null;
            }
            if ($unread !== null) {
                $items[] = ['label' => 'Οδηγοί χωρίς επιβεβαίωση', 'hint' => 'Το πρόγραμμα της ημέρας δεν έχει διαβαστεί', 'count' => $unread,
                    'tone' => 'violet', 'icon' => 'heroicon-o-paper-airplane', 'url' => DailyTransfersResource::getUrl()];
            }
        }

        return ['items' => $items];
    }
}
