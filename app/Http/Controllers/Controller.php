<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Get the authenticated business UUID from request attributes, headers, or body.
     */
    protected function getBusinessUuid(Request $request): ?string
    {
        return $request->attributes->get('auth_business_uuid') 
            ?? $request->attributes->get('business_uuid') 
            ?? $request->header('X-Business-Uuid') 
            ?? $request->input('business_uuid');
    }

    /**
     * Get the authenticated user UUID from request attributes or JWT payload.
     */
    protected function getUserUuid(Request $request): ?string
    {
        return $request->attributes->get('auth_user_uuid') 
            ?? $request->attributes->get('user_uuid');
    }

    /**
     * Check if the authenticated user has global administrative privileges.
     */
    protected function isGlobalAdmin(Request $request): bool
    {
        $roles = (array) $request->attributes->get('auth_roles', []);
        $payload = (array) $request->attributes->get('jwt_payload', []);

        return in_array('admin', $roles, true)
            || in_array('super_admin', $roles, true)
            || in_array('superadmin', $roles, true)
            || !empty($payload['is_admin']);
    }
}
