<?php

namespace App\Filament\Resources\VehicleAssignments\Pages;

use App\Filament\Resources\VehicleAssignments\VehicleAssignmentResource;
use App\Models\VehicleAssignment;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ListVehicleAssignments extends ListRecords
{
    protected static string $resource = VehicleAssignmentResource::class;

    /** Wide like the other lists: tables use the whole width of the screen. */
    protected Width|string|null $maxContentWidth = Width::Full;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }

    /** Σήμερα / Αύριο / Όλες, like the date buttons on Transfers; opens on today. */
    public function getTabs(): array
    {
        $day = fn (string $label, Carbon $date): Tab => Tab::make($label)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('AssignmentDate', $date->toDateString()))
            ->badge(fn (): int => VehicleAssignment::query()->where('AssignmentDate', $date->toDateString())->count());

        return [
            'today' => $day('Σήμερα', Carbon::today()),
            'tomorrow' => $day('Αύριο', Carbon::tomorrow()),
            'all' => Tab::make('Όλες'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'today';
    }
}
