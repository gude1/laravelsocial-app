<?php

namespace App\Http\Middleware;

use App\UserActivity;
use Closure;

class VerifyOthers
{
  /**
  * Handle an incoming request.
  *
  * @param  \Illuminate\Http\Request  $request
  * @param  \Closure  $next
  * @return mixed
  */
  public function handle($request, Closure $next) {
    /*UserActivity::saveActivity();
    return $next($request);*/
    $authuser = auth()->user();
    $authprofile = $authuser->profile;
    if ($authuser->deleted) {
      return response()->json([
        'errmsg' => 'account does not exist',
        'status' => 401,
      ]);
    } else if ($authuser->suspended) {
      return response()->json([
        'errmsg' => 'account has being suspended',
        'status' => 406,
      ]);
    } else if (!$authuser->approved) {
      return response()->json([
        'errmsg' => 'account is not approved yet ',
        'status' => 406,
      ]);
    } else if (is_null($authprofile) || empty($authprofile)) {
      //auth()->logout(true);
      return response()->json([
        'errmsg' => 'profile incomplete',
        'status' => 401,
      ]);
    } elseif (is_null($authprofile->campus) || empty($authprofile->campus) || in_array(null, $authprofile->avatar)) {
      //auth()->logout(true);
      return response()->json([
        'errmsg' => 'profile incomplete',
        'status' => 401,
      ]);
    } elseif (!in_array($authuser->gender, ['male', 'female'])) {
      //auth()->logout(true);
      return response()->json([
        'errmsg' => 'profile incomplete',
        'status' => 401,
      ]);
    } else if (!is_string($authprofile->profile_name) || empty($authprofile->profile_name)) {
      return response()->json([
        'errmsg' => 'profile incomplete',
        'status' => 401,
      ]);
    }
    $update = $authuser->update([
      'last_login' => time(),
    ]);
    if (!$update) {
      return response()->json([
        'errmsg' => 'could not verify user',
        'status' => 401,
      ]);
    }
    return $next($request);
  }
}