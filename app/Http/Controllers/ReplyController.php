<?php

namespace App\Http\Controllers;

use App\PostComment;
use App\Reply;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReplyController extends Controller
{
    protected $user;
    protected $profile;
    /**
     *Instantiate a new controller instance.
     * @return void
     */
    public function __construct()
    {
        $this->middleware('jwt.verify');
        $this->user = auth()->user();
        if (!is_null($this->user)) {
            $this->profile = $this->user->profile;
        } else {
            return;
        }
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $originid = request()->originid;
        if (is_null($originid) || empty($originid)) {
            return response()->json([
                'errmsg' => 'Missing values to continue',
                'status' => 400,
            ]);
        }

        $replies = Reply::with('profile.user')
            ->where('originid', $originid)
            ->simplePaginate(50);
        if (count($replies) < 1) {
            return response()->json([
                'errmsg' => 'No replies yet',
                'status' => 404,
            ]);
        }

        return response()->json([
            'message' => 'Replies found',
            'replies' => $replies->items(),
            'nextpageurl' => $replies->nextPageUrl(),
            'total' => $replies->count(),
            'status' => 302,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $originid = $request->originid;
        if (is_null($originid) || empty($originid)) {
            return response()->json([
                'errmsg' => 'Missing values to continue',
                'status' => 400,
            ]);
        }
        $validate = Validator::make($request->all(), [
            'reply_text' => 'bail|sometimes|required|string|between:3,125',
            'reply_image' => 'bail|sometimes|required|image|file',
        ]);
        if ($validate->fails()) {
            $errors = $validate->errors();
            $replytexterr = $errors->first('reply_text');
            $replyimageerr = $errors->first('reply_image');
            return response()->json([
                'errmsg' => [
                    'replytexteer' => $replytexterr,
                    'replyimageerr' => $replyimageerr,
                ],
                'status' => 400,
            ]);
        }
        $userprofile = $this->user->profile;
        $data = $request->only('reply_text', 'reply_image');
        if (count($data) < 1) {
            return response()->json([
                'errmsg' => 'Missing values to continue',
                'status' => 400,
            ]);
        }
        $data = array_merge($data, [
            'originid' => $originid,
            'replyid' => '',
            'replyerid' => $userprofile->profile_id,
        ]);

        $postcomment = PostComment::firstWhere('commentid', $originid);
        $orginreply = Reply::firstWhere('replyid', $originid);
        $origin = is_null($postcomment) ?
        is_null($orginreply) ? null : $orginreply
        : $postcomment;
        if (is_null($origin)) {
            return response()->json([
                'errmsg' => 'cannot make reply to a comment/post that doesnot exist,comment/post may have being deleted',
                'status' => 400,
            ]);
        }
        $numreplies = $origin->num_replies;
        $numreplies = ++$numreplies < 0 ? 0 : $numreplies;
        //we create the reply
        $reply = Reply::create($data);
        $reply->refresh();
        if (!$reply) {
            return response()->json([
                'errmsg' => 'something went wrong could not post reply please try again',
                'status' => 500,
            ]);
        }
        $origin->update([
            'num_replies' => $numreplies,
        ]);
        return response()->json([
            'message' => 'reply posted',
            'reply' => $reply,
            'status' => 201,
        ]);

    }

    /**
     * Display the specified resource.
     *
     *  @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {
        $replyid = $request->replyid;
        if (is_null($replyid) || empty($replyid)) {
            return response()->json([
                'errmsg' => 'Missing values to continue',
                'status' => 400,
            ]);
        }
        $reply = Reply::with('profile.user')->firstWhere('replyid', $replyid);
        if (is_null($reply) ||
            is_null($reply->profile) ||
            is_null($reply->profile->user)
        ) {
            return response()->json([
                'errmsg' => 'reply not found it may have being hidden or deleted',
                'status' => 404,
            ]);
        }
        if ($reply->anonymous) {
            $reply['profile'] = null;
            $reply['user'] = null;
        }
        return response()->json([
            'message' => 'Reply Found',
            'reply ' => $reply,
            'status' => 302,
        ]);

    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\ReplyPostComment  $replyPostComment
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, ReplyPostComment $replyPostComment)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request)
    {
        $profile = $this->profile;
        $replyid = $request->replyid;
        if (is_null($replyid) || empty($replyid)) {
            return response()->json([
                'errmsg' => 'Missing values to continue',
                'status' => 400,
            ]);
        }
        $todeletereply = Reply::firstWhere('replyid', $replyid);
        if (is_null($todeletereply) || $todeletereply->replyerid != $profile->profile_id) {
            return response()->json([
                'errmsg' => 'could not delete this reply',
                'status' => 400,
            ]);
        }
        $origin = $todeletereply->origin;
        $origin_num_replies = $origin->num_replies;
        $origin_num_replies = --$origin_num_replies < 0 ? 0 : $origin_num_replies;
        if (!$todeletereply->delete()) {
            return response()->json([
                'errmsg' => 'could not delete reply please try again',
                'status' => 500,
            ]);
        }
        $origin->update([
            'num_replies' => $origin_num_replies,
        ]);
        return response()->json([
            'errmsg' => 'reply deleted',
            'status' => 200,
        ]);

    }
}
