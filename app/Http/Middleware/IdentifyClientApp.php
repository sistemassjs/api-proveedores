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

        ClientApp::setCurrent(is_string($fromHeader) && $fromHeader !== ''
            ? $fromHeader
            : (is_string($fromQuery) ? $fromQuery : null));

        return $next($request);
    }
}
