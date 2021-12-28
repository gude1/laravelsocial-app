<?php

namespace App\Http\Controllers;

use App\FCMNotification;
use App\MeetupRequest;
use App\MeetupRequestConversation;
use App\PageVisits;
use App\Profile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
////use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;

class MeetupRequestConversationController extends Controller
{
  protected $user;
  protected $profile;
  protected $user_blocked_profiles_id = [];

  /**
   * Instantiate a new controller instance.
   *
   * @return void
   */
  public function __construct()
  {
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
    PageVisits::saveVisit('meetuprequestconversation');
  }

  /**
   * Display a listing of the resource.
   *
   * @param  \Illuminate\Http\Request  $req
   * @return \Illuminate\Http\Response
   */
  public function index(Request $req)
  {
    $userprofile = $this->profile;
    $min = $req->min;
    $max = $req->max;
    if (!is_null($min) && is_integer($min)) {
      $meet_convs = MeetupRequestConversation::select('conversation_id')->whereHas('origin_meet_request', function (Builder $query) {
        $query->where([
          'deleted' => false,
          ['expires_at', '>', time()],
        ]);
      })
        ->where(function (Builder $query) {
          $query->orWhere([
            'sender_id' => $this->profile->profile_id,
            'receiver_id' => $this->profile->profile_id,
          ]);
        })
        ->where('id', '<', $min)
        ->with(['origin_meet_request'])
        ->orderBy('id', 'desc')
        ->groupBy('conversation_id')
        ->limit(15)
        ->get();
    } elseif (!is_null($max) && is_integer($max)) {
      $meet_convs = MeetupRequestConversation::select('conversation_id')->whereHas('origin_meet_request', function (Builder $query) {
        $query->where([
          'deleted' => false,
          ['expires_at', '>', time()],
        ]);
      })
        ->where(function (Builder $query) {
          $query->orWhere([
            'sender_id' => $this->profile->profile_id,
            'receiver_id' => $this->profile->profile_id,
          ]);
        })
        ->where('id', '>', $max)
        ->with(['origin_meet_request'])
        ->orderBy('id', 'desc')
        ->groupBy('conversation_id')
        ->limit(15)
        ->get();
    } else {
      $meet_convs = MeetupRequestConversation::select('conversation_id')->whereHas('origin_meet_request', function (Builder $query) {
        $query->where([
          'deleted' => false,
          ['expires_at', '>', time()],
        ]);
      })
        ->where(function (Builder $query) {
          $query->orWhere([
            'sender_id' => $this->profile->profile_id,
            'receiver_id' => $this->profile->profile_id,
          ]);
        })
        ->with(['origin_meet_request'])
        ->orderBy('id', 'desc')
        ->groupBy('conversation_id')
        ->limit(15)
        ->get();
    }

    if (count($meet_convs) < 1) {
      return response()->json([
        'errmsg' => 'No conversations',
        'status' => 404,
      ]);
    }

    foreach ($meet_convs as $meet_conv) {
      $meet_conv->makeVisible(['num_new_msg', 'partnermeetprofile']);
      $meet_conv['conv_list'] = $this->getConvsById($meet_conv->conversation_id, $meet_conv->partnermeetprofile->owner_id);
    }

    return response()->json([
      'message' => 'fetched',
      'meet_convs' => $meet_convs,
      'status' => 200,
    ]);
  }

  private function getConvsById($conv_id, $partner_id)
  {
    $userprofile = $this->profile;
    $partner = Profile::with('user')->firstWhere('profile_id', $partner_id);
    if (empty($conv_id) || is_null($partner)) {
      return null;
    }

    $meet_req_convs = MeetupRequestConversation::where('conversation_id', $conv_id)
      ->orderBy('id', 'desc')
      ->limit(10)
      ->get();

    $set_delivered = $this->setStatus($conv_id, $meet_req_convs->first()->id);

    if ($set_delivered) {
      FCMNotification::send([
        "to" => $partner->user->device_token,
        'priority' => 'high',
        'content-available' => true,
        'data' => [
          'nav_id' => 'MEETREQ_CONVS',
          'responseData' => [
            'type' => 'SET_FCM_MEET_CONV_TO_DELIVERED',
            'conv_id' => $conv_id,
            'payload' => ['min' => $meet_req_convs->first()->id],
          ],
        ],
      ]);
    }

    return $meet_req_convs;
  }

