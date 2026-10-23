<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\TableLayouts;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Loads a user's table layouts into the session once, and saves them back when they change. */
class SyncTableLayouts
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $request->session()->get('myat.layouts_loaded')) {
            TableLayouts::load($user);
            $request->session()->put('myat.layouts_loaded', true);
        }

        $response = $next($request);

        if ($user instanceof User && $request->session()->get('myat.layouts_loaded')) {
            try {
                TableLayouts::save($user);
            } catch (\Throwable $e) {
                report($e); // a layout that cannot be saved must never break the page
            }
        }

        return $response;
    }
}
