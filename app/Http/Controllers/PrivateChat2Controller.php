<?php

namespace App\Http\Controllers;

use App\FCMNotification;
use App\PrivateChat;
use App\PageVisits;
use App\Profile;
use App\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class PrivateChat2Controller extends Controller
{

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
        PageVisits::saveVisit('privatechat');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $min =  request()->min;
        $max = request()->max;

        $private_chat_list = PrivateChat::select('created_chatid')
            ->where(function (Builder $query) {
                $query->where([
                    'sender_id' => $this->profile->profile_id,
                    'sender_deleted' => false,
                ])->orWhere([
                    ['receiver_id', '=', $this->profile->profile_id],
                    ['receiver_deleted', '=', false],
                ]);
            });

        if (!is_null($min) && is_integer($min)) {
            $private_chat_list =  $private_chat_list
                ->where('id', '<', $min)
                ->orderBy('id', 'desc')
                ->groupBy('created_chatid')
                ->limit(10)
                ->get();
        } elseif (!is_null($max) && is_integer($max)) {
            $private_chat_list =  $private_chat_list
                ->where('id', '>', $max)
                ->orderBy('id', 'desc')
                ->groupBy('created_chatid')
                ->limit(10)
                ->get();
        } else {
            $private_chat_list =   $private_chat_list
                ->orderBy('id', 'desc')
                ->groupBy('created_chatid')
                ->limit(10)
                ->get();
        }

        if (count($private_chat_list) < 1) {
            return response()->json([
                'errmsg' => 'No results',
                'status' => 404
            ]);
        }
        $chat_list = [];
        $fcm_notes_list = [];

        foreach ($private_chat_list as $item) {
            $newitem = $this->getChats($item->created_chatid);
            if (count($newitem) > 0) {
                $chat_list[] = $newitem;
                if ($newitem['set_status']) {
                    $fcm_notes_list[] = [
                        'min' => $newitem['first_id'],
                        'created_chatid' => $newitem['created_chatid'],
                        'partnerprofile' => $newitem['partnerprofile'],
                        'status' => 'delivered'
                    ];
                }
            }
        }

        foreach ($fcm_notes_list as $item) {
            FCMNotification::send([
                "to" => $item['partnerprofile']->user->device_token,
                'priority' => 'high',
                'content-available' => true,
                'data' => [
                    'nav_id' => 'PRIVATECHATLIST',
                    'resdata' => [
                        'type' => 'SET_PRIVATECHAT_READ_STATUS',
                        'payload' => [$item],
                    ],
                ],
            ]);
        }

        return response()->json([
            'chatlist' => $chat_list,
            'count' => count($chat_list),
            'status' => 200
        ]);
    }

    /**
     * public function to get chats
     * 
     */
    public function getChats($created_chatid, $min = null, $max = null,  $limit = 30)
    {
        $chats = PrivateChat::where('created_chatid', $created_chatid)
            ->where(function (Builder $query) {
                $query->where([
                    'sender_id' => $this->profile->profile_id,
                    'sender_deleted' => false,
                ])->orWhere([
                    ['receiver_id', '=', $this->profile->profile_id],
                    ['receiver_deleted', '=', false],
                ]);
            });

        if (!is_null($min)) {
            $chats =  $chats->where('id', '<', $min);
        } elseif (!is_null($max)) {
            $chats = $chats->where('id', '>', $max);
        }

        $chats = $chats->orderBy('id', 'desc')->limit($limit)->get();
        if (count($chats) > 0) {
            $set_status =  $this->setChatStatus($created_chatid, $chats->first()->id);
        } else {
            return [];
        }
        return [
            'created_chatid' => $created_chatid,
            'set_status' => $set_status,
            'num_new_msg' => $chats->first()->num_new_msg,
            'first_id' => $chats->first()->id,
            'last_id' => $chats->last()->id,
            'partnerprofile' => $chats->first()->partnerprofile,
            'chats' => $chats
        ];
    }

    public function setChatStatus($created_chatid, $min = null, $max = null, $status = "delivered")
    {
        if (empty($created_chatid)) {
            return false;
        }

        if (!empty($min)) {
            return PrivateChat::where([
                'created_chatid' => $created_chatid,
                'receiver_id' => $this->profile->profile_id,
                'receiver_deleted' => false,
                ['id', '<=', $min]
            ])
                ->whereNotIn('read', ['true', $status])
                ->update([
                    'read' => $status,
                    'updated_at' => time()
                ]);
        } elseif (!empty($max)) {
            return PrivateChat::where([
                'created_chatid' => $created_chatid,
                'receiver_id' => $this->profile->profile_id,
                'receiver_deleted' => false,
                ['id', '>=', $max]
            ])
                ->whereNotIn('read', ['true', $status])
                ->update([
                    'read' => $status,
                    'updated_at' => time()
                ]);
        } else {
            return false;
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $req)
    {
        $userprofile = $this->profile->load('user');
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
        if ($req->receiver_id == $userprofile->profile_id) {
            return response()->json([
                'errmsg' => 'you can not send chat messages to yourself',
                'status' => 400,
            ]);
        }
        $receiver_profile = Profile::whereHas('user', function (Builder $query) {
            $query->where([
                'deleted' => false,
                'suspended' => false,
                'approved' => true,
            ]);
        })
            ->with('user')
            ->firstWhere("profile_id", $req->receiver_id);

        $chat = PrivateChat::select('created_chatid')->firstWhere(function (Builder $query) {
            $query->where([
                'sender_id' => $this->profile->profile_id,
                'receiver_id' => request()->receiver_id,
            ])->orWhere([
                ['sender_id', '=', request()->receiver_id],
                ['receiver_id', '=', $this->profile->profile_id],
            ]);
        });

        $chatid = is_null($chat) || empty($chat) ? "{$userprofile->profile_id}{$req->receiver_id}" : $chat->created_chatid;

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

        $data = $req->only('chat_msg');
        $chat_pic = $this->uploadChatPic();
        if (is_array($chat_pic) && count($chat_pic) > 0) {
            $data['chat_pics'] = json_encode($chat_pic);
        }
        if (count($data) < 1) {
            return response()->json([
                'errmsg' => 'Missing values to continue',
                'status' => 400,
            ]);
        }

        $data['created_chatid'] = $chatid;
        $data['sender_id'] = $this->profile->profile_id;
        $data['receiver_id'] = $req->receiver_id;
        $data['private_chatid'] = md5(rand(2452, 1632662727));
        $data['created_at'] = time();
        $data['updated_at'] = time();

        $new_chat = PrivateChat::create($data);
        if (!$new_chat) {
            return response()->json([
                'errmsg' => 'chat message not sent please try again',
                'status' => 500,
            ]);
        }
        $body_text = '';
        if (count($new_chat->chat_pics) > 0) {
            $body_text = '📷';
        }
        $body_text .= "{$new_chat->chat_msg}";

        FCMNotification::send([
            "to" => $receiver_profile->user->device_token,
            'priority' => 'high',
            'content-available' => true,
            'data' => [
                'nav_id' => 'PRIVATECHAT',
                'notification' => [
                    'identity' => "pchat{$new_chat->created_chatid}",
                    'id' => $new_chat->id,
                    'name' => 'PrivateChat',
                    'body' => $body_text,
                    'sender' => $userprofile->load('user'),
                    'note_id' => "{$new_chat->private_chatid}",
                ],
                'resdata' => [
                    'type' => 'SET_FCM_PRIVATECHAT',
                    'payload' => [$new_chat, $userprofile->load('user')],
                ],
            ],
        ]);
        Notification::makeMentions(['gidslab'], 'pchatmention', $new_chat->private_chatid);

        return response()->json([
            'message' => 'chat sent',
            'partnerprofile' => $receiver_profile,
            'created_chatid' => $new_chat->created_chatid,
            'private_chat' => $new_chat->refresh(),
            'status' => 200,
        ]);
    }

    /**
     * this method handling uploading of chat image
     *
     * @return []
     */
    public function uploadChatPic()
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
            $chatpicpath = $chatpic->storeAs('images/uploads/chatpics', $chatpicfilename, 'publics');
            $thumbchatpicpath = $thumbchatpic->storeAs('images/uploads/thumbchatpics', $thumbchatpicfilename, 'publics');
            if (!$chatpicpath || !$thumbchatpicpath) {
                return [];
            }
            $images = [
                'size' => $chatpic->getSize(),
                'chatpicpath' => $chatpicpath,
                'thumbchatpicpath' => $thumbchatpicpath,
            ];
        }
        return $images;
    }


    /**
     * Display the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function show()
    {
        $min =  request()->min;
        $max = request()->max;
        $created_chatid = request()->created_chatid;
        $partner_id = request()->partner_id;

        if (!$created_chatid) {
            if (!$partner_id) {
                return response()->json([
                    'errmsg' => 'chat not found',
                    'status' => 400,
                ]);
            }
            $created_chatid = PrivateChat::select('created_chatid')->where([
                'sender_id' => $this->profile->profile_id,
                'receiver_id' => $partner_id,
            ])
                ->orWhere([
                    ['sender_id', '=', $partner_id],
                    ['receiver_id', '=', $this->profile->profile_id]
                ])
                ->first();
            if (is_null($created_chatid)) {
                return response()->json([
                    'errmsg' => 'chat not found',
                    'status' => 400,
                ]);
            }
            $created_chatid = $created_chatid->created_chatid;
        }

        $chatlist = $this->getChats($created_chatid, $min, $max, 30);

        if (count($chatlist) < 1 || count($chatlist['chats']) < 1) {
            return response()->json([
                'errmsg' => 'chats not found',
                'status' => 400,
            ]);
        }
        if ($chatlist['set_status']) {
            $payload = ['created_chatid' => $created_chatid, 'status' => 'delivered'];
            if ($max) {
                $payload['max'] = $max;
            } else {
                $payload['min'] = $chatlist['first_id'];
            }
            FCMNotification::send([
                "to" => $chatlist['partnerprofile']->user->device_token,
                'priority' => 'high',
                'content-available' => true,
                'data' => [
                    'resdata' => [
                        'type' => 'SET_FCM_PRIVATECHAT_READ_STATUS',
                        'payload' => [$payload],
                    ],
                ],
            ]);
        }

        return response()->json([
            'message' => 'chat  found',
            'chatlistitem' => $chatlist,
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
        //return $this->profile;
        if (is_null($search_name) || empty($search_name)) {
            return response()->json([
                'errmsg' => 'missing values to continue',
                'status' => 400,
            ]);
        }
        if ($search_name[0] == "@") {
            $name = substr($search_name, 1);
            $chatlist = PrivateChat::where(function (Builder $query) use ($name) {
                $query->where('sender_id', $this->profile->profile_id);
                $query->whereHas('receiver_profile', function (Builder $query) use ($name) {
                    $query->whereHas('user', function (Builder $query) use ($name) {
                        $query->where('username', 'like', "%{$name}%");
                    });
                });
            })
                ->orWhere(function (Builder $query) use ($name) {
                    $query->where('receiver_id', $this->profile->profile_id);
                    $query->whereHas('sender_profile', function (Builder $query) use ($name) {
                        $query->whereHas('user', function (Builder $query) use ($name) {
                            $query->where('username', 'like', "%{$name}%");
                        });
                    });
                })
                ->groupBy('created_chatid')
                ->simplePaginate(20);
        } else {
            $chatlist = PrivateChat::where(function (Builder $query) use ($search_name) {
                $query->where('sender_id', $this->profile->profile_id);
                $query->whereHas('receiver_profile', function (Builder $query) use ($search_name) {
                    $query->where('profile_name', 'like', "%$search_name%");
                });
            })
                ->orWhere(function (Builder $query) use ($search_name) {
                    $query->where('receiver_id', $this->profile->profile_id);
                    $query->whereHas('sender_profile', function (Builder $query) use ($search_name) {
                        $query->where('profile_name', 'like', "%$search_name%");
                    });
                })
                ->groupBy('created_chatid')
                ->simplePaginate(20);
        }
        if (count($chatlist) < 1) {
            return response()->json([
                'errmsg' => 'No results',
                'status' => 404,
            ]);
        }
        $lists = [];
        foreach ($chatlist as $chatlistitem) {
            $lists[] = ['profile' => $chatlistitem->partnerprofile];
        }

        return response()->json([
            'message' => 'results found',
            'lists' => $lists,
            'next_url' => $chatlist->nextPageUrl(),
            'status' => 200
        ]);
    }

    /**
     * public function to set chat to read
     *
     * @param  \Illuminate\Http\Request  $req
     * @return \Illuminate\Http\Response
     */
    public function setChatToRead(Request $req)
    {
        $created_chatid = $req->created_chatid;
        $min =  request()->min;
        $max = request()->max;
        $private_chat = PrivateChat::firstWhere('created_chatid', $created_chatid);
        $set_to_read = $this->setChatStatus($created_chatid, $min, $max, 'true');
        if (!$set_to_read) {
            return response()->json([
                'errmsg' => 'failed',
                'status' => 400
            ]);
        }

        $payload_arr = ['created_chatid' => $created_chatid, 'status' => 'true'];
        if ($min) {
            $payload_arr['min'] = $min;
        } else {
            $payload_arr['max'] = $max;
        }

        FCMNotification::send([
            "to" => $private_chat->partnerprofile->user->device_token,
            'priority' => 'high',
            'content-available' => true,
            'data' => [
                'nav_id' => 'PRIVATECHATLIST',
                'resdata' => [
                    'type' => 'SET_PRIVATECHAT_READ_STATUS',
                    'payload' => [$payload_arr],
                ],
            ],
        ]);

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
            'message' => 'deleted',
            'status' => 200,
        ]);
    }

    /**
     * public function to delete all chats from a chatlis
     * 
     * @param  \Illuminate\Http\Request  $req
     * @return \Illuminate\Http\Response
     */
    public function deletePrivateChatList(Request $req)
    {
        $userprofile = $this->profile;
        $created_chatid = $req->created_chatid;
        $limiter = 0 + $req->limit_id;
        if (is_null($created_chatid) || empty($created_chatid) || !is_integer($limiter)) {
            return response()->json([
                'errmsg' => 'Missing values to continue',
                'status' => 400,
            ]);
        }
        $delete1 = PrivateChat::where([
            ['created_chatid', '=', $created_chatid],
            ['sender_id', '=', $userprofile->profile_id],
            ['id', '<=', $limiter],
        ])
            ->update([
                'sender_deleted' => true,
                'updated_at' => time(),
            ]);

        $delete2 = PrivateChat::where([
            ['created_chatid', '=', $created_chatid],
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
     * to get all info about a private chat between user and another
     *
     * @param  \Illuminate\Http\Request  $req
     * @return \Illuminate\Http\Response
     */
    public function getAPrivateChatInfo(Request $req)
    {
        $userprofile = $this->profile;
        $created_chatid = $req->created_chatid;
        $chat = PrivateChat::where('created_chatid', $created_chatid)
            ->where(function (Builder $query) {
                $query->where([
                    'sender_id' => $this->profile->profile_id,
                    'sender_deleted' => false,
                ])->orWhere([
                    ['receiver_id', '=', $this->profile->profile_id],
                    ['receiver_deleted', '=', false],
                ]);
            })->first();

        if (is_null($chat) || empty($chat)) {
            return response()->json([
                'errmsg' => 'Missing values to continue',
                'status' => 400,
            ]);
        }
        $totalchats = $chat->related_chats()->count();
        $yoursentchats = $chat->related_chats()
            ->where('sender_id', $userprofile->profile_id)->count();
        $othersentchats = $chat->related_chats()
            ->where('receiver_id', $userprofile->profile_id)->count();
        $peryoursentchat = round(($yoursentchats / $totalchats) * 100);
        $perothersentchats = round(($othersentchats / $totalchats) * 100);

        return response()->json([
            'message' => 'fetched',
            'status' => 200,
            'private_chatinfo' => [
                'init_date' => $chat->created_at,
                'totalchats' => $totalchats,
                'yoursentchats' => $yoursentchats,
                'partnersentchats' => $othersentchats,
                'peryoursentchat' => $peryoursentchat,
                'perothersentchats' => $perothersentchats,
            ],
        ]);
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
     * Remove the specified resource from storage.
     *
     * @param  \App\PrivateChat  $privateChat
     * @return \Illuminate\Http\Response
     */
    public function destroy(PrivateChat $privateChat)
    {
        //
    }
}