  /**
   * Fetch conversations by conversation by id and set to delievered
   *
   * @param  \Illuminate\Http\Request  $req
   * @return \Illuminate\Http\Response
   */
  public function getConvs(Request $req)
  {
    $userprofile = $this->profile;
    if (empty($req->conversation_id) || empty($req->request_id)) {
      return response()->json([
        'errmsg' => 'Missing values to continue',
        'status' => 400,
      ]);
    }
    $conv_id = $req->conversation_id;
    $min = $req->min;
    $max = $req->max;

    $meet_req = MeetupRequest::firstWhere([
      'request_id' => $req->request_id,
      ['expires_at', '>', time()],
    ]);

    if (!$meet_req) {
      return response()->json([
        'errmsg' => 'Meet request for this conversation has being deleted or expired',
        'status' => 400,
      ]);
    }

    if (!is_null($min) && is_integer($min)) {
      $convs = MeetupRequestConversation::where('conversation_id', $conv_id)
        ->where('id', '<', $min)
        ->orderBy('id', 'desc')
        ->limit(10)
        ->get();
    } elseif (!is_null($max) && is_integer($max)) {
      $convs = MeetupRequestConversation::where('conversation_id', $conv_id)
        ->where('id', '>', $max)
        ->orderBy('id', 'desc')
        ->limit(10)
        ->get();
    } else {
      $convs = MeetupRequestConversation::where('conversation_id', $conv_id)
        ->orderBy('id', 'desc')
        ->limit(10)
        ->get();
    }

    if (count($convs) < 1) {
      return response()->json([
        'errmsg' => 'No results',
        'status' => 400,
      ]);
    }

    $set_status = $this->setStatus($conv_id, $convs->first()->id);
    $partner = MeetupRequestConversation::with('sender_profile.user')
      ->firstWhere('receiver_id', $userprofile->profile_id);

    if ($set_status && $partner->sender_profile) {
      FCMNotification::send([
        "to" => $partner->sender_profile->user->device_token,
        'priority' => 'high',
        'content-available' => true,
        'data' => [
          'nav_id' => 'MEETREQ_CONVS',
          'responseData' => [
            'type' => 'SET_FCM_MEET_CONV_TO_DELIVERED',
            'conv_id' => $conv_id,
            'payload' => ['min' => $convs->first()->id],
          ],
        ],
      ]);
    }

    return response()->json([
      'conversation_id' => $conv_id,
      'origin_meet_request' => $meet_req,
      'convs' => $convs,
      'status' => 200,
    ]);
  }

