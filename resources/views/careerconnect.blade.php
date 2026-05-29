<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>CareerConnect — Careers &amp; Faculty Communication</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">

    {{-- Font Awesome icons --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    {{-- CareerConnect design system (matches EntryEase) --}}
    <link rel="stylesheet" href="{{ asset('css/careerconnect.css') }}?v={{ file_exists(public_path('css/careerconnect.css')) ? filemtime(public_path('css/careerconnect.css')) : 1 }}">

    <script>
        // ── Module configuration ──────────────────────────────────────────
        window.CAREERCONNECT_API_BASE = @json(rtrim((string) config('app.url'), '/'));
        window.PORTAL_ORIGIN          = "{{ config('app.portal_url') }}";
        window.CAREERCONNECT_SSO_HANDSHAKE_KEY = @json(config('careerconnect.service_key'));
        window.SSO_TIMEOUT_MS = 8000;
        window.DEORIS_SSO_MODE = "module";

        @php
            $reverbHost   = trim((string) config('broadcasting.connections.reverb.options.host', 'localhost'), '"\'');
            $reverbPort   = (int) config('broadcasting.connections.reverb.options.port', 8082);
            $reverbScheme = config('broadcasting.connections.reverb.options.scheme', 'https');
            // When running over HTTPS the browser requires WSS.
            // If scheme is http but APP_URL is https, upgrade automatically.
            $appIsHttps = str_starts_with((string) config('app.url'), 'https');
            if ($appIsHttps && $reverbScheme === 'http') {
                $reverbScheme = 'https';
                // Use the app hostname so the browser can reach it (not localhost)
                $appHost = parse_url((string) config('app.url'), PHP_URL_HOST) ?: $reverbHost;
                if (in_array($reverbHost, ['localhost', '127.0.0.1'], true)) {
                    $reverbHost = $appHost;
                }
            }
        @endphp
        window.REVERB_CONFIG = {
            key:          @json(config('broadcasting.connections.reverb.key')),
            host:         @json($reverbHost),
            port:         @json($reverbPort),
            scheme:       @json($reverbScheme),
            authEndpoint: @json(url('/broadcasting/auth')),
        };
    </script>
</head>
<body>

{{-- Root mount point — JS replaces this entirely after SSO --}}
<div id="careerconnect-root" style="
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #F4F6F9;
">
    <div id="careerconnect-loader" style="
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 16px;
    ">
        <div class="cc-spinner"></div>
        <p style="color:#7C3041;font-weight:700;font-size:15px;letter-spacing:.02em;">
            Loading CareerConnect…
        </p>
        <p id="careerconnect-loader-error" style="
            color:#dc2626;font-size:13px;
            display:none;max-width:360px;text-align:center;
        "></p>
    </div>
</div>

<style>
/* Inline spinner so it works before the CSS file loads */
.cc-spinner {
    width: 48px; height: 48px;
    border-radius: 50%;
    border: 4px solid rgba(124,48,65,.15);
    border-top-color: #7C3041;
    animation: ccSpin .8s linear infinite;
}
@keyframes ccSpin { to { transform: rotate(360deg); } }
</style>

{{-- SSO bridge must load before the app script --}}
<script src="{{ rtrim(config('app.portal_url', 'https://deoris.test'), '/') }}/module-bridge.js"></script>

{{-- Pusher + Laravel Echo for Reverb WebSockets --}}
<script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js"></script>

{{-- Real-time client --}}
<script src="{{ asset('js/realtime.js') }}?v={{ file_exists(public_path('js/realtime.js')) ? filemtime(public_path('js/realtime.js')) : 1 }}"></script>

{{-- Main app --}}
<script src="{{ asset('js/careerconnect.js') }}?v={{ file_exists(public_path('js/careerconnect.js')) ? filemtime(public_path('js/careerconnect.js')) : 1 }}"></script>
<script src="{{ asset('js/careerconnect-opportunities.js') }}?v={{ file_exists(public_path('js/careerconnect-opportunities.js')) ? filemtime(public_path('js/careerconnect-opportunities.js')) : 1 }}"></script>

@if(app()->environment('local') && ! request()->boolean('embedded'))
<script>
    // ── Standalone dev mode ───────────────────────────────────────────────
    // When accessed directly (not inside the DEORIS portal iframe) in the
    // local environment, module-bridge.js cannot receive an SSO token from
    // a parent frame and emits "missing_iframe_context".
    // This block fires a synthetic module:ready so the app boots without a
    // real portal session. NEVER active in production.
    window.DEORIS_API_TOKEN = "dev:admin-001";
    setTimeout(function () {
        window.dispatchEvent(new CustomEvent('module:ready', {
            detail: {
                success: true,
                user: {
                    id: 'admin-001',
                    name: 'Admin User (Dev)',
                    email: 'admin@university.edu',
                    role: 'admin',
                },
                embedded: false,
                portalOrigin: window.PORTAL_ORIGIN,
            },
        }));
    }, 300);
</script>
@endif

</body>
</html>
