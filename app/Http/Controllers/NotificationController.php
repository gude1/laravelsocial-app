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
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $userprofile = $this->profile;
        if (request()->limiter) {
          $notes = $userprofile->notifications()
            ->where('id','<',request()->limiter)
            ->where(['deleted' => false])
            ->with(['initiator_profile.user', 'recipient_profile.user'])
            ->orderBy('created_at', 'desc')
            ->groupBy('linkmodel')
            ->limit(10)
            ->get();
        } else {
            $notes = $userprofile->notifications()
            ->with(['initiator_profile.user', 'recipient_profile.user'])
            ->where(['deleted' => false])
            ->orderBy('created_at', 'desc')
            ->groupBy('linkmodel')
            ->limit(10)
            ->get();
        }

        $notes->each(function($item,$index){
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
            'status' => '200',
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
        $save = Notification::saveNote($req->only('receipient_id','type','link'));
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
     * Display the specified resource.
     *
     * @param  \App\Notification  $notification
     */
    public function addNoteProp($note)
    {
      if(empty($note)  || is_null($note)){
         return;
      }
      $note->related_count = Notification::where('linkmodel',$note->linkmodel)->where(['deleted'=>false])->count() -1;
      switch ($note->type) {
          case 'postlike':
              $note->post = Post::with(['profile.user'])->firstWhere('postid',$note->link);
              break;
          case 'postcomment':
              $note->postcomment = PostComment::with(['owner_post.profile.user','profile.user'])->firstWhere('postcommentid',$note->link);
             break;
          case 'postcommentlike' :
              $note->postcomment = PostComment::with(['owner_post.profile.user','profile.user'])->firstWhere('postcommentid',$note->link);
            break;
        case 'postcommentreply':
            $note->postcommentreply = PostCommentReply::with(['origin.profile.user','profile.user'])->firstWhere('postcommentreplyid',$note->link);
            break;
            default:
              # code...
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
