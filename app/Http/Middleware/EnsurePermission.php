<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $userPermissions = $request->attributes->get('auth_permissions', []);
        $userRoles = $request->attributes->get('auth_roles', []);

        // Owner/Admin superuser bypass
        if (in_array('owner', $userRoles, true) || in_array('admin', $userRoles, true) || in_array('*', $userPermissions, true)) {
            return $next($request);
        }

        foreach ($permissions as $permission) {
            if (in_array($permission, $userPermissions, true)) {
                return $next($request);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Unauthorized. Missing required permission(s): ' . implode(', ', $permissions),
        ], Response::HTTP_FORBIDDEN);
    }
}
