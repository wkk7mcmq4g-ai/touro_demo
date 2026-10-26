<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Pages\Page;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\Page as ResourcePage;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Links pasted into WhatsApp, Viber, Telegram, Slack, e-mail and the like: the app fetches the page
 * without signing in, so every preview said "Login". For those link-preview robots only, answer with
 * the page's name and the business logo (Open Graph tags) instead of the redirect to the login.
 *
 * Only the screen's name is shown (e.g. "Πωλήσεις ανά οδηγό", "Transfers · Επεξεργασία"), never any
 * data: no record, customer or amount ever reaches the preview. People still go to the login as before.
 */
class LinkPreview
{
    /** User agents of the services that build link previews. */
    private const ROBOTS = '/WhatsApp|facebookexternalhit|Facebot|TelegramBot|Viber|Slackbot|Slack-ImgProxy|Twitterbot|LinkedInBot|Discordbot|SkypeUriPreview|Teams|Microsoft Office|Outlook|Google-PageRenderer|Iframely|Embedly|redditbot|Pinterest|Applebot|vkShare|Snapchat|Signal/i';

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && ! $request->user() && preg_match(self::ROBOTS, (string) $request->userAgent())
            && ($title = self::title($request)) !== null) {
            return response()->view('filament.brand.link-preview', ['title' => $title, 'url' => $request->fullUrl()]);
        }

        return $next($request);
    }

    /** The screen's name from the route's Filament page, or null when it isn't one. */
    public static function title(Request $request): ?string
    {
        $page = $request->route()?->getControllerClass();
        if (! $page || ! is_subclass_of($page, Page::class)) {
            return null;
        }

        try {
            if (is_subclass_of($page, ResourcePage::class)) {
                $name = (string) $page::getResource()::getNavigationLabel();
                $action = match (true) {
                    is_subclass_of($page, CreateRecord::class) => 'Νέο',
                    is_subclass_of($page, EditRecord::class) => 'Επεξεργασία',
                    is_subclass_of($page, ViewRecord::class) => 'Προβολή',
                    default => null,
                };

                return $action ? "{$name} · {$action}" : $name;
            }

            return (string) $page::getNavigationLabel();
        } catch (\Throwable) {
            return null;
        }
    }
}
