<?php

namespace App\Providers;

use App\Auth\PhpRunnerUserProvider;
use App\Listeners\LogAccess;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // `php artisan serve` hands the dev server only a few environment variables. On Windows
        // PHP then has no TEMP/TMP, can't buffer larger requests (e.g. "Edit in list") and drops them.
        if (PHP_OS_FAMILY === 'Windows') {
            ServeCommand::$passthroughVariables = array_values(array_unique([
                ...ServeCommand::$passthroughVariables, 'TEMP', 'TMP', 'USERPROFILE', 'LOCALAPPDATA',
            ]));
        }
    }

    public function boot(): void
    {
        // Log in against the PHPRunner users table (see config/auth.php).
        Auth::provider('phprunner', fn ($app, array $config) => new PhpRunnerUserProvider($app['hash'], $config['model']));

        // Greek conventions for every date field: dd/mm/yyyy, weeks start on Monday.
        DatePicker::configureUsing(fn (DatePicker $picker) => $picker
            ->native(false)
            ->displayFormat('d/m/Y')
            ->firstDayOfWeek(1)
            ->closeOnDateSelection());
        DateTimePicker::configureUsing(fn (DateTimePicker $picker) => $picker
            ->native(false)
            ->displayFormat('d/m/Y H:i')
            ->firstDayOfWeek(1));

        // Same access_log rows the PHPRunner login/logout events wrote.
        Event::listen(Login::class, [LogAccess::class, 'login']);
        Event::listen(Logout::class, [LogAccess::class, 'logout']);
    }
}
