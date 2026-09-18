<?php

namespace App\Http\Middleware;

use App\Support\ClientApp;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyClientApp
{
    public function handle(Request $request, Closure $next): Response
    {
        $headerName = (string) config('client_apps.header', 'X-Client-App');

        $fromHeader = $request->header($headerName);
        $fromQuery = $request->query('app');
        $fromState = $request->query('state');

        if (is_string($fromState) && str_starts_with($fromState, 'app.')) {
            ClientApp::applyOauthState($fromState);
        } else {
            ClientApp::setCurrent($fromHeader ?: $fromQuery);
        }

        return $next($request);
    }
}
