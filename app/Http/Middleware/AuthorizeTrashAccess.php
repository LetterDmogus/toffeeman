<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeTrashAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route() ? $request->route()->getName() : '';
        $path = $request->getPathInfo();

        $isAccessingTrash = $request->boolean('trash')
            || ($routeName && (
                str_ends_with($routeName, '.restore')
                || str_ends_with($routeName, '.force')
                || str_ends_with($routeName, '.bulk-restore')
                || str_ends_with($routeName, '.bulk-force')
            ))
            || str_contains($path, '/restore')
            || str_contains($path, '/force')
            || str_contains($path, '/bulk-restore')
            || str_contains($path, '/bulk-force');

        if ($isAccessingTrash) {
            $user = $request->user();
            if ($user && $user->role !== 'superadmin' && ! $user->hasPermissionTo('view-trash')) {
                abort(403, 'Unauthorized. You do not have permission to access the recycle bin.');
            }
        }

        return $next($request);
    }
}
