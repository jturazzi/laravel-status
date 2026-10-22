<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public const LOCALES = ['en', 'fr'];

    public const COOKIE = 'locale';

    /**
     * Explicit choice (?lang=, then cookie), else the browser language, else APP_LOCALE.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $chosen = in_array($request->query('lang'), self::LOCALES, true) ? $request->query('lang') : null;
        $saved = in_array($request->cookie(self::COOKIE), self::LOCALES, true) ? $request->cookie(self::COOKIE) : null;
        $fallback = in_array(config('app.locale'), self::LOCALES, true) ? config('app.locale') : self::LOCALES[0];

        $locale = $chosen ?? $saved ?? $request->getPreferredLanguage(self::LOCALES) ?? $fallback;

        app()->setLocale($locale);
        Carbon::setLocale($locale);

        $response = $next($request);

        if ($chosen) {
            $response->headers->setCookie(cookie()->forever(self::COOKIE, $chosen));
        }

        return $response;
    }
}
