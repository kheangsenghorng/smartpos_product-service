<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    /**
     * Known automated vulnerability scanner and attack tool User-Agent signatures.
     */
    protected array $blockedUserAgents = [
        'sqlmap',
        'nikto',
        'acunetix',
        'w3af',
        'havij',
        'dirbuster',
        'gobuster',
        'nmap',
        'masscan',
        'zgrab',
        'hydra',
        'metasploit',
        'morfeus',
        'nessus',
        'arachni',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $userAgent = strtolower($request->userAgent() ?? '');

        if (!empty($userAgent)) {
            foreach ($this->blockedUserAgents as $signature) {
                if (str_contains($userAgent, $signature)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Access denied.',
                    ], Response::HTTP_FORBIDDEN);
                }
            }
        }

        $response = $next($request);

        $response->headers->remove('X-Powered-By');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        if ($request->is('docs*') || $request->is('docs/*')) {
            $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://unpkg.com https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://unpkg.com https://fonts.googleapis.com https://cdn.jsdelivr.net; font-src 'self' https://fonts.gstatic.com data:; connect-src 'self' https: http:; img-src 'self' data: https:; frame-ancestors 'none';");
        } else {
            $response->headers->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none';");
        }

        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        return $response;
    }
}
