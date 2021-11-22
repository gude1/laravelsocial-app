<?php

namespace App\Http\Controllers;

use App\FCMNotification;
use App\Http\Resources\UserResource;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserController extends Controller
{

  /**
   * Instantiate a new controller    instance.
   * * @return void
   */
  public function __construct()
  {
    $this->middleware('jwt.verify')
      ->only(['show', 'update', 'destory']);
    if (request()->missing('mobile_confirmed') || request()->mobile_confirmed != "ultimatrix") {
      //auth()->logout(true);
      return response()->json([
        'errmsg' => 'Validation failed',
        'status' => 401,
      ]);
    }
    // PageVisits::saveVisit('user');
  }

  /**
   * Display a listing of the resource.
   *
   * @return \Illuminate\Http\Response
   */
  public function index()
  {
    //
  }

  /**
   * Store a newly created resource in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\Response
   */
  public function store(Request $request)
  {
    $messages = [
      'username.regex' => 'username can only contain letters and underscores',
    ];
    $validate = Validator::make($request->all(), [
      'name' => 'bail|required|regex:/^[a-zA-Z ]*$/|between:10,50',
      'username' => 'bail|required|regex:/^[a-zA-Z_]*$/|between:3,15|unique:users,username|string',
      'gender' => 'bail|sometimes|required|string',
      'email' => 'bail|required|email:filter|unique:users,email',
      'phone' => 'bail|required|digits:11|unique:users,phone',
      'device_token' => 'bail|sometimes|required|string',
      'password' => 'bail|required|alpha_num|min:5',
    ], $messages);
    if ($validate->fails()) {
      $errors = $validate->errors();
      $namerr = $errors->first('name');
      $usernamerr = $errors->first('username');
      $gendererr = $errors->first('gender');
      $emailerr = $errors->first('email');
      $phonerr = $errors->first('phone');
      $passworderr = $errors->first('password');
      $device_token_err = $errors->first('device_token');
      return response()->json([
        'errors' => [
          'nameerr' => $namerr,
          'usernameerr' => $usernamerr,
          'gendererr' => $gendererr,
          'emailerr' => $emailerr,
          'phoneerr' => $phonerr,
          'passworderr' => $passworderr,
          'device_token_err' => $device_token_err,
        ],
        'status' => 400,
      ]);
    } else {
      if (is_numeric($request->username) || $request->username[0] == '@') {
        return response()->json([
          'errors' => [
            'usernameerr' => 'username cannot contain numbers or start with @',
          ],
          'status' => 400,
        ]);
      }
      $user = new User();
      $user->name = $request->name;
      $user->userid = rand(0, 5000);
      $user->username = $request->username;
      $user->email = $request->email;
      $user->phone = $request->phone;
      $user->password = $request->password;
      $user->device_token = $request->device_token;
      if (!is_null($request->gender) && !empty($request->gender)) {
        $user->gender = $request->gender;
      }
      try {
        $user->save();
        $user->profile()->create([
          'profile_id' => '',
          'created_at' => time(),
        ]);
        return response()->json([
          'message' => 'Succesfully Registered',
          'status' => 201,
        ]);
      } catch (Exception $e) {
        if ($user) {
          $user->delete();
        }
        return response()->json([
          'errmsg' => 'Could not register user please try again',
          'status' => 500,
        ]);
      }
    }
  }

  /**
   * signs the user in
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\Response
   */
  public function login(Request $request)
  {
    if (auth()->user()) {
      $token = auth()->getToken(auth()->user());
      // $token = "$token";
      return $this->getAuthUser(auth()->user(), $token);
    }
    $credentials = $request->only('email', 'password');

    if (!$token = auth()->attempt($credentials)) {
      return response()->json([
        'errmsg' => 'Invalid email or password',
        'status' => 400,
      ]);
    }

    if ($request->device_token) {
      auth()->user()->update([
        'last_login' => time(),
        'device_token' => $request->device_token,
      ]);
    } else {
      auth()->user()->update([
        'last_login' => time(),
      ]);
    }
    return $this->getAuthUser(auth()->user(), $token);
  }

  /**
   * function to return authenticated user
   * @param
   */
  public function getAuthUser($user, $token)
  {
    $user->token = $token;
    $profile = $user->profile;
    return response()->json([
      'user' => $user,
      'profile' => $profile,
      'meet_setting' => $user->meet_setting,
      'post_settings' => $profile->post_settings,
      'posts' => $profile->posts()->latest()->first(),
      'status' => 302,
    ]);
  }

  /**
   * public function to add users device token
   *
   * @param  \Illuminate\Http\Request  $req
   * @return \Illuminate\Http\Response
   */
  public function addDeviceToken(Request $req)
  {
    $user = auth()->user();
    $token = $req->device_token;
    if (!$token) {
      return response()->json([
        'errmsg' => 'Missing values to continue',
        'status' => 400,
      ]);
    }

    $add = $user->update([
      'device_token' => $token,
    ]);

    if (!$add) {
      return response()->json([
        'errmsg' => 'something went wrong please try again',
        'status' => 400,
      ]);
    }
    return response()->json([
      'userdetails' => $user->refresh(),
      'message' => 'token saved',
      'status' => 200,
    ]);
  }

  /**
   * Display the specified resource.
   *
   * @param  \App\User  $user
   * @return \Illuminate\Http\Response
   */
  public function show(User $user)
  {
  }

  /**
   * Update the specified resource in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  \App\User  $user
   * @return \Illuminate\Http\Response
   */
  public function update(Request $request)
  {
    /*return $user = auth()->getUser()->device_token;*/
    $validate = Validator::make($request->all(), [
      'username' => [
        'sometimes',
        'bail',
        'required',
        'between:3,10',
        Rule::unique('users', 'username')->ignore($user->userid, 'userid'),
        'string',
      ],
      'phone' => [
        'sometimes',
        'bail',
        'required',
        'digits:11',
        Rule::unique('users', 'phone')->ignore($user->userid, 'userid'),
      ],
      'password' => 'sometimes|bail|required|alpha_num|min:5',
    ]);

    if ($validate->fails()) {
      $errors = $validate->errors();
      $usernamerr = $errors->first('username');
      $phonerr = $errors->first('phone');
      $passworderr = $errors->first('password');
      return response()->json([
        'errors' => [
          'usernameerr' => $usernamerr,
          'phoneerr' => $phonerr,
          'passworderr' => $passworderr,
        ],
        'status' => 400,
      ]);
    } else {
      try {
        if (is_numeric($request->username)) {
          return response()->json([
            'errors' => [
              'usernameerr' => 'username cannot contain numbers',
            ],
            'status' => 400,
          ]);
        }
        $user->update($request->only('username', 'phone', 'password'));
        return response()->json([
          'userupdate' => [
            'username' => $user->username,
            'phone' => $user->phone,
            'password' => $user->password,
          ],
          'status' => 200,
        ]);
      } catch (Exception $e) {
        return response()->json([
          'errmsg' => 'Could not update user details please try again',
          'status' => 500,
        ]);
      }
    }
  }
  /**
   * sending notification
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\Response
   */
  public function sendNotification(Request $request)
  {
    $user = auth()->user();
    $data = [
      "to" => $user->device_token,
      'priority' => 'high',
      'data' => [
        "responseData" => [
          'type' => 'man',
          'payload' => mt_rand(),
        ],
        "notification" => [
          "title" => $request->title,
          "bigPictureUrl" => "https://eportal.oauife.edu.ng/pic.php?image_id=ECN/2016/01320172",
          "largeIconUrl" => $user->profile->avatar[1],
          "body" => $request->body,
        ],
      ],
    ];

    dd(FCMNotification::send($data));
  }

  /**
   * Remove the specified resource from storage.
   * @return \Illuminate\Http\Response
   */
  public function destroy()
  {
    $user = auth()->user();
    if ($user == null) {
      return response()->json([
        'errmsg' => 'Could not delete user at this time please try again',
        'status' => 401,
      ]);
    }
    if ($user->delete() && $user->profile()->delete()) {
      auth()->logout(true);
      return response()->json([
        'message' => 'User deleted successfully',
        'status' => '200',
      ]);
    } else {
      return response()->json([
        'errmsg' => 'Could not delete user at this time please try again',
        'status' => 500,
      ]);
    }
  }
}
