<?php

namespace HulkApps\AppManager\app\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyAPIRequest
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if($request->header('token')) {

            if(config('app-manager.secret') === $request->header('token')) {
                return $next($request);
            }

            return response(['message' => 'API token is not valid'], 401);
        }
        return response(['message' => 'API token is required'], 401);}
}
