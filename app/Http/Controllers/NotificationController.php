<?php

namespace App\Http\Controllers;

use App\Post;
use App\PageVisits;
use App\Notification;
use App\PostComment;
use App\PostCommentReply;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    protected $user;
    protected $profile;
    protected $store_string;
    protected $muted_profiles_id = [];
    protected $user_blocked_profiles_id = [];

    /**
     *Instantiate a new controller instance.
     * @return void
     */
    public function __construct()
    {
        $this->middleware('jwt.verify');
        $this->middleware('app.verify')->except('update');
        $this->user = auth()->user();
        if (is_null($this->user)) {
            return;
        }
        $this->profile = $this->user->profile;
        if (!is_null($this->profile->profile_settings)) {
            $this->muted_profiles_id = $this->profile->profile_settings->muted_profiles;
            $this->user_blocked_profiles_id = $this->profile->profile_settings->blocked_profiles;
        }
        PageVisits::saveVisit('notification');
    }


    /**
     * Get auth user notifications
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $userprofile = $this->profile;
        if (request()->max) {
            $notes = $userprofile->notifications()
                ->where('id', '>', request()->max)
                ->where(['deleted' => false, 'is_mention' => false])
                ->with(['initiator_profile.user'])
                ->orderBy('created_at', 'desc')
                ->groupBy('linkmodel')
                ->limit(2)
                ->get();
        } elseif (request()->min) {
            $notes = $userprofile->notifications()
                ->where('id', '<', request()->min)
                ->where(['deleted' => false, 'is_mention' => false])
                ->with(['initiator_profile.user'])
                ->orderBy('created_at', 'desc')
                ->groupBy('linkmodel')
                ->limit(2)
                ->get();
        } else {
            $notes = $userprofile->notifications()
                ->with(['initiator_profile.user'])
                ->where(['deleted' => false, 'is_mention' => false])
                ->orderBy('created_at', 'desc')
                ->groupBy('linkmodel')
                ->limit(2)
                ->get();
        }

        $notes->each(function ($item, $index) {
            $this->addNoteProp($item);
        });

        if (count($notes) < 1) {
            return response()->json([
                'errmsg' => 'No notifications',
                'status' => 404,
            ]);
        }

        return response()->json([
            'message' => 'fetched',
            'notes' => $notes,
            'status' => 200,
        ]);
    }

    /**
     * Get auth user metions
     * 
     * @param  \Illuminate\Http\Request  $req
     * @return \Illuminate\Http\Response
     */
    public function getMentionsList(Request $req)
    {
        $userprofile = $this->profile;
        if (request()->max) {
            $mentions = $userprofile->notifications()
                ->where('id', '>', request()->max)
                ->where(['deleted' => false, 'is_mention' => true])
                ->whereNotIn('type', ['pchatmention', 'meetchatmention'])
                ->with(['initiator_profile.user'])
                ->orderBy('created_at', 'desc')
                ->groupBy('linkmodel')
                ->limit(2)
                ->get();
        } elseif (request()->min) {
            $mentions = $userprofile->notifications()
                ->where('id', '<', request()->min)
                ->where(['deleted' => false, 'is_mention' => true])
                ->whereNotIn('type', ['pchatmention', 'meetchatmention'])
                ->with(['initiator_profile.user'])
                ->orderBy('created_at', 'desc')
                ->groupBy('linkmodel')
                ->limit(2)
                ->get();
        } else {
            $mentions = $userprofile->notifications()
                ->with(['initiator_profile.user'])
                ->where(['deleted' => false, 'is_mention' => true])
                ->whereNotIn('type', ['pchatmention', 'meetchatmention'])
                ->orderBy('created_at', 'desc')
                ->groupBy('linkmodel')
                ->limit(2)
                ->get();
        }

        $mentions->each(function ($item, $index) {
            $this->addNoteProp($item);
        });

        if (count($mentions) < 1) {
            return response()->json([
                'errmsg' => 'No mentions',
                'status' => 404,
            ]);
        }

        return response()->json([
            'message' => 'fetched',
            'mentions' => $mentions,
            'status' => 200,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $req
     * @return \Illuminate\Http\Response
     */
    public function store(Request $req)
    {
        $save = Notification::saveNote($req->only('receipient_id', 'type', 'link'));
        if (!$save) {
            return response()->json([
                'errmsg' => 'could not complete request please try again',
                'status' => 500,
            ]);
        }
        return response()->json([
            'message' => 'success',
            'status' => 200,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $req
     * @return \Illuminate\Http\Response
     */
    public function storeMention(Request $req)
    {
        $save = Notification::makeMentions($req->mentions, $req->type, $req->link);
        if (!$save) {
            return response()->json([
                'errmsg' => 'could not complete request please try again',
                'status' => 500,
            ]);
        }
        return response()->json([
            'message' => 'success',
            'status' => 200,
        ]);
    }

    /**
     * Add properties to  notification object.
     *
     */
    public function addNoteProp($note)
    {
        if (empty($note) || is_null($note)) {
            return;
        }
        $note->related_count = Notification::where('linkmodel', $note->linkmodel)->where(['deleted' => false])->count() - 1;
        switch ($note->type) {
            case 'postlike':
                $note->post = Post::with(['profile.user'])->firstWhere('postid', $note->link);
                break;
            case 'postshare':
                $note->post = Post::with(['profile.user'])->firstWhere('postid', $note->link);
                break;
            case 'postcomment':
                $note->postcomment = PostComment::with(['owner_post.profile.user', 'profile.user'])->firstWhere('commentid', $note->link);
                break;
            case 'postcommentlike':
                $note->postcomment = PostComment::with(['owner_post.profile.user', 'profile.user'])->firstWhere('commentid', $note->link);
                break;
            case 'postcommentreply':
                $note->postcommentreply = PostCommentReply::with(['origin.profile.user', 'profile.user'])->firstWhere('replyid', $note->link);
                break;
            case 'postcommentreplylike':
                $note->postcommentreply = PostCommentReply::with(['origin.profile.user', 'profile.user'])->firstWhere('replyid', $note->link);
                break;
            default:
                # code...
                break;
        }
    }
    /**
     * Add properties to mention object.
     *
     */
    public function addMentionProp($mention)
    {
        if (empty($mention) || is_null($mention)) {
            return;
        }
        switch ($mention->type) {
            case 'postmention':
                $mention->post = Post::with(['profile.user'])->firstWhere('postid', $mention->link);
                break;
            case 'postcommentmention ':
                $mention->postcomment = PostComment::with(['owner_post.profile.user', 'profile.user'])->firstWhere('commentid', $mention->link);
                break;
            case 'postcommentreplymention':
                $mention->postcommentreply = PostCommentReply::with(['origin.profile.user', 'profile.user'])->firstWhere('replyid', $mention->link);
                break;
            default:
                break;
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Notification  $notification
     * @return \Illuminate\Http\Response
     */
    public function show(Notification $notification)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Notification  $notification
     * @return \Illuminate\Http\Response
     */
    public function edit(Notification $notification)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Notification  $notification
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Notification $notification)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Notification  $notification
     * @return \Illuminate\Http\Response
     */
    public function destroy(Notification $notification)
    {
        //
    }
}
