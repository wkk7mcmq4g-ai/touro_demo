<?php

namespace App\Filament\Widgets\Dashboard;

use App\Filament\Pages\PartnerJobs;
use App\Filament\Resources\DailyTransfers\DailyTransfersResource;
use App\Filament\Resources\Transfers\TransferResource;
use App\Filament\Resources\TransfersToSort\TransferToSortResource;
use App\Models\DailyRecipient;
use App\Support\DashboardData;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
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
            // No vehicle yet: today on its own line, first and red when something is open (most urgent).
            $today = DashboardData::unassignedOn(Carbon::today());
            $items[] = ['label' => 'Χωρίς όχημα · Σήμερα', 'count' => $today['count'], 'tone' => 'rose', 'icon' => 'heroicon-o-truck',
                'urgent' => $today['count'] > 0,
                'hint' => $today['count'] === 0 ? 'Όλα τα σημερινά έχουν όχημα'
                    : ($today['next'] ? 'Επόμενη παραλαβή χωρίς όχημα '.$today['next'] : 'Δεν έχει δοθεί όχημα'),
                'url' => TransferResource::getUrl('index', ['tab' => 'today'])];
            $tomorrow = DashboardData::unassignedOn(Carbon::tomorrow());
            $items[] = ['label' => 'Χωρίς όχημα · Αύριο', 'count' => $tomorrow['count'], 'tone' => 'amber', 'icon' => 'heroicon-o-truck',
                'hint' => $tomorrow['count'] === 0 ? 'Όλα τα αυριανά έχουν όχημα'
                    : ($tomorrow['next'] ? 'Πρώτη παραλαβή '.$tomorrow['next'] : 'Δεν έχει δοθεί όχημα'),
                'url' => TransferResource::getUrl('index', ['tab' => 'tomorrow'])];
            $items[] = ['label' => 'Θέματα (7 ημέρες)', 'hint' => 'TBD, κενή διαδρομή ή πτήση', 'count' => DashboardData::issuesNextDays(),
                'tone' => 'rose', 'icon' => 'heroicon-o-exclamation-triangle', 'url' => TransferResource::getUrl('index', ['tab' => 'issues'])];
        }
        if (TransferToSortResource::canAccess()) {
            $items[] = ['label' => 'Προς διαλογή', 'hint' => 'Κρατήσεις συνεργατών που περιμένουν', 'count' => DashboardData::toSort(),
                'tone' => 'azure', 'icon' => 'heroicon-o-inbox-arrow-down', 'url' => TransferToSortResource::getUrl()];
        }
        if (PartnerJobs::partners('for-us') !== []) {
            // Jobs partners gave us in their own apps that are not in our transfers yet.
            $new = array_filter(PartnerJobs::newCounts());
            arsort($new);
            $labels = PartnerJobs::partners('for-us');
            $items[] = ['label' => 'Νέες αναθέσεις συνεργατών', 'count' => array_sum($new), 'tone' => 'teal', 'icon' => 'heroicon-o-arrows-right-left',
                'hint' => $new === [] ? 'Όλες έχουν περαστεί στα δρομολόγια'
                    : collect($new)->map(fn (int $n, string $key): string => $labels[$key].' '.$n)->implode(' · '),
                'url' => PartnerJobs::getUrl(['partner' => array_key_first($new) ?? array_key_first($labels), 'direction' => 'for-us'])];
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
