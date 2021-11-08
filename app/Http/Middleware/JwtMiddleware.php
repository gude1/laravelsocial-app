<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use JWTAuth;
use Tymon\JWTAuth\Http\Middleware\BaseMiddleware;

class JwtMiddleware extends BaseMiddleware
{

  /**
   * Handles incoming request
   *
   * @param  \Illuminate\Http\Request  $request
   */
  public function handle($request, Closure $next)
  {
    try {
      $user = JWTAuth::parseToken()->authenticate();
      if (!$user) {
        auth()->logout(true);
        return response()->json([
          'errmsg' => 'An unknown error could not validate user',
          'status' => 401,
        ]);
      } else if ($request->missing('mobile_confirmed') || $request->mobile_confirmed != "ultimatrix") {
        //auth()->logout(true);
        return response()->json([
          'errmsg' => 'Validation failed',
          'status' => 401,
        ]);
      }
    } catch (Exception $e) {
      if ($e instanceof \Tymon\JWTAuth\Exceptions\TokenInvalidException) {
        return response()->json([
          'errmsg' => 'Token is Invalid',
          'status' => 401,
        ]);
      } else if ($e instanceof \Tymon\JWTAuth\Exceptions\TokenExpiredException) {
        return response()->json([
          'errmsg' => 'Token is Expired',
          'status' => 401,
        ]);
      } else {
        return response()->json([
          'errmsg' => 'Authorization Token not found',
          'status' => 401,
        ]);
      }
    }
    return $next($request);
  }
}
