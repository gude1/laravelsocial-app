<?php

namespace App\Http\Controllers;

use App\CreateChat;
use App\PageVisits;
use App\PrivateChat;
use App\Profile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class PrivateChatController extends Controller
{
    protected $user;
    protected $profile;
    protected $user_blocked_profiles_id = [];

    /**
     *Instantiate a new controller instance.
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
        PageVisits::saveVisit('privatechat');
    }

    /**
     * protected function to sort  according to id from lowest to highest in db
     */
    protected function idSort($data)
    {
        usort($data, function ($item1, $item2) {
            if (is_object($item1) && is_object($item2)) {
                return $item1->id - $item2->id;
            } elseif (is_array($item1) && is_array($item2)) {
                return $item1['id'] - $item2['id'];
            }
        });
        return $data;
    }

    /**
     * returns profile chat list
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $userprofile = $this->profile;
        $limiter = request('limiter');
        $black_list = request('black_list');
        $chat_list = null;
        if (is_array($limiter) && is_array($black_list) && count($limiter) == 2) {
            $max_limiter = $this->clean_input($limiter[0]);
            $min_limiter = $this->clean_input($limiter[1]);
            $sub = "select create_chatid from private_chats where id > '$max_limiter' and (sender_id = '{$userprofile->profile_id}' or receiver_id = '{$userprofile->profile_id}')";
            $chat_list = DB::table(DB::raw("private_chats"))
                ->select('create_chatid')
                ->whereNotIn('create_chatid', [DB::raw($sub)])
                ->whereNotIn('create_chatid', $black_list)
                ->where(function ($query) {
                    $query->where([
                        'sender_id' => $this->profile->profile_id,
                        'sender_deleted' => false,
                    ])->orWhere([
                        ['receiver_id', '=', $this->profile->profile_id],
                        ['receiver_deleted', '=', false],
                    ]);
                })
                ->orderBy('id', 'desc')
                ->groupBy('create_chatid')
                ->limit(10)
                ->get();
        } else {
            $sub = PrivateChat::orderBy('id', 'desc');
            $chat_list = DB::table(DB::raw("({$sub->toSql()})"))
                ->select('create_chatid')
                ->where(function ($query) {
                    $query->where([
                        'sender_id' => $this->profile->profile_id,
                        'sender_deleted' => false,
                    ])->orWhere([
                        ['receiver_id', '=', $this->profile->profile_id],
                        ['receiver_deleted', '=', false],
                    ]);
                })
                ->groupBy('create_chatid')
                ->orderBy('id', 'desc')
                ->limit(10)
                ->get();
        }
        if (count($chat_list) < 1) {
            return response()->json([
                'errmsg' => 'No chat list yet',
                'status' => 404,
            ]);
        }
        $each_chatid_chat_arr = [];
        //get related chats for each chat list
        foreach ($chat_list as $chatitem) {
            $fetchchat = $this->fetchChats($chatitem->create_chatid);
            $fetchchat['private_chats']->first()->makeVisible('num_new_msg');
            $each_chatid_chat_arr[] = [
                'create_chatid' => $chatitem->create_chatid,
                'partnerprofile' => $fetchchat['partnerprofile'],
                'chats' => $fetchchat['private_chats'],
                'last_fetch_arr' => $fetchchat['last_fetch_arr'],

            ];
        }
        return response()->json([
            'chatlist' => $each_chatid_chat_arr,
            'status' => 200,
        ]);
    }

    /**
     * cleans data
     */
    protected function clean_input($data)
    {
        if (empty($data) || is_null($data)) {
            return $data;
        }
        $data = trim($data);
        $data = strip_tags($data);
        $data = stripslashes($data);
        $data = htmlentities($data);
        return $data;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $userprofile = $this->profile;
        //CreateChat::truncate();
        //PrivateChat::truncate();
        $validate = Validator::make($request->all(), [
            'chat_msg' => 'sometimes|bail|required|between:1,300|string',
            'chat_pics' => 'sometimes|bail|required|array|min:1,max:1',
            'chat_pics*' => 'sometimes|bail|required|mimes:jpg,jpeg,png,bmp,gif,svg,webp|dimensions:max_width=1700,max_height=1700',
            'thumb_chat_pics' => 'sometimes|bail|required|array|min:1,max:1',
            'thumb_chat_pics*' => 'sometimes|bail|required|mimes:jpg,jpeg,png,bmp,gif,svg,webp|dimensions:max_width=100,max_height=100',
        ]);
        if ($validate->fails()) {
            $errors = $validate->errors();
            return response()->json([
                'chat_msg_error' => $errors->first('chat_msg'),
                'chat_pics_error' => $errors->first('chat_pics'),
                'chat_pics*_error' => $errors->first('chat_pics*'),
                'thumb_chat_pics_error' => $errors->first('thumb_chat_pics'),
                'thumb_chat_pics*_error' => $errors->first('thumb_chat_pics*'),
            ]);
        }
        if ($request->receiver_id == $userprofile->profile_id) {
            return response()->json([
                'errmsg' => 'you can not send chat messages to yourself',
                'status' => 400,
            ]);
        }
        $receiver_profile = Profile::whereHas('user', function (Builder $query) {
            $query->where([
                'deleted' => false,
                'approved' => true,
            ]);
        })
            ->with('user')
            ->firstWhere("profile_id", $request->receiver_id);

        if (is_null($receiver_profile) || empty($receiver_profile)) {
            return response()->json([
                'errmsg' => 'receipient profile not found,user might have deactivated ',
                'status' => 400,
            ]);
        }
        /** before allowing action check to make user certain conditions are met */
        if (Gate::allows('others_verify_block_status', $receiver_profile)) {
            return response()->json([
                'errmsg' => 'cannot perform action chat partner  has you blocked',
                'status' => 412,
            ]);
        }
        if (Gate::allows('user_verify_block_status', $receiver_profile)) {
            return response()->json([
                'errmsg' => 'cannot perform action you need to unblock chat partner first',
                'status' => 412,
            ]);
        }

        //create or find created_chat for receiver and userprofile
        $created_chat = CreateChat::where([
            'profile_id1' => request()->receiver_id,
            'profile_id2' => $this->profile->profile_id,
        ])
            ->orWhere([
                ['profile_id1', '=', $this->profile->profile_id],
                ['profile_id2', '=', request()->receiver_id],
            ])
            ->first();

        //to set messages received by message sender
        if (is_null($created_chat) || empty($created_chat)) {
            $created_chat = CreateChat::create([
                'profile_id1' => $userprofile->profile_id,
                'profile_id2' => $request->receiver_id,
                'created_at' => time(),
                'updated_at' => time(),
                'chatid' => md5(rand(2452, 1632662727)),
                'profile_id1_lastvist' => time(),
            ]);
            $created_chat->refresh();
            if (!$created_chat) {
                return response()->json([
                    'errmsg' => 'chat not sent please try again',
                    'status' => 500,
                ]);
            }
        }
        //extra validation before inserting chat
        if ($request->missing('chat_pics') && $request->missing('chat_msg')) {
            return response()->json([
                'errmsg' => 'Missing values to continue',
                'status' => 400,
            ]);
        } elseif (count($request->only('chat_pics')) != count($request->only('thumb_chat_pics'))) {
            return response()->json([
                'errmsg' => 'chat_images and thumbnail_images count mismatch!',
                'status' => 400,
            ]);
        }
        $data = $request->only(
            'chat_msg',
            'receiver_id'
        );
        $uploadedchat_pics = $this->uploadChatPics();
        $uploadedchat_pics_len = count($uploadedchat_pics);
        $request_chat_pics_len = count($request->only('chat_pics'));
        $warningmsg = $uploadedchat_pics_len != $request_chat_pics_len ?
        "{$uploadedchat_pics_len}/{$request_chat_pics_len} uploaded" : null;
        $data['chat_pics'] = json_encode($uploadedchat_pics);
        $data['private_chatid'] = md5(rand(2452, 1632662727));
        $data['sender_id'] = $userprofile->profile_id;
        $data['created_at'] = time();
        $data['updated_at'] = time();
        //inserting into database
        $private_chat = $created_chat->private_chats()->create($data);

        if ($request->setread == "ok") {
            $set_to_read = null;
            if ($created_chat->profile_id1 == $userprofile->profile_id) {
                $set_to_read = $this->setChatArrayToRead(
                    json_decode($created_chat->profile_id1_last_fetch_arr, true),
                    true
                );
                if ($set_to_read == 200) {
                    $created_chat->update([
                        'profile_id1_last_fetch_arr' => json_encode([])
                    ]);
                }
            } else if ($created_chat->profile_id2 == $userprofile->profile_id) {
                $set_to_read = $this->setChatArrayToRead(
                    json_decode($created_chat->profile_id2_last_fetch_arr, true),
                    true
                );
                if ($set_to_read == 200) {
                    $created_chat->update([
                        'profile_id2_last_fetch_arr' => json_encode([])
                    ]);
                }
            }
        }

        if (!$private_chat) {
            return response()->json([
                'errmsg' => 'chat message not sent please try again',
                'status' => 500,
            ]);
        }
        //broadCast($private_chat->refresh(), 'newchatsent')->toOthers();
        return response()->json([
            'message' => 'chat sent',
            'create_chatid' => $private_chat->create_chatid,
            'partnerprofile' => $receiver_profile,
            'private_chat' => $private_chat->refresh(),
            'warning_msg' => $warningmsg,
            'status' => 200,
        ]);

    }

    /**
     * this method handling uploading of chat image
     *
     * @return []
     */
    public function uploadChatPics()
    {
        $profileid = $this->profile->profile_id;
        $images = [];
        $chatpics = request()->chat_pics;
        $thumbchatpics = request()->thumb_chat_pics;
        if (is_array($chatpics) &&
            count($thumbchatpics) > 0 &&
            request()->hasFile('chat_pics') &&
            is_array($thumbchatpics) &&
            count($thumbchatpics) > 0 &&
            request()->hasFile('thumb_chat_pics')
        ) {
            for ($num = 0; $num < count($chatpics); $num++) {
                if (!$chatpics[$num]->isValid() || !$thumbchatpics[$num]->isValid()) {
                    continue;
                }
                $chatpicext = $chatpics[$num]->extension();
                $thumbchatpicext = $thumbchatpics[$num]->extension();
                $uniqueid = rand(0, 73737);
                $chatpicfilename = "$profileid$uniqueid.$chatpicext";
                $thumbchatpicfilename = "$profileid$uniqueid.$thumbchatpicext";
                $chatpicpath = $chatpics[$num]->storeAs('images/uploads/chatpics', $chatpicfilename, 'publics');
                $thumbchatpicpath = $thumbchatpics[$num]->storeAs('images/uploads/thumbchatpics', $thumbchatpicfilename, 'publics');
                if (!$chatpicpath || !$thumbchatpicpath) {
                    continue;
                }
                $images[] = [
                    //'chatpic' => $chatpicpath,
                    'size' => $chatpics[$num]->getSize(),
                    //'thumbchatpic' => $thumbchatpicpath,
                    'chatpicpath' => $chatpicpath,
                    'thumbchatpicpath' => $thumbchatpicpath,
                ];
            } //for loop

        } //parent if statement
        return $images;
    }

    /**
     * Display the specified resource.
     *
     * @param  \Illuminate\Http\Request  $req
     * @return \Illuminate\Http\Response
     */
    public function show(Request $req)
    {
        //return CreateChat::all();
        $userprofile = $this->profile;
        $togetchatid = $req->create_chatid;
        if (is_null($togetchatid) || empty($togetchatid)) {
            return response()->json([
                'errmsg' => 'Missing values to continue',
                'status' => 400,
            ]);
        }
        $created_chat = CreateChat::orWhere([
            'profile_id1' => $userprofile->profile_id,
            'profile_id2' => $userprofile->profile_id,
        ])->where('chatid', $togetchatid)
            ->first();
        if (is_null($created_chat) || empty($created_chat)) {
            return response()->json([
                'errmsg' => 'chat not found',
                'status' => 400,
            ]);
        }
        $partnerprofile = $created_chat->profile_id1 == $userprofile->profile_id ?
        $created_chat->receipient_profile()->with('user')->first() : $created_chat->initiator_profile()->with('user')->first();

        $private_chats = $created_chat->private_chats()
            ->where(function (Builder $query) {
                $query->where([
                    'sender_id' => $this->profile->profile_id,
                    'sender_deleted' => false,
                ])->orWhere([
                    ['receiver_id', '=', $this->profile->profile_id],
                    ['receiver_deleted', '=', false],
                ]);
            })
            ->orderBy('id', 'desc')
            ->limit(100)
            ->get();
        if (count($private_chats) < 1) {
            return response()->json([
                'errmsg' => 'no chats yet',
                'status' => 404,
            ]);
        }
        //to update mes
        $to_set_delivered_ids = [];
        $to_set_last_fetch_arr = [];
        foreach ($private_chats as $item) {
            if ($item->receiver_id == $userprofile->profile_id) {
                if ($item->read == 'false') {
                    $to_set_delivered_ids[] = $item->id;
                }
                if ($item->read != 'true') {
                    $to_set_last_fetch_arr[] = $item->id;
                }

            }
        }
        //update reads to delivered  for chat received by userprofile
        $update_to_delivered = null;
        if (count($to_set_delivered_ids) > 0) {
            $update_to_delivered = PrivateChat::whereIn('id', $to_set_delivered_ids)
                ->update([
                    'read' => 'delivered',
                    'updated_at' => time(),
                ]);
        }
        //update createchat last_fetch_arr for  auth userprofile
        $update_last_fetch = null;
        if ($created_chat->profile_id1 == $userprofile->profile_id) {
            $to_set_last_fetch_arr = array_diff(
                $to_set_last_fetch_arr,
                json_decode($created_chat->profile_id1_last_fetch_arr, true),
            );
            $to_set_last_fetch_arr = array_merge(
                json_decode($created_chat->profile_id1_last_fetch_arr, true),
                $to_set_last_fetch_arr
            );
            $update_last_fetch = $created_chat->update([
                'profile_id1_last_fetch_arr' => json_encode($to_set_last_fetch_arr),
                'updated_at' => time(),
            ]);

        } else if ($created_chat->profile_id2 == $userprofile->profile_id) {
            $to_set_last_fetch_arr = array_diff(
                $to_set_last_fetch_arr,
                json_decode($created_chat->profile_id2_last_fetch_arr, true),
            );
            $to_set_last_fetch_arr = array_merge(
                json_decode($created_chat->profile_id2_last_fetch_arr, true),
                $to_set_last_fetch_arr
            );
            $update_last_fetch = $created_chat->update([
                'profile_id2_last_fetch_arr' => json_encode($to_set_last_fetch_arr),
                'updated_at' => time(),
            ]);
        }

        if ((!$update_to_delivered && count($to_set_delivered_ids) > 0) || !$update_last_fetch) {
            return response()->json([
                'errmsg' => 'could not fetch chat please try again',
                'status' => 500,
            ]);
        }
        broadcast($private_chats, 'updatechats')->toOthers();
        return response()->json([
            'message' => 'chat retrieved',
            'partnerprofile' => $partnerprofile,
            'private_chats' => $this->idSort(json_decode($private_chats, true)),
            'last_fetch_arr' => $to_set_last_fetch_arr,
            'status' => 200,
        ]);

    }

    /**
     * to display goods starts here
     */
    protected function fetchChats($create_chatid)
    {
        $userprofile = $this->profile;
        $togetchatid = $create_chatid;
        if (is_null($togetchatid) || empty($togetchatid)) {
            return [];
        }
        $created_chat = CreateChat::orWhere([
            'profile_id1' => $userprofile->profile_id,
            'profile_id2' => $userprofile->profile_id,
        ])->where('chatid', $togetchatid)
            ->first();
        if (is_null($created_chat) || empty($created_chat)) {
            return [
                'private_chats' => [],
                'last_fetch_arr' => [],
            ];
        }
        $partnerprofile = $created_chat->profile_id1 == $userprofile->profile_id ?
        $created_chat->receipient_profile()->with('user')->first() : $created_chat->initiator_profile()->with('user')->first();

        $private_chats = $created_chat->private_chats()
            ->where(function (Builder $query) {
                $query->where([
                    'sender_id' => $this->profile->profile_id,
                    'sender_deleted' => false,
                ])->orWhere([
                    ['receiver_id', '=', $this->profile->profile_id],
                    ['receiver_deleted', '=', false],
                ]);
            })
            ->orderBy('id', 'desc')
            ->limit(100)
            ->get();
        if (count($private_chats) < 1) {
            return [
                'private_chats' => [],
                'partnerprofile' => $partnerprofile,
                'last_fetch_arr' => [],
            ];
        }
        //to update mes
        $to_set_delivered_ids = [];
        $to_set_last_fetch_arr = [];
        foreach ($private_chats as $item) {
            if ($item->receiver_id == $userprofile->profile_id) {
                if ($item->read == 'false') {
                    $to_set_delivered_ids[] = $item->id;
                }
                if ($item->read != 'true') {
                    $to_set_last_fetch_arr[] = $item->id;
                }

            }
        }
        //update reads to delivered  for chat received by userprofile
        $update_to_delivered = null;
        if (count($to_set_delivered_ids) > 0) {
            $update_to_delivered = PrivateChat::whereIn('id', $to_set_delivered_ids)
                ->update([
                    'read' => 'delivered',
                    'updated_at' => time(),
                ]);
        }
        //update createchat last_fetch_arr for  auth userprofile
        $update_last_fetch = null;
        if ($created_chat->profile_id1 == $userprofile->profile_id) {
            $to_set_last_fetch_arr = array_diff(
                $to_set_last_fetch_arr,
                json_decode($created_chat->profile_id1_last_fetch_arr, true),
            );
            $to_set_last_fetch_arr = array_merge(
                json_decode($created_chat->profile_id1_last_fetch_arr, true),
                $to_set_last_fetch_arr
            );
            $update_last_fetch = $created_chat->update([
                'profile_id1_last_fetch_arr' => json_encode($to_set_last_fetch_arr),
                'updated_at' => time(),
            ]);

        } else if ($created_chat->profile_id2 == $userprofile->profile_id) {
            $to_set_last_fetch_arr = array_diff(
                $to_set_last_fetch_arr,
                json_decode($created_chat->profile_id2_last_fetch_arr, true),
            );
            $to_set_last_fetch_arr = array_merge(
                json_decode($created_chat->profile_id2_last_fetch_arr, true),
                $to_set_last_fetch_arr
            );
            $update_last_fetch = $created_chat->update([
                'profile_id2_last_fetch_arr' => json_encode($to_set_last_fetch_arr),
                'updated_at' => time(),
            ]);
        }

        if ((!$update_to_delivered && count($to_set_delivered_ids) > 0) || !$update_last_fetch) {
            return [
                'private_chats' => [],
                'partnerprofile' => $partnerprofile,
                'last_fetch_arr' => [],
            ];
        }

        return [
            'private_chats' => $private_chats,
            'partnerprofile' => $partnerprofile,
            'last_fetch_arr' => $to_set_last_fetch_arr,
        ];
    }

    /**
     * Display chat messages for chatid and also updated read
     *
     * @param  \Illuminate\Http\Request  $req
     * @return \Illuminate\Http\Response
     */
    public function showAndUpdateCreateChatRead(Request $req)
    {
        $userprofile = $this->profile;
        $togetchatid = $req->create_chatid;
        if ($req->findpartnerchat == 'true') {
            $togetchatid = CreateChat::where([
                'profile_id1' => $userprofile->profile_id,
                'profile_id2' => $req->partner_id,
            ])
                ->orWhere([
                    ['profile_id1', '=', $req->partner_id],
                    ['profile_id2', '=', $userprofile->profile_id],
                ])->first();
            if (!$togetchatid) {
                return response()->json([
                    'errmsg' => 'Could not fetch chats',
                    'status' => 400,
                ]);
            }
            $togetchatid = $togetchatid->chatid;
        }
        $offset = is_null($req->offset) || empty($req->offset) || !is_numeric($req->offset) ? 0 : $req->offset;
        $offset_operator = !in_array($req->operator, ['<', '>']) ? '<' : $req->operator;
        if (is_null($togetchatid) || empty($togetchatid)) {
            return response()->json([
                'errmsg' => 'Missing values to continue',
                'status' => 400,
            ]);
        }
        $created_chat = CreateChat::orWhere([
            'profile_id1' => $userprofile->profile_id,
            'profile_id2' => $userprofile->profile_id,
        ])->where('chatid', $togetchatid)
            ->first();
        if (is_null($created_chat) || empty($created_chat)) {
            return response()->json([
                'errmsg' => 'chat not found',
                'status' => 400,
            ]);
        }
        $partnerprofile = $created_chat->profile_id1 == $userprofile->profile_id ?
        $created_chat->receipient_profile()->with('user')->first() : $created_chat->initiator_profile()->with('user')->first();

        if ($offset < 1) {
            $private_chats = $created_chat->private_chats()
                ->where(function (Builder $query) {
                    $query->where([
                        'sender_id' => $this->profile->profile_id,
                        'sender_deleted' => false,
                    ])->orWhere([
                        ['receiver_id', '=', $this->profile->profile_id],
                        ['receiver_deleted', '=', false],
                    ]);
                })
                ->orderBy('id', 'desc')
                ->limit(100)
                ->get();
        } else {
            if ($offset_operator == "<") {
                $private_chats = $created_chat->private_chats()
                    ->where(function (Builder $query) {
                        $query->where([
                            'sender_id' => $this->profile->profile_id,
                            'sender_deleted' => false,
                        ])->orWhere([
                            ['receiver_id', '=', $this->profile->profile_id],
                            ['receiver_deleted', '=', false],
                        ]);
                    })
                    ->where('id', $offset_operator, $offset)
                    ->orderBy('id', 'desc')
                    ->limit(100)
                    ->get();
            } else {
                $private_chats = $created_chat->private_chats()
                    ->where(function (Builder $query) {
                        $query->where([
                            'sender_id' => $this->profile->profile_id,
                            'sender_deleted' => false,
                        ])->orWhere([
                            ['receiver_id', '=', $this->profile->profile_id],
                            ['receiver_deleted', '=', false],
                        ]);
                    })
                    ->where('id', $offset_operator, $offset)
                    ->limit(100)
                    ->get();
            }
        }
        if (count($private_chats) < 1) {
            return response()->json([
                'errmsg' => 'no chats yet',
                'status' => 404,
            ]);
        }
        //to update mes
        $to_set_delivered_ids = [];
        $to_set_last_fetch_arr = [];
        foreach ($private_chats as $item) {
            if ($item->receiver_id == $userprofile->profile_id) {
                if ($item->read == 'false') {
                    $to_set_delivered_ids[] = $item->id;
                }
                if ($item->read != 'true') {
                    $to_set_last_fetch_arr[] = $item->id;
                }

            }
        }
        //update reads to delivered  for chat received by userprofile
        $update_to_delivered = null;
        if (count($to_set_delivered_ids) > 0) {
            $update_to_delivered = PrivateChat::whereIn('id', $to_set_delivered_ids)
                ->update([
                    'read' => 'delivered',
                    'updated_at' => time(),
                ]);
        }

        //update read createchat last_fetch_arr and update createchat last_fetch_arr for  auth userprofile
        $update_last_fetch = null;
        if ($created_chat->profile_id1 == $userprofile->profile_id) {
            //code to update read of previous create chat last_fetch
            $update_last_fetch_read = $this->setChatArrayToRead(
                json_decode($created_chat->profile_id1_last_fetch_arr, true),
                true
            );

            if ($update_last_fetch_read == 200) {
                $to_set_last_fetch_arr = array_diff(
                    $to_set_last_fetch_arr,
                    json_decode($created_chat->profile_id1_last_fetch_arr, true),
                );
                $update_last_fetch = $created_chat->update([
                    'profile_id1_last_fetch_arr' => json_encode($to_set_last_fetch_arr),
                    'updated_at' => time(),
                ]);
            }

        } else if ($created_chat->profile_id2 == $userprofile->profile_id) {
            //code to update read of previous create chat last_fetch
            $update_last_fetch_read = $this->setChatArrayToRead(
                json_decode($created_chat->profile_id2_last_fetch_arr, true),
                true
            );

            if ($update_last_fetch_read == 200) {
                $to_set_last_fetch_arr = array_diff(
                    $to_set_last_fetch_arr,
                    json_decode($created_chat->profile_id2_last_fetch_arr, true),
                );
                $update_last_fetch = $created_chat->update([
                    'profile_id2_last_fetch_arr' => json_encode($to_set_last_fetch_arr),
                    'updated_at' => time(),
                ]);
            }
        }
        if ((!$update_to_delivered && count($to_set_delivered_ids) > 0) || !$update_last_fetch) {
            return response()->json([
                'errmsg' => 'could not fetch chat please try again',
                'status' => 500,
            ]);
        }

        return response()->json([
            'message' => 'chat retrieved',
            'create_chatid' => $togetchatid,
            'partnerprofile' => $partnerprofile,
            'private_chats' => $private_chats,
            'last_fetch_arr' => $to_set_last_fetch_arr,
            'status' => 200,
        ]);
    }

    /**
     * public function to set read to true for chat msgs sent to users
     *
     * @param  \Illuminate\Http\Request  $req
     * @return \Illuminate\Http\Response
     */
    public function setChatRead(Request $req)
    {
        $userprofile = $this->profile;
        $togetchatid = $req->create_chatid;
        if (is_null($togetchatid) || empty($togetchatid)) {
            return response()->json([
                'errmsg' => 'Missing values to continue',
                'status' => 400,
            ]);
        }
        $created_chat = CreateChat::orWhere([
            'profile_id1' => $userprofile->profile_id,
            'profile_id2' => $userprofile->profile_id,
        ])->where('chatid', $togetchatid)
            ->first();
        if (is_null($created_chat) || empty($created_chat)) {
            return response()->json([
                'errmsg' => 'chat not found',
                'status' => 400,
            ]);
        }
        $to_set_read_arr = [];
        $isprofileid1 = false;
        if ($created_chat->profile_id1 == $userprofile->profile_id) {
            $to_set_read_arr = is_null($created_chat->profile_id1_last_fetch_arr) ?
            [] : json_decode($created_chat->profile_id1_last_fetch_arr, true);
            $isprofileid1 = true;
        } else if ($created_chat->profile_id2 == $userprofile->profile_id) {
            $to_set_read_arr = is_null($created_chat->profile_id2_last_fetch_arr) ?
            [] : json_decode($created_chat->profile_id2_last_fetch_arr, true);
        }
        if (count($to_set_read_arr) < 1) {
            return response()->json([
                'message' => 'done',
                'set_to_read_arr' => $to_set_read_arr,
                'status' => 200,
            ]);
        }
        $setread = $created_chat->private_chats()
            ->whereIn('id', $to_set_read_arr)
            ->where([
                ['receiver_id', '=', $userprofile->profile_id],
            ])
            ->update([
                'read' => 'true',
                'updated_at' => time(),
            ]);
        if (!$setread) {
            return response()->json([
                'errmsg' => 'something went wrong please try again',
                'status' => 500,
            ]);
        }
        if ($isprofileid1) {
            $created_chat->update([
                'profile_id1_last_fetch_arr' => json_encode([]),
                'updated_at' => time(),
            ]);
        } else {
            $created_chat->update([
                'profile_id2_last_fetch_arr' => json_encode([]),
                'updated_at' => time(),
            ]);
        }
        return response()->json([
            'message' => 'done',
            'set_to_read_arr' => $to_set_read_arr,
            'status' => 200,
        ]);

    }

    /**
     * public function to set request chat array id to read
     * @param  \Illuminate\Http\Request  $req
     * @return \Illuminate\Http\Response
     */
    public function setReqChatArrayToRead(Request $req)
    {
        $to_set_read_arr = $req->chat_arr;
        if (!is_array($to_set_read_arr) || count($to_set_read_arr) < 1) {
            return response()->json([
                'errmsg' => 'Missing values to continue',
                'status' => 400,
            ]);
        }
        switch ($this->setChatArrayToRead($to_set_read_arr)) {
            case 200:
                return response()->json([
                    'message' => 'done',
                    'set_to_read_arr' => $to_set_read_arr,
                    'status' => 200,
                ]);
                break;
            case 400:
                return response()->json([
                    'errmsg' => 'You did something wrong',
                    'status' => 400,
                ]);
            case 500:
                return response()->json([
                    'errmsg' => 'something went wrong please try again',
                    'status' => 500,
                ]);
            default:
                return response()->json([
                    'errmsg' => 'You did something wrong',
                    'status' => 400,
                ]);
                break;
        }

    }

    /**
     * protected function to set chat array id to read
     *
     * @param Array $chat_arr
     * @param Boolean $allow_empty
     * @return
     */
    protected function setChatArrayToRead($chat_arr = [], $allow_empty = false)
    {
        //return [$chat_arr];
        if (count($chat_arr) < 1) {
            if ($allow_empty) {
                return 200;
            }
            return 400;
        }
        $setchatreadaction = PrivateChat::whereIn('id', $chat_arr)
            ->where('receiver_id', $this->profile->profile_id)
            ->update([
                'read' => 'true',
                'updated_at' => time(),
            ]);
        if (!$setchatreadaction) {
            return 500;
        } else {
            return 200;
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\PrivateChat  $privateChat
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, PrivateChat $privateChat)
    {
        //
    }
    /**
     * function to delete file from storage
     *
     */
    public function deleteFile($file)
    {
        if (Storage::exists($file)) {
            Storage::delete($file);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $req)
    {
        $userprofile = $this->profile;
        $todeleteprivate_chatid = $req->chatid;
        if (is_null($todeleteprivate_chatid) || empty($todeleteprivate_chatid)) {
            return response()->json([
                'errmsg' => 'Missing values to continue',
                'status' => 400,
            ]);
        }

        $private_chat = PrivateChat::orWhere([
            'sender_id' => $this->profile->profile_id,
            'receiver_id' => $this->profile->profile_id,
        ])
            ->where('private_chatid', $todeleteprivate_chatid)
            ->first();

        if (is_null($private_chat) || empty($private_chat)) {
            return response()->json([
                'errmsg' => 'chat not found',
                'status' => 404,
            ]);
        }
        $deleteaction = null;
        if ($private_chat->sender_id == $userprofile->profile_id && $private_chat->sender_deleted == false) {
            $deleteaction = $private_chat->update([
                'sender_deleted' => true,
                'updated_at' => time(),
            ]);
        } elseif ($private_chat->receiver_id == $userprofile->profile_id && $private_chat->receiver_deleted == false) {
            $deletedaction = $private_chat->update([
                'receiver_deleted' => true,
                'updated_at' => time(),
            ]);
        }
        if (!$deleteaction) {
            return response()->json([
                'errmsg' => 'could not delete chat please try again',
                'status' => 500,
            ]);
        }
        return response()->json([
            'message' => 'chat deleted',
            'status' => 200,
        ]);

    }

    /**
     * to set all chats from a private chat to deleted from the given id num and less
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function deletePrivateChatList(Request $req)
    {
        $userprofile = $this->profile;
        $create_chatid = $req->create_chatid;
        $limiter = 0 + $req->limit_id;
        if (is_null($create_chatid) || empty($create_chatid) || !is_integer($limiter)) {
            return response()->json([
                'errmsg' => 'Missing values to continue',
                'status' => 400,
            ]);
        }
        $delete1 = PrivateChat::where([
            ['create_chatid', '=', $create_chatid],
            ['sender_id', '=', $userprofile->profile_id],
            ['id', '<=', $limiter],
        ])
            ->update([
                'sender_deleted' => true,
                'updated_at' => time(),
            ]);

        $delete2 = PrivateChat::where([
            ['create_chatid', '=', $create_chatid],
            ['receiver_id', '=', $userprofile->profile_id],
            ['id', '<=', $limiter],
        ])
            ->update([
                'receiver_deleted' => true,
                'updated_at' => time(),
            ]);

        if (!$delete1 && !$delete2) {
            return response()->json([
                'errmsg' => 'something went wrong please try again',
                'status' => 500,
            ]);
        }

        return response()->json([
            'message' => 'done',
            'status' => 200,
        ]);

    }

    /**
     * to set a particular chat to deleted for user
     *
     * @param  \Illuminate\Http\Request  $req
     * @return \Illuminate\Http\Response
     */
    public function deleteAPrivateChat(Request $req)
    {
        $userprofile = $this->profile;
        $chatid = $req->chatid;
        if (is_null($chatid) || empty($chatid)) {
            return response()->json([
                'errmsg' => 'Missing values to continue',
                'status' => 400,
            ]);
        }
        $delete1 = PrivateChat::where([
            'private_chatid' => $chatid,
            'sender_id' => $userprofile->profile_id,
        ])
            ->update([
                'sender_deleted' => true,
                'updated_at' => time(),
            ]);

        $delete2 = PrivateChat::where([
            'private_chatid' => $chatid,
            'receiver_id' => $userprofile->profile_id,
        ])
            ->update([
                'receiver_deleted' => true,
                'updated_at' => time(),
            ]);

        if (!$delete1 && !$delete2) {
            return response()->json([
                'errmsg' => 'something went wrong please try again',
                'status' => 500,
            ]);
        }

        return response()->json([
            'message' => 'done',
            'status' => 200,
        ]);
    }

    /**
     * Public function to search for people authuser as private chatted
     *
     * @param  \Illuminate\Http\Request  $req
     * @return \Illuminate\Http\Response
     */
    public function searchPrivateChatList(Request $req)
    {
        $search_name = $req->name;
        $userprofile = $this->profile;
        if (is_null($search_name) || empty($search_name)) {
            return response()->json([
                'errmsg' => 'missing values to continue',
                'status' => 400,
            ]);
        }
        if ($search_name[0] == "@") {
            $chatlists = CreateChat::where(function (Builder $query) {
                $query->where('profile_id1', $this->profile->profile_id);
                $query->whereHas('receipient_profile', function (Builder $query) {
                    $query->whereHas('user', function (Builder $query) {
                        $name = substr(request()->name, 1);
                        $query->where('username', 'like', "%{$name}%");
                    });
                });
            })
                ->orWhere(function (Builder $query) {
                    $query->where('profile_id2', $this->profile->profile_id);
                    $query->whereHas('initiator_profile', function (Builder $query) {
                        $query->whereHas('user', function (Builder $query) {
                            $name = substr(request()->name, 1);
                            $query->where('username', 'like', "%{}%");
                        });
                    });
                })
                ->with([
                    'receipient_profile.user',
                    'initiator_profile.user',
                ])
                ->simplePaginate(20);
        } else {
            $chatlists = CreateChat::where(function (Builder $query) {
                $query->where('profile_id1', $this->profile->profile_id);
                $query->whereHas('receipient_profile', function (Builder $query) {
                    $name = request()->name;
                    $query->where('profile_name', 'like', "%$name%");
                });
            })
                ->orWhere(function (Builder $query) {
                    $query->where('profile_id2', $this->profile->profile_id);
                    $query->whereHas('initiator_profile', function (Builder $query) {
                        $name = request()->name;
                        $query->where('profile_name', 'like', "%$name%");
                    });
                })
                ->with([
                    'receipient_profile.user',
                    'initiator_profile.user',
                ])
                ->simplePaginate(20);
        }

        if (count($chatlists) > 1) {
            return response()->json([
                'errmsg' => 'No chats exists',
                'status' => 404,
            ]);
        }
        $lists = [];
        foreach ($chatlists as $chatitem) {
            if ($chatitem->profile_id1 == $userprofile->profile_id) {
                $lists[] = ['profile' => $chatitem->receipient_profile];
            } else if ($chatitem->profile_id2 == $userprofile->profile_id) {
                $lists[] = ['profile' => $chatitem->initiator_profile];
            }
        }

        return response()->json([
           'message' => 'results found',
           'lists' => $lists,
           'next_url' => $chatlists->nextPageUrl(),
           'status' => 200
        ]);

    }

    /**
     * to get all info about a private chat between user and another
     *
     * @param  \Illuminate\Http\Request  $req
     * @return \Illuminate\Http\Response
     */
    public function getAPrivateChatInfo(Request $req)
    {
        $userprofile = $this->profile;
        $create_chatid = $req->create_chatid;
        if (is_null($create_chatid) || empty($create_chatid)) {
            return response()->json([
                'errmsg' => 'Missing values to continue',
                'status' => 400,
            ]);
        }
        $created_chat = CreateChat::orWhere([
            'profile_id1' => $userprofile->profile_id,
            'profile_id2' => $userprofile->profile_id,
        ])->where('chatid', $create_chatid)
            ->first();
        if (is_null($created_chat) || empty($created_chat)) {
            return response()->json([
                'errmsg' => 'chat not found',
                'status' => 404,
            ]);
        }
        $totalchats = $created_chat->private_chats()->count();
        $yoursentchats = $created_chat->private_chats()
            ->where('sender_id', $userprofile->profile_id)->count();
        $othersentchats = $created_chat->private_chats()
            ->where('receiver_id', $userprofile->profile_id)->count();
        $peryoursentchat = round(($yoursentchats / $totalchats) * 100);
        $perothersentchats = round(($othersentchats / $totalchats) * 100);
        return response()->json([
            'message' => 'fetched',
            'status' => 200,
            'private_chatinfo' => [
                'init_date' => $created_chat->created_at,
                'totalchats' => $totalchats,
                'yoursentchats' => $yoursentchats,
                'partnersentchats' => $othersentchats,
                'peryoursentchat' => $peryoursentchat,
                'perothersentchats' => $perothersentchats,
            ],
        ]);

    }

}
