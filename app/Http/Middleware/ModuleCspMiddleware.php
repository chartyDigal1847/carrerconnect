<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Content-Security-Policy for CareerConnect (iframe + WebSockets + Portal SSO).
 */
class ModuleCspMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Content-Security-Policy', $this->buildPolicy());

        return $response;
    }

    private function buildPolicy(): string
    {
        $portalUrl = rtrim((string) config('app.portal_url', 'https://deoris.test'), '/');
        $appUrl = rtrim((string) config('app.url', ''), '/');

        $reverbHost = trim((string) config('broadcasting.connections.reverb.options.host', 'localhost'), '"\'');
        $reverbPort = (int) config('broadcasting.connections.reverb.options.port', 8080);
        $reverbScheme = config('broadcasting.connections.reverb.options.scheme', 'http') === 'https' ? 'wss' : 'ws';

        $connectSources = array_filter(array_unique([
            "'self'",
            $appUrl,
            $portalUrl,
            'https://deoris.test',
            'http://deoris.test',
            'https://careerconnect.deoris.test',
            'http://careerconnect.deoris.test',
            'http://localhost',
            'http://127.0.0.1',
            "http://localhost:{$reverbPort}",
            "http://127.0.0.1:{$reverbPort}",
            "ws://localhost:{$reverbPort}",
            "wss://localhost:{$reverbPort}",
            "ws://127.0.0.1:{$reverbPort}",
            "wss://127.0.0.1:{$reverbPort}",
            $reverbHost !== 'localhost' && $reverbHost !== '127.0.0.1'
                ? "{$reverbScheme}://{$reverbHost}:{$reverbPort}"
                : null,
            "{$reverbScheme}://localhost:{$reverbPort}",
            'https://cdn.jsdelivr.net',
        ]));

        $scriptSources = array_filter(array_unique([
            "'self'",
            "'unsafe-inline'",
            $portalUrl,
            'https://deoris.test',
            'https://cdn.jsdelivr.net',
            'https://cdnjs.cloudflare.com',
        ]));

        return implode('; ', [
            "default-src 'self'",
            'script-src '.implode(' ', $scriptSources),
            'script-src-elem '.implode(' ', $scriptSources),
            "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com",
            "font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com",
            "img-src 'self' data: blob:",
            'connect-src '.implode(' ', $connectSources),
            "frame-ancestors {$portalUrl} https://deoris.test http://deoris.test http://localhost https://localhost",
            "frame-src 'self' {$portalUrl}",
            "object-src 'none'",
            "base-uri 'self'",
        ]);
    }
}
