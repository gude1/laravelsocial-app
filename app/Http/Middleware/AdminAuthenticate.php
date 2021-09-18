<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class AdminAuthenticate
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $validate = Auth::guard('admin')->check();
        $list = ['adminloginscreen', 'adminregisterscreen'];

        if ($validate == false && !in_array(request()->path(), $list)) {
            return redirect('/adminloginscreen')
                ->with('login_panelmsg', 'session expired please login');
        }
        
        return $next($request);
    }
}
