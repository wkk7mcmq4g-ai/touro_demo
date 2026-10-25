<?php

namespace App\Filament\Widgets\Dashboard;

use App\Support\DashboardData;
use Filament\Widgets\ChartWidget;

/** Jobs per day, the last week and the next, today highlighted; passengers as a line. */
class TransfersChart extends ChartWidget
{
    protected static ?int $sort = 4;

    protected ?string $heading = 'Transfers ανά ημέρα';

    protected ?string $description = 'Την προηγούμενη και την επόμενη εβδομάδα';

    protected ?string $maxHeight = '260px';

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 8];

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $days = DashboardData::volume();
        $today = now()->toDateString();
        $primary = (string) config('business.colors.primary');
        [$r, $g, $b] = array_map('hexdec', str_split(ltrim($primary, '#'), 2));
        $bar = fn (array $d): string => $d['date']->toDateString() === $today ? $primary : ($d['date']->isPast() ? 'rgba(148,163,184,0.45)' : "rgba({$r},{$g},{$b},0.35)");

        return [
            'datasets' => [
                [
                    'type' => 'bar',
                    'label' => 'Transfers',
                    'data' => array_column($days, 'jobs'),
                    'backgroundColor' => array_map($bar, $days),
                    'borderRadius' => 6,
                    'maxBarThickness' => 34,
                    'order' => 2,
                ],
                [
                    'type' => 'line',
                    'label' => 'Επιβάτες',
                    'data' => array_column($days, 'pax'),
                    'borderColor' => config('business.colors.navy'),
                    'backgroundColor' => config('business.colors.navy'),
                    'borderDash' => [4, 4],
                    'pointRadius' => 2,
                    'tension' => 0.35,
                    'yAxisID' => 'pax',
                    'order' => 1,
                ],
            ],
            'labels' => array_map(fn (array $d): string => $d['date']->locale('el')->translatedFormat('D j/n'), $days),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => true, 'position' => 'bottom', 'labels' => ['usePointStyle' => true, 'boxWidth' => 8]]],
            'scales' => [
                'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0], 'grid' => ['color' => 'rgba(148,163,184,0.15)'], 'title' => ['display' => true, 'text' => 'Transfers']],
                'pax' => ['position' => 'right', 'beginAtZero' => true, 'ticks' => ['precision' => 0], 'grid' => ['display' => false], 'title' => ['display' => true, 'text' => 'Επιβάτες']],
                'x' => ['grid' => ['display' => false]],
            ],
        ];
    }
}
