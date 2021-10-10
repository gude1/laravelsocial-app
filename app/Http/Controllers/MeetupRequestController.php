<?php

namespace App\Http\Controllers;

use App\MeetupRequest;
use App\MeetupSetting;
use App\PageVisits;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MeetupRequestController extends Controller
{
  protected $user;
  protected $profile;
  protected $user_blocked_profiles_id = [];

  /**
  *Instantiate a new controller instance.
  * @return void
  */
  public function __construct() {
    $this->middleware('jwt.verify');
    $this->middleware('app.verify');
    $this->user = auth()->user();
    if (!is_null($this->user)) {
      $this->profile = $this->user->profile;
    } else {
      return;
    }
    if (!is_null($this->profile->profile_settings)) {
      $this->user_blocked_profiles_id = $this->profile->profile_settings->blocked_profiles;
    }
    PageVisits::saveVisit('meetuprequest');
  }

  /**
  * returns profile meetup list
  *
  * @param  \Illuminate\Http\Request  $req
  * @return \Illuminate\Http\Response
  */
  public function index(Request $req) {
    $today = date('Y-m-d', time());
    $filter_arr = [];
    if ($req->request_category) {
      $filter_arr['request_category'] = $req->request_category;
    }
    if ($req->request_mood) {
      $filter_arr['request_mood'] = $req->request_mood;
    }
    $blacklistarr = [];
    $db_blacklistarr = is_null($this->profile->meetup_setting) ? [] :
    $this->profile->meetup_setting->black_listed_arr;

    if (is_array($req->blacklist)) {
      $blacklistarr = $req->blacklist;
    }
    // return [$req->campus];
    if ($req->campus) {
      $meetup_list = MeetupRequest::whereHas('requester_profile', function (Builder $query) {
        $query->whereHas('user', function (Builder $query) {
          $query->where([
            'deleted' => false,
            'suspended' => false,
            'approved' => true,
          ]);
        });
        $query->where('campus', request()->campus);
      })
      ->whereHas('requester_meet_profile', function (Builder $query) {
        $query->where('black_listed_arr', 'not like', "%{$this->profile->profile_id}%");
      })
      ->with(['requester_profile.user', 'requester_meet_profile'])
      ->where(
        array_merge([
          'deleted' => false,
          //['requester_id', '!=', $this->profile->profile_id],
          ['expires_at', '>', time()],
        ], $filter_arr)
      )
      ->whereNotIn('request_id', $blacklistarr)
      ->whereNotIn('requester_id', $db_blacklistarr)
      ->orderBy('id', 'desc')
      ->simplePaginate(10);
    } else {
      $meetup_list = MeetupRequest::whereHas('requester_profile', function (Builder $query) {
        $query->whereHas('user', function (Builder $query) {
          $query->where([
            'deleted' => false,
            'suspended' => false,
            'approved' => true,
          ]);
        });
      })
      ->whereHas('requester_meet_profile', function (Builder $query) {
        $query->where('black_listed_arr', 'not like', "%{$this->profile->profile_id}%");
      })
      ->with(['requester_profile.user', 'requester_meet_profile'])
      ->where(
        array_merge([
          'deleted' => false,
          // ['requester_id', '!=', $this->profile->profile_id],
          ['expires_at', '>', time()],
        ], $filter_arr))
      ->whereNotIn('request_id', $blacklistarr)
      ->whereNotIn('requester_id', $db_blacklistarr)
      ->orderBy('id', 'desc')
      ->simplePaginate(10);
    }

    if (count($meetup_list) < 1) {
      return response()->json([
        'errmsg' => 'No results found, please try changing your request setting to expand your options',
        'status' => 404,
      ]);
    } else {
      return response()->json([
        'message' => 'found',
        'meetup_list' => $meetup_list->items(),
        'my_num_req_left' => $this->returnNumRequestLeft(),
        'next_url' => $meetup_list->nextPageUrl(),
        'status' => 200,
      ]);
    }

  }

  /**
  * public function to handle add and remove profile_id from meet_black_list
  *
  * @param  \Illuminate\Http\Request  $req
  * @return \Illuminate\Http\Response
  */
  public function handleBlackList(Request $req) {
    $userprofile = $this->profile;
    $req_profile_id = $req->profile_id;

    if (is_null($req_profile_id) || empty($req_profile_id) || $userprofile->profile_id == $req_profile_id) {
      return response()->json([
        'errmsg' => 'something went wrong please try again',
        'status' => 400,
      ]);
    }

    $black_listed_arr = is_null($userprofile->meetup_setting) ? [] :
    $userprofile->meetup_setting->black_listed_arr;

    if (in_array($req_profile_id, $black_listed_arr)) {
      $index = array_search($req_profile_id, $black_listed_arr);
      unset($black_listed_arr[$index]);
      $black_listed_arr = array_values($black_listed_arr);
    } else {
      array_push($black_listed_arr, $req_profile_id);
    }

    $blacklist_action = $userprofile->meetup_setting()->updateOrCreate(
      ['owner_id' => $userprofile->profile_id],
      ['black_listed_arr' => json_encode($black_listed_arr)],
    );

    if (!$blacklist_action) {
      return response()->json([
        'errmsg' => 'something went wrong please try again',
        'status' => 500,
      ]);
    }

    return response()->json([
      'message' => 'action successful!',
      'status' => 200,
    ]);
  }

  /**
  * Save meetup name and avatar
  *
  * @param  \Illuminate\Http\Request  $req
  * @return \Illuminate\Http\Response
  */
  public function saveMeetupDetails(Request $req) {
    $userprofile = $this->profi
    $validate = Validator::make($req->all(), [
      'meetup_name' => 'bail|sometimes|required|string|between:3,15',
      'avatar_name' => 'bail|sometimes|required|string|between:3,100',
      'meetup_avatar' => 'bail|sometimes|required|mimes:jpg,jpeg,png,bmp,gif,svg,webp|dimensions:max_width=400,max_height=400',
    ]);

    if ($validate->fails()) {
      $errors = $validate->errors();
      return response()->json([
        'status' => 400,
        'errors' => [
          'meetup_name_err' => $errors->first('meetup_name'),
          'avatar_name_err' => $errors->first('avatar_name'),
          'meetup_avatar_err' => $errors->first('meetup_avatar'),
        ],
      ]);
    }
    $data = $req->only('meetup_name', 'avatar_name');
    $meetup_avatar = $this->uploadMeetPic();
    if ((count($data) < 1 || is_null($data)) && is_null($meetup_avatar)) {
      return response()->json([
        'errmsg' => 'Missing values to continue',
        'status' => 400,
      ]);
    }
    if (!is_null($meetup_avatar)) {
      $data['meetup_avatar'] = $meetup_avatar;
    }
    $save = $userprofile->meetup_setting()->updateOrCreate(
      ['owner_id' => $userprofile->profile_id],
      $data
    );

    if (!$save) {
      return response()->json([
        'errmsg' => 'something went wrong please try again',
        'status' => 500,
      ]);
    }
    return response()->json([
      'meetup_setting' => $save->refresh(),
      'message' => 'success!',
      'status' => 200,
    ]);

  }

  /**
  * protected to upload meetup profile pic
  */
  protected function uploadMeetPic() {
    $user = $this->user;
    if (request()->hasFile('meetup_avatar') && request()->file('meetup_avatar')->isValid()) {
      $ext = request()->meetup_avatar->extension();
      $filename = $user->username . 'meetup' . rand(0, 73737) . '.' . $ext;
      if (!$path = request()->meetup_avatar->storeAs('images/uploads/meetupprofilepics', $filename, 'publics')) {
        return null;
      } else {
        return $path;
      }
    }
    return null;
  }

  /**
  * Store a newly created resource in storage.
  *
  * @param  \Illuminate\Http\Request  $request
  * @return \Illuminate\Http\Response
  */
  public function store(Request $request) {
    $num_times = $this->returnNumRequestLeft();
    $category_current_count = MeetupRequest::where([
      'request_category' => $request->request_category,
      ['expires_at', '>', time()],
    ])->count();

    if ($num_times < 1) {
      return response()->json([
        'errmsg' => 'You can only make 3 meet request per day',
        'status' => 400,
      ]);
    }

    $validate = Validator::make($request->all(), [
      'request_msg' => 'bail|required|string|between:3,250',
      'request_addr' => 'bail|sometimes|required|string|between:6,50',
      'request_location' => 'bail|sometimes|required|integer',
      'request_category' => 'bail|required|string|between:5,20',
      'request_mood' => 'bail|required|string',
      //'request_mood_emoji' => 'bail|sometimes|required|string',
      'expires_after' => 'bail|sometimes|digits_between:1,2',
    ]);

    if ($validate->fails()) {
      $errors = $validate->errors();
      return response()->json([
        'status' => 400,
        'errors' => [
          'request_msg_err' => $errors->first('request_msg'),
          'request_addr_err' => $errors->first('request_addr'),
          'request_location_err' => $errors->first('request_location'),
          'request_category_err' => $errors->first('request_category'),
          'request_mood_err' => $errors->first('request_mood'),
          'expires_after' => $errors->first('expires_after'),
        ],
      ]);
    }

    $to_create_metup_req = $request->only(
      'request_msg',
      'request_addr',
      'request_location',
      'request_category',
      'request_mood'
    );

    $to_create_metup_req = array_merge($to_create_metup_req, [
      'requester_id' => $this->profile->profile_id,
      'request_id' => md5(rand(2452, 1632662727)),
      'created_at' => time(),
      'updated_at' => time(),
      'expires_at' => time() + $this->secondsFrom($request->expires_after + 0),
    ]);

    $create_req = MeetupRequest::create($to_create_metup_req);
    if (!$create_req) {
      return response()->json([
        'errmsg' => 'something went wrong please try again',
        'status' => 500,
      ]);
    }
    return response()->json([
      'message' => 'Meetup request created',
      'meetup_req' => $create_req->fresh(['requester_profile.user', 'requester_meet_profile']),
      'num_req_left' => $this->returnNumRequestLeft(),
      'status' => 200,
    ]);
  }

  /**
  * Display the specified resource.
  *
  * @param  \App\MeetupRequest  $meetupRequest
  * @return \Illuminate\Http\Response
  */
  public function show(Request $req) {
    $meetup_reqid = $req->meetup_reqid;
    if (is_null($meetup_reqid) || empty($meetup_reqid)) {
      return response()->json([
        'errmsg' => 'Missing value to continue',
        'status' => 400,
      ]);
    }

    $meetup_req = MeetupRequest::whereHas('requester_profile', function (Builder $query) {
      $query->whereHas('user', function (Builder $query) {
        $query->where([
          'deleted' => false,
          'suspended' => false,
          'approved' => true,
        ]);
      });
    })
    ->whereHas('requester_meet_profile', function (Builder $query) {
      $query->where('black_listed_arr', 'not like', "%{$this->profile->profile_id}%");
    })
    ->with('requester_profile.user')
    ->where([
      'request_id' => $meetup_reqid,
      'deleted' => false,
      'expired' => false,
    ])
    ->first();
    if (is_null($meetup_req) || empty($meetup_req)) {
      return response()->json([
        'errmsg' => 'Request not found',
        'status' => 404,
      ]);
    }
    return response()->json([
      'message' => 'found',
      'num_req_left' => $this->returnNumRequestLeft(),
      'meetup_req' => $meetup_req,
      'status' => 200,
    ]);

  }

  /**
  * public function to search meetup requests starts here
  *
  * @param  \Illuminate\Http\Request  $request
  * @return \Illuminate\Http\Response
  */
  public function searchRequests(Request $req) {
    $userprofile = $this->profile;
    $search_word = $req->searchword;
    if (is_null($search_word) || empty($search_word)) {
      return response()->json([
        'errmsg' => 'Missing values to continue',
        'status' => 400,
      ]);
    }
    $search_action = MeetupRequest::whereHas('requester_profile', function (Builder $query) {
      $query->whereHas('user', function (Builder $query) {
        $query->where([
          'deleted' => false,
          'suspended' => false,
          'approved' => true,
        ]);
      });
    })
    ->whereHas('requester_meet_profile', function (Builder $query) {
      $query->where('black_listed_arr', 'not like', "%{$this->profile->profile_id}%");
    })
    ->where([
      ['request_msg', 'like', "%{$search_word}%"],
      ['deleted', '=', false],
      ['expired', '=', false],
    ])
    ->orderBy('id', 'desc')
    ->simplePaginate(30);
    if (count($search_action) < 1) {
      return response()->json([
        'errmsg' => 'no search results',
        'status' => 404,
      ]);
    }
    return response()->json([
      'message' => 'results found',
      'search_results' => $search_action->items(),
      'next_page_url' => $search_action->nextPageUrl(),
      'status' => 200,
    ]);
  }

  /**
  * protected function to return future time
  *
  * @param $num_of_hours
  * @return Int
  */
  public function secondsFrom($num_of_hours = 0) {
    $num_of_hours = $num_of_hours < 1 || $num_of_hours > 24 ? 24 : $num_of_hours;
    return 3600 * $num_of_hours;
  }

  /**
  * Update the specified resource in storage.
  *
  * @param  \Illuminate\Http\Request  $request
  * @param  \App\MeetupRequest  $meetupRequest
  * @return \Illuminate\Http\Response
  */
  public function update(Request $request, MeetupRequest $meetupRequest) {
    //
  }

  /**
  * protected function to set Request as expired
  */
  protected function setExpired() {
    $set = MeetupRequest::where('expires_at', '<=', time())
    ->update([
      'expired' => true,
    ]);
  }

  /**
  * public function to get meetup_requests created by user
  *
  * @return \Illuminate\Http\Response
  */
  public function getProfilesMeetupRequests() {
    $authprofile_meetup_reqs = $this->profile->meetup_requests()
    ->with(['requester_profile.user', 'requester_meet_profile'])
    ->where([
      'deleted' => false,
      ['expires_at', '>', time()],
    ])
    ->orderBy('id', 'desc')
    ->simplePaginate(10);
    if (count($authprofile_meetup_reqs) < 1) {
      return response()->json([
        'errmsg' => 'no requests',
        'status' => 404,
      ]);
    }
    return response()->json([
      'message' => 'found',
      'meetup_reqs' => $authprofile_meetup_reqs->items(),
      'next_page_url' => $authprofile_meetup_reqs->nextPageUrl(),
      'status' => 200,
    ]);

  }

  /**
  * public function  to respond to a meet request
  *
  * @param  \Illuminate\Http\Request  $req
  * @return \Illuminate\Http\Response
  */
  public function respondToRequest(Request $req) {
    $userprofile = $this->profile;
    $request_id = $req->req_id;
    $response_msg = $req->res_msg;
    if ((is_null($request_id) || empty($request_id)) ||
      (is_null($request_id) || empty($request_id))
    ) {
      return response()->json([
        'errmsg' => 'Missing values to continue',
        'status' => 400,
      ]);
    }
    $meet_req = MeetupRequest::whereHas('requester_meet_profile', function (Builder $query) {
      $query->where('black_listed_arr', 'not like', "%{$this->profile->profile_id}%");
    })->firstWhere([
      'request_id' => $request_id,
      'deleted' => false,
    ]);

    if (!$meet_req || $meet_req->expires_at <= time()) {
      return response()->json([
        'errmsg' => 'Meet request not found or expired',
        'status' => 400,
      ]);
    }
    $check = $meet_req[$userprofile->profile_id];

    if (is_array($check)) {
      return response()->json([
        'errmsg' => 'you have already reacted to the request',
        'status' => 400,
      ]);
    }

    $meet_req[$userprofile->profile_id] = $response_msg;
    $respond = $meet_req->update([
      'responders_ids' => json_encode($meet_req),
    ]);
    if (!$respond) {
      return response()->json([
        'errmsg' => 'Failed to respond to request please try again',
        'status' => 500,
      ]);
    }
    return response()->json([
      'message' => 'Response sent',
      'status' => 200,
    ]);
  }

  /**
  * Private function to know the number of request left for today
  *
  */
  private function returnNumRequestLeft() {
    $profile = $this->profile;
    $today = date('Y-m-d', time());
    $count = DB::table(
      DB::raw(
        "( select created_at from  meetup_requests where strftime('%Y-%m-%d',created_at,'unixepoch') = '$today' and requester_id='{$profile->profile_id}' and deleted ='0')"
      )
    )->count();
    $today_delete_count = DB::table(
      DB::raw(
        "( select created_at from  meetup_requests where strftime('%Y-%m-%d',created_at,'unixepoch') = '$today' and  requester_id='{$profile->profile_id}' and deleted ='1')"
      )
    )->count();
    $count = 3 - $count;
    $count = $count < 0 ? 0 : $count;
    if ($today_delete_count >= 3) {
      return 0;
    }
    return $count;
  }

  /**
  * Public function to return current users meet profile
  *
  * @param  \Illuminate\Http\Request  $request
  * @return \Illuminate\Http\Response
  */
  public function getAMeetSetting(Request $req) {
    $meet_setting = MeetupSetting::firstWhere('owner_id', $req->ownerid);
    if (!$meet_setting) {
      return response()->json([
        'errmsg' => "meet setting not found",
        'status' => 404,
      ]);
    }

    return response()->json([
      'msg' => "meet setting found",
      'meet_setting' => $meet_setting,
      'status' => 200,
    ]);
  }

  /**
  * Remove the specified resource from storage.
  *
  * @param  \Illuminate\Http\Request  $request
  * @return \Illuminate\Http\Response
  */
  public function destroy(Request $req) {
    $meetup_reqid = $req->meetup_reqid;
    if (is_null($meetup_reqid) || empty($meetup_reqid)) {
      return response()->json([
        'errmsg' => 'Missing values to continue',
        'status' => 400,
      ]);
    }
    $delete = MeetupRequest::where([
      'requester_id' => $this->profile->profile_id,
      'request_id' => $meetup_reqid,
    ])->update([
      'deleted' => true,
      'updated_at' => time(),
    ]);
    if (!$delete) {
      return response()->json([
        'errmsg' => 'something went wrong please try again',
        'status' => 500,
      ]);
    }
    return response()->json([
      'message' => 'deleted',
      'status' => 200,
    ]);
  }
}