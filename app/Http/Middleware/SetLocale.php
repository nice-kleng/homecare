<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Set locale aplikasi ke Indonesia.
 * Bisa dioverride via query param ?lang=en untuk keperluan development.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->query('lang')
            ?? $request->user()?->locale
            ?? session('locale')
            ?? config('app.locale', 'id');

        // Whitelist locale yang diizinkan
        if (! in_array($locale, ['id', 'en'])) {
            $locale = 'id';
        }

        App::setLocale($locale);
        session(['locale' => $locale]);

        return $next($request);
    }
}