  /**
   * Store a newly created resource in storage.
   *
   * @param  \Illuminate\Http\Request  $req
   * @return \Illuminate\Http\Response
   */
  public function store(Request $req)
  {
    $userprofile = $this->profile;
    $meet_reqid = $req->request_id;
    if (is_null($meet_reqid) || empty($meet_reqid)) {
      return response()->json([
        'errmsg' => 'something went wrong, please try again',
        'status' => 400,
      ]);
    }
    $meet_req = MeetupRequest::firstWhere([
      'request_id' => $meet_reqid,
      ['expires_at', '>', time()],
    ]);

    if (empty($userprofile->meetup_setting)) {
      return response()->json([
        'errmsg' => 'You need to set up your meet profile first',
        'status' => 400,
      ]);
    }
    if (!$meet_req) {
      return response()->json([
        'errmsg' => 'Meet request not found it maybe have expired or deleted',
        'status' => 400,
      ]);
    }

    $sent_conv = MeetupRequestConversation::firstWhere([
      'meet_request_id' => $meet_reqid,
      'sender_id' => $userprofile->profile_id,
    ]);

    $received_conv = MeetupRequestConversation::firstWhere([
      'meet_request_id' => $meet_reqid,
      'receiver_id' => $userprofile->profile_id,
    ]);

    if ($meet_req->requester_id == $userprofile->profile_id) {
      if (!$received_conv) {
        return response()->json([
          'errmsg' => 'you cant start a conversation on your own meet request',
          'status' => 400,
        ]);
      }
      $receiver_id = $received_conv->sender_id;
      $conversation_id = "{$meet_req->request_id}{$userprofile->profile_id}{$receiver_id}";
    } else {
      if ($sent_conv && !$received_conv) {
        return response()->json([
          'errmsg' => 'you have  already reacted to this request waiting for response',
          'status' => 400,
        ]);
      }
      $receiver_id = $meet_req->requester_id;
      $conversation_id = "{$meet_req->request_id}{$receiver_id}{$userprofile->profile_id}";
    }

    //check if auth user not muted
    $partner_meet_profile = !empty($received_conv) ? $received_conv->partnermeetprofile : null;
    if (
      $partner_meet_profile &&
      in_array($userprofile->profile_id, $partner_meet_profile->black_listed_arr)
    ) {
      return response()->json([
        'errmsg' => 'failed to send message meet profile has muted you',
        'status' => 400,
      ]);
    } elseif (in_array($receiver_id, $userprofile->meetup_setting->black_listed_arr)) {
      return response()->json([
        'errmsg' => 'failed to send message you have muted this meet profile',
        'status' => 400,
      ]);
    }

    $validate = Validator::make($req->all(), [
      'chat_msg' => 'sometimes|bail|required|between:1,300|string',
      'chat_pic' => 'sometimes|bail|required|mimes:jpg,jpeg,png,bmp,gif,svg,webp|max:8000000',
      'thumb_chat_pic' => 'sometimes|bail|required|mimes:jpg,jpeg,png,bmp,gif,svg,webp|dimensions:max_width=150,max_height=150',
    ]);
    if ($validate->fails()) {
      $errors = $validate->errors();
      return response()->json([
        'chat_msg_err' => $errors->first('chat_msg'),
        'chat_pic_err' => $errors->first('chat_pic'),
        'thumb_chat_pic_err' => $errors->first('thumb_chat_pic'),
      ]);
    }
    $data = $req->only('chat_msg');
    $chat_pic = $this->uploadConvPic();
    if (is_array($chat_pic) && count($chat_pic) > 0) {
      $data['chat_pic'] = json_encode($chat_pic);
    }
    if (count($data) < 1) {
      return response()->json([
        'errmsg' => 'Missing values to continue',
        'status' => 400,
      ]);
    }
    $sendconv = $meet_req->conversations()->create(
      array_merge($data, [
        'sender_id' => $userprofile->profile_id,
        'conversation_id' => $conversation_id,
        'receiver_id' => $receiver_id,
        'created_at' => time(),
        'updated_at' => time(),
      ])
    );
    $partner = Profile::with('user')->firstWhere('profile_id', $receiver_id);

    if (!$sendconv) {
      return response()->json([
        'errmsg' => 'Request failed please try again',
        'status' => 500,
      ]);
    }
    $l = '
          🌌';
    $t = '📷';


    $body_text = '';
    if (count($sendconv->chat_pic) > 0) {
      $body_text = '📷';
    }
    $body_text .= "{$sendconv->chat_msg}";
    if ($partner) {
      FCMNotification::send([
        "to" => $partner->user->device_token,
        'priority' => 'high',
        'data' => [
          'responseData' => [
            'type' => 'ADD_FCM_MEET_CONV',
            'conv_id' => $conversation_id,
            'payload' => $sendconv,
          ],
          "notification" => [
            "title" => $userprofile->meetup_setting->meetup_name,
            'largeIconUrl' => $userprofile->meetup_setting->meetup_avatar,
            "body" => $body_text,
          ],
        ],
      ]);
    }

    return response()->json([
      'message' => 'sent',
      'conv_id' => $conversation_id,
      'meet_request' => $meet_req,
      'partner_meet_profile' => $sendconv->fresh()->partnermeetprofile,
      'conv' => $sendconv,
      'status' => 200,
    ]);
  }

