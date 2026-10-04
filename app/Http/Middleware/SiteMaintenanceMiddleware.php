<?php

namespace App\Http\Middleware;

use App\Models\SiteSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SiteMaintenanceMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $enabled = SiteSetting::valueOf('maintenance_enabled', '0') === '1';

        if (!$enabled) {
            return $next($request);
        }

        if ($request->user()?->isAdmin()) {
            return $next($request);
        }

        if ($request->is('login', 'logout', 'admin/*')) {
            return $next($request);
        }

        return response()->view('errors.maintenance', [
            'message' => SiteSetting::valueOf(
                'maintenance_message',
                'We are carrying out scheduled maintenance. Please check back shortly.'
            ),
        ], 503);
    }
}
