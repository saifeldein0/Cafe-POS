<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the UI language chosen with the FR/EN toggle (stored in the session).
 */
class SetLocale
{
    public const SESSION_KEY = 'locale';

    /** @var list<string> */
    public const SUPPORTED = ['fr', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->hasSession()
            ? $request->session()->get(self::SESSION_KEY, config('app.locale'))
            : config('app.locale');

        if (in_array($locale, self::SUPPORTED, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