  public function setStatus($conversation_id, $min = null, $max = null, $status = "delivered")
  {
    if (empty($conversation_id) || (empty($min) && empty($max))) {
      return false;
    }
    $set_status = MeetupRequestConversation::where([
      'conversation_id' => $conversation_id,
      'receiver_id' => $this->profile->profile_id
    ]);
    if (!empty($min)) {
      $set_status = $set_status->where('id', '<=', $min);
    } elseif (!empty($max)) {
      $set_status = $set_status->where('id', '>=', $max);
    } else {
      return false;
    }
    return $set_status->where(
      ['status', '!=', $status],
      ['status', '!=', 'read'],
    )->update([
      'status' => $status,
      'updated_at' => time()
    ]);
  }



  /**
   * this method handling uploading of chat image
   *
   * @return []
   */
  public function uploadConvPic()
  {
    $profileid = $this->profile->profile_id;
    $images = [];
    $chatpic = request()->chat_pic;
    $thumbchatpic = request()->thumb_chat_pic;
    if (
      request()->hasFile('chat_pic') && request()->hasFile('thumb_chat_pic') && request()->file('chat_pic')->isValid() && request()->file('thumb_chat_pic')->isValid()
    ) {
      $chatpicext = $chatpic->extension();
      $thumbchatpicext = $thumbchatpic->extension();
      $uniqueid = rand(0, 73737);
      $chatpicfilename = "$profileid$uniqueid.$chatpicext";
      $thumbchatpicfilename = "$profileid$uniqueid.$thumbchatpicext";
      $chatpicpath = $chatpic->storeAs('images/uploads/meetreqconvpics', $chatpicfilename, 'publics');
      $thumbchatpicpath = $thumbchatpic->storeAs('images/uploads/thumbmeetreqconvpics', $thumbchatpicfilename, 'publics');
      if (!$chatpicpath || !$thumbchatpicpath) {
        return [];
      }
      $images = [
        //'chatpic' => url($chatpicpath),
        'size' => $chatpic->getSize(),
        //'thumbchatpic' => url($thumbchatpicpath),
        'chatpicpath' => $chatpicpath,
        'thumbchatpicpath' => $thumbchatpicpath,
      ];
    } //parent if statement
    return $images;
  }

  /**
   * public function to set conversation
   *
   * @param  \Illuminate\Http\Request  $req
   * @return \Illuminate\Http\Response
   */
  public function setConvStatus(Request $req)
  {
    $option = [
      '1',
      '2'
    ];

    if (!in_array($req->type, $option) || (empty($req->max)) && empty($req->min) || empty($req->conv_id)) {
      return response()->json([
        'errmsg' => 'Missing values to continue',
        'status' => 200,
      ]);
    }

    $status = $req->type == "1" ? "delievered" : "read";
    $partner = MeetupRequestConversation::with('sender_profile.user')
      ->firstWhere('receiver_id', $this->profile->profile_id);
    $set_status = $this->setStatus($req->conv_id, $req->max, $req->max, $status);

    if ($set_status && !empty($partner)  && !empty($partner->sender_profile)) {
      FCMNotification::send([
        "to" => $partner->sender_profile->user->device_token,
        'priority' => 'high',
        'content-available' => true,
        'data' => [
          'nav_id' => 'MEETREQ_CONVS',
          'responseData' => [
            'type' => $req->type == "1" ? 'SET_FCM_MEET_CONV_TO_DELIVERED' : 'SET_FCM_MEET_CONV_TO_READ',
            'conv_id' => $req->conv_id,
            'payload' => ['max' => $req->max, 'min' => $req->min]
          ],
        ],
      ]);
    }
    return response()->json([
      'message' => "done",
      'status' => 200,
    ]);
  }

  /**
   * Display the specified resource.
   *
   * @param  \App\MeetupRequestConversation  $meetupRequestConversation
   * @return \Illuminate\Http\Response
   */
  public function show(MeetupRequestConversation $meetupRequestConversation)
  {
    //
  }

  /**
   * Update the specified resource in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  \App\MeetupRequestConversation  $meetupRequestConversation
   * @return \Illuminate\Http\Response
   */
  public function update(Request $request, MeetupRequestConversation $meetupRequestConversation)
  {
    //
  }

  /**
   * Remove the specified resource from storage.
   *
   * @param  \App\MeetupRequestConversation  $meetupRequestConversation
   * @return \Illuminate\Http\Response
   */
  public function destroy(MeetupRequestConversation $meetupRequestConversation)
  {
    //
  }
}
