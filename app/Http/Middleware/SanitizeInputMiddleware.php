<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SanitizeInputMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $input = $request->all();
        array_walk_recursive($input, function (&$value) {
            if (is_string($value)) {
                $value = trim($value);
                // Strip dangerous script tags while keeping standard text/descriptions
                $value = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $value);
            }
        });
        $request->merge($input);

        return $next($request);
    }
}
