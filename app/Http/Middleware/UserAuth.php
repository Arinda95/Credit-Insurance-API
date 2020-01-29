<?php

namespace App\Http\Middleware;

use Closure;
use App\Session;
use App\Admin;

class UserAuth
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
        $authpack = $request->input('auth');
        try {
            $session_id = (Session::where('session_id', $authpack['session_id'])->first())->session_id;
        }
        catch (\Throwable $e) {
            return response('Unauthorized', 401);
        }
        $input_token = $authpack['token'];
        $target_hashed_token = (Session::where('session_id', $session_id)->first())->token;
        
        //match
        if(password_verify($input_token, $target_hashed_token)){
            return $next($request);
        }
        else{
            return response('Unauthorized', 401);
        }
    }
}
