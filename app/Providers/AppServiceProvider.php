<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($hotFile = config('app.vite_hot_file')) {
            Vite::useHotFile($hotFile);
        }
        RateLimiter::for('analysis', function (Request $request) {
            if (! $request->boolean('use_ai') && ! $request->routeIs('imports.ocr')) {
                return Limit::perMinute(20)->by('local:'.$request->user()?->id);
            }

            return [
                Limit::perMinute(3)->by((string) $request->user()?->id),
                Limit::perDay(config('analysis.daily_limit'))->by('daily:'.$request->user()?->id),
            ];
        });
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(Str::lower($request->string('email')->toString()).'|'.$request->ip()),
            Limit::perMinute(30)->by($request->ip()),
        ]);
    }
}
