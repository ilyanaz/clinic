<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AuthenticateMiddleware
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
        if (!session()->has('user_id')) {
            // Check if admin user exists, if not redirect to install
            try {
                $adminExists = \App\Models\User::where('username', 'admin')->exists();
                
                if (!$adminExists) {
                    return redirect()->route('install');
                }
            } catch (\Exception $e) {
                return redirect()->route('install');
            }
            
            // Use guest redirect so Laravel stores the intended URL.
            return redirect()->guest(route('login'));
        }

        return $next($request);
    }
}
