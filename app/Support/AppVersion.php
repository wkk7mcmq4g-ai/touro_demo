<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * The app's version, shown in the user menu: "Έκδοση 134 · 27/10/26 08:54" (commit on hover).
 *
 * The number is the count of commits on main, so every push gets the next one. The Package
 * workflow writes it to version.json when it builds the zip (the server has no git); on a
 * developer's machine it is read from git instead.
 */
class AppVersion
{
    /** @return array{number: ?int, commit: ?string, date: ?Carbon} */
    public static function get(): array
    {
        return once(function (): array {
            $file = base_path('version.json');
            $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

            if (! is_array($data) && is_dir(base_path('.git')) && function_exists('exec')) {
                $run = function (string $cmd): ?string {
                    $out = [];
                    @exec('git -C '.escapeshellarg(base_path()).' '.$cmd.' 2>/dev/null', $out, $code);

                    return $code === 0 && isset($out[0]) ? trim($out[0]) : null;
                };
                $data = ['number' => $run('rev-list --count HEAD'), 'commit' => $run('rev-parse --short HEAD'), 'date' => $run('log -1 --format=%cI')];
            }

            $date = null;
            try {
                $date = filled($data['date'] ?? null) ? Carbon::parse($data['date'])->setTimezone(config('app.timezone')) : null;
            } catch (\Throwable) {
            }

            return [
                'number' => is_numeric($data['number'] ?? null) ? (int) $data['number'] : null,
                'commit' => filled($data['commit'] ?? null) ? substr((string) $data['commit'], 0, 7) : null,
                'date' => $date,
            ];
        });
    }

    public static function label(): string
    {
        ['number' => $number, 'commit' => $commit, 'date' => $date] = self::get();

        return collect(['Έκδοση '.($number ?? '—'), $date?->format('d/m/y H:i')])->filter()->implode(' · ');
    }

    /** Hover text: the exact commit, for checking against GitHub. */
    public static function details(): ?string
    {
        ['commit' => $commit, 'date' => $date] = self::get();

        return $commit ? 'Commit '.$commit.($date ? ' · '.$date->format('d/m/Y H:i') : '') : null;
    }
}
