<?php

namespace App\Http\Middleware;

use Closure;
use App\Session;
use App\Admin;

class AdminAuthOne
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

        $target_level = 1;
        $admin_id = (Session::where('session_id', $session_id)->first())->user_id;
        $found_level = (Admin::where('admin_id', $admin_id))->first()->level;
        
        //match
        if(password_verify($input_token, $target_hashed_token)){
            if($target_level <= $found_level){
                return $next($request);
            }
            else if($target_level > $found_level){
                return response('Unauthorized', 401);
            }
        }
        else{
            return response('Unauthorized', 401);
        }
    }
}
