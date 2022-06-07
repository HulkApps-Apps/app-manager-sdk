<?php

namespace HulkApps\AppManager\app\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use HulkApps\AppManager\app\Traits\HasPlan as HasPlanTrait;

class HasPlan
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
        if(Auth::user()) {
            if (Auth::user() instanceof HasPlanTrait && Auth::user()->hasPlan()) {
                return $next($request);
            }
        }

        if ($request->ajax()) {
            return response(['message' => 'Please select plan'], 401);
        } return redirect(config('app-manager.plan_route'))->withErrors(['message' => 'Please select plan']);

    }
}
