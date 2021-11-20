<?php

namespace App\Http\Controllers;

use App\PrivateChat;
use App\PageVisits;
use App\Profile;
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
        foreach ($private_chat_list as $item) {
            $chat_list[] = $this->getChats($item->created_chatid);
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
            $this->setChatStatus($created_chatid, $chats->first()->id);
        }
        return [
            'created_chatid' => $created_chatid,
            'num_new_msg' => $chats->first()->num_new_msg,
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
        $userprofile = $this->profile;
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
            $data['chat_pic'] = json_encode($chat_pic);
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

        return response()->json([
            'message' => 'chat sent',
            'partnerprofile' => $receiver_profile,
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
            $chatpicpath = $chatpic->storeAs('images/uploads/thumbchatpics', $chatpicfilename, 'publics');
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

        if (is_null($created_chatid) || empty($created_chatid)) {
            return response()->json([
                'errmsg' => 'chat not found',
                'status' => 400,
            ]);
        }

        $chatlist = $this->getChats($created_chatid, $max, $min, 30);

        if (count($chatlist['chats']) < 1) {
            return response()->json([
                'errmsg' => 'chats not found',
                'status' => 400,
            ]);
        }

        return response()->json([
            'message' => 'chat  found',
            'chats' => $chatlist['chats'],
            'count' => count($chatlist['chats']),
            'partnerprofile' =>  $chatlist['partnerprofile'],
            'status' => 200,
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

        $set_to_read = $this->setChatStatus($created_chatid, $min, $max, 'true');

        if (!$set_to_read) {
            return response()->json([
                'errmsg' => 'failed',
                'status' => 400
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
            'message' => 'deleted',
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
