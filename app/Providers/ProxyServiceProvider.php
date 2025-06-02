<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Http\Request;

class ProxyServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Trust all proxies for this application
        $headers = Request::HEADER_X_FORWARDED_FOR |
                   Request::HEADER_X_FORWARDED_HOST |
                   Request::HEADER_X_FORWARDED_PORT |
                   Request::HEADER_X_FORWARDED_PROTO |
                   Request::HEADER_X_FORWARDED_AWS_ELB;
                   
        Request::setTrustedProxies(['*'], $headers);

        // Force HTTPS URLs when behind a reverse proxy
        if ($this->app->environment('production') || $this->isHttpsRequest()) {
            URL::forceScheme('https');
        }
    }

    /**
     * Determine if the request is coming through HTTPS proxy
     */
    private function isHttpsRequest(): bool
    {
        return (
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
            (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') ||
            (isset($_SERVER['HTTP_X_URL_SCHEME']) && $_SERVER['HTTP_X_URL_SCHEME'] === 'https')
        );
    }
} 