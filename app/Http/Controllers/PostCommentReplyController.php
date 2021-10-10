<?php

namespace App\Http\Controllers;

use App\PostComment;
use App\PostCommentReply;
use App\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use App\PageVisits;

class PostCommentReplyController extends Controller
{
  protected $user;
  protected $profile;
  protected $blacklisted_posts = [];
  protected $muted_profiles = [];
  protected $blocked_profiles = [];
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
      $this->muted_profiles = $this->profile->profile_settings->muted_profiles;
      $this->blocked_profiles = $this->profile->profile_settings->blocked_profiles;
    }
    if (!is_null($this->profile->post_settings)) {
      $this->blacklisted_posts = $this->profile->post_settings->blacklisted_posts;
    }
    PageVisits::saveVisit('postcommentreply');
  }

  /**
  * Display a listing of the resource.
  *
  * @return \Illuminate\Http\Response
  */
  public function index() {
    $userprofile = $this->profile;
    $originid = request()->originid;
    if (is_null($originid) || empty($originid)) {
      return response()->json([
        'errmsg' => 'cannot retrieve replies missing values to continue',
        'status' => 400,
      ]);
    }
    $owner_comment = PostComment::with(['owner_post', 'profile.user'])->firstWhere([
      'commentid' => $originid,
      'deleted' => false,
    ]);
    if (is_null($owner_comment) || empty($owner_comment) || $owner_comment->profile->user->deleted == true || $owner_comment->profile->user->suspended == true || $owner_comment->profile->user->approved != true || ($owner_comment->hidden == true &&
      $owner_comment->owner_post->poster_id != $userprofile->profile_id) || ($owner_comment->hidden == true &&
      $owner_comment->commenter_id != $userprofile->profile_id)
    ) {
      return response()->json([
        'errmsg' => 'origin postcomment not found',
        'status' => 404,
      ]);
    }
    if (Gate::allows('others_verify_block_status', $owner_comment->profile)) {
      return response()->json([
        'errmsg' => 'cannot show reply reply owner has you blocked',
        'status' => 412,
      ]);
    }
    $replies = PostCommentReply::whereHas('profile', function (Builder $query) {
      $query->whereHas('user', function (Builder $query) {
        $query->where([
          'deleted' => false,
          'suspended' => false,
          'approved' => true,
        ]);
      });
      $query->whereHas('profile_settings', function (Builder $query) {
        $query->where('blocked_profiles', 'not like', "%{$this->profile->profile_id}%");
      });
      $query->orDoesntHave('profile_settings');
    })
    ->with('profile.user')
    ->where([
      'originid' => $originid,
      'deleted' => false,
    ])
    ->orderBy('id', 'desc')
    ->simplePaginate(20);

    if (count($replies) < 1) {
      return response()->json([
        'errmsg' => 'No replies yet',
        'status' => 404,
      ]);
    }

    return response()->json([
      'message' => 'replies found',
      'origin' => $owner_comment,
      'replies' => $replies->items(),
      'hiddens' => $owner_comment->replies()->where('hidden', true)->exists(),
      'nextpageurl' => $replies->nextPageUrl(),
      'status' => 302,
    ]);

  }
  /**
  * Store a newly created resource in storage.
  *
  * @param  \Illuminate\Http\Request  $request
  * @return \Illuminate\Http\Response
  */
  public function store(Request $req) {
    $userprofile = $this->profile;
    $originid = $req->originid;
    $reply_text = $req->reply_text;
    if (is_null($originid) || empty($originid) || is_null($reply_text) || empty($reply_text) ||
      strlen($reply_text) > 300) {
      return response()->json([
        'errmsg' => 'Missing values to continue',
        'status' => 400,
      ]);
    }
    $owner_comment = PostComment::with(['owner_post', 'profile.user'])->firstWhere([
      'commentid' => $originid,
      'deleted' => false,
    ]);
    if (is_null($owner_comment) || empty($owner_comment) || $owner_comment->profile->user->deleted == true || $owner_comment->profile->user->suspended == true || $owner_comment->profile->user->approved != true || ($owner_comment->hidden == true &&
      $owner_comment->owner_post->poster_id != $userprofile->profile_id) || ($owner_comment->hidden == true &&
      $owner_comment->commenter_id != $userprofile->profile_id)
    ) {
      return response()->json([
        'errmsg' => 'origin postcomment not found',
        'status' => 404,
      ]);
    }
    if (Gate::allows('others_verify_block_status', $owner_comment->profile)) {
      return response()->json([
        'errmsg' => 'cannot post reply postcomment owner has you blocked',
        'status' => 412,
      ]);
    }
    if (Gate::allows('user_verify_block_status', $owner_comment->profile)) {
      return response()->json([
        'errmsg' => 'cannot post reply you need to unblock postcomment owner',
        'status' => 412,
      ]);
    }
    $replyaction = $owner_comment->replies()->create([
      'replyid' => '',
      'replyer_id' => $userprofile->profile_id,
      'reply_text' => $reply_text,
      'created_at' => time(),
      'updated_at' => time(),
    ]);
    if (!$replyaction) {
      return response()->json([
        'errmsg' => 'could not post reply please try again',
        'status' => 500,
      ]);
    }

    if ($owner_comment->profile->profile_id != $userprofile->profile_id) {
      Notification::saveNote([
        'receipient_id' => $owner_comment->profile->profile_id,
        'type' => 'postcommentreply',
        'link' => $replyaction->replyid
      ]);
    }

    return response()->json([
      'message' => 'reply posted',
      'origin' => $owner_comment,
      'reply' => $replyaction->fresh('profile.user'),
      'status' => 201,
    ]);
  }

  /**
  * Display the specified resource.
  *
  * @param  \Illuminate\Http\Request  $request
  * @return \Illuminate\Http\Response
  */
  public function show(Request $req) {
    $replyid = $req->replyid;
    if (is_null($replyid) || empty($replyid)) {
      return response()->json([
        'errmsg' => 'Missing values to continue',
        'status' => 400,
      ]);
    }
    $reply = PostCommentReply::with(['profile.user'])->firstWhere([
      'replyid' => $replyid,
      'deleted' => false,
    ]);
    if (is_null($reply) || empty($reply) || $reply->profile->user->deleted == true ||
      $reply->profile->user->suspended == true ||
      $reply->profile->user->approved != true || ($reply->hidden == true &&
        $reply->origin->commenter_id != $userprofile->profile_id) || ($reply->hidden == true &&
        $reply->replyer_id != $userprofile->profile_id)
    ) {
      return response()->json([
        'errmsg' => 'reply not found',
        'status' => 404,
      ]);
    }
    if (Gate::allows('others_verify_block_status', $reply->profile)) {
      return response()->json([
        'errmsg' => 'cannot show reply replyowner has you blocked',
        'status' => 412,
      ]);
    }

    return response()->json([
      'message' => 'reply found',
      'blockmsg' => Gate::allows('user_verify_block_status', $reply->profile) ?
      "You blocked {$reply->profile->user->username}" : null,
      'reply' => $reply,
      'status' => 200,
    ]);
  }
  /**
  * public function to handle liking of reply starts here
  *
  * @param  \Illuminate\Http\Request  $request
  * @return \Illuminate\Http\Response
  */
  public function handleLikeAction(Request $req) {
    $userprofile = $this->profile;
    $tolikereplyid = $req->replyid;
    if (is_null($tolikereplyid) || empty($tolikereplyid)) {
      return response()->json([
        'errmsg' => 'Missing values to continue',
        'status' => 400,
      ]);
    }
    $tolikereply = PostCommentReply::with(['origin', 'profile.user'])->firstWhere([
      'replyid' => $tolikereplyid,
      'deleted' => false,
    ]);
    if (is_null($tolikereply) || empty($tolikereply) || $tolikereply->profile->user->deleted == true || $tolikereply->profile->user->approved != true || $tolikereply->profile->user->suspended == true || ($tolikereply->hidden == true &&
      $tolikereply->origin->commenter_id != $userprofile->profile_id) || ($tolikereply->hidden == true &&
      $tolikereply->replyer_id != $userprofile->profile_id)
    ) {
      return response()->json([
        'errmsg' => 'cannot find reply to like',
        'status' => 404,
      ]);
    }

    //dtermine wheteher to like or unlike reply
    $checklikestatus = $tolikereply->likes()->firstWhere('liker_id', $userprofile->profile_id);
    if (is_null($checklikestatus) || empty($checklikestatus)) {
      /**ensure certain conditions are met before performing action */
      if (Gate::allows('others_verify_block_status', $tolikereply->profile)) {
        return response()->json([
          'errmsg' => 'cannot perform action postcomment owner has you blocked',
          'status' => 412,
        ]);
      }
      if (Gate::allows('user_verify_block_status', $tolikereply->profile)) {
        return response()->json([
          'errmsg' => 'cannot perform action you need to unblock postcomment owner',
          'status' => 412,
        ]);
      }
      $likeaction = $tolikereply->likes()->create([
        'liker_id' => $userprofile->profile_id,
        'created_at' => time(),
        'updated_at' => time(),
      ]);
      if (!$likeaction) {
        return response()->json([
          'errmsg' => 'could not like reply please try again',
          'status' => 500,
        ]);
      }
      $msg = "Reply liked";
    } else {
      if (!$checklikestatus->delete()) {
        return response()->json([
          'errmsg' => 'could not like reply please try again',
          'status' => 500,
        ]);
      }
      $msg = "Reply unliked";
    }
    if ($tolikereply->profile->profile_id != $userprofile->profile_id) {
      if ($msg == "Reply liked") {
        Notification::saveNote([
          'receipient_id' => $tolikereply->profile->profile_id,
          'type' => 'postcommentreplylike',
          'link' => $tolikereplyid
        ]);
      } else {
        Notification::deleteNote([
          'receipient_id' => $tolikereply->profile->profile_id,
          'type' => 'postcommentreplylike',
          'link' => $tolikereplyid
        ]);
      }
    }

    return response()->json([
      'message' => $msg,
      'reply' => $tolikereply,
      'status' => 200,
    ]);
  }

  /**
  * public function to handle hidding/unhidding of reply
  *
  * @param  \Illuminate\Http\Request  $request
  * @return \Illuminate\Http\Response
  */
  public function hideReplyAction(Request $req) {

    $userprofile = $this->profile;
    $tohidereplyid = $req->replyid;
    if (is_null($tohidereplyid) || empty($tohidereplyid)) {
      return response()->json([
        'errmsg' => 'Missing values to continue',
        'status' => 400,
      ]);
    }
    $tohidereply = PostCommentReply::with(['origin', 'profile.user'])->firstWhere([
      'replyid' => $tohidereplyid,
      'deleted' => false,
    ]);
    if (is_null($tohidereply) || empty($tohidereply)) {
      return response()->json([
        'errmsg' => 'cannot find reply',
        'status' => 404,
      ]);
    }
    if ($tohidereply->origin->commenter_id != $userprofile->profile_id) {
      return response()->json([
        'errmsg' => 'You are not allowed to hide this reply',
        'status' => 412,
      ]);
    }
    $hideaction = $tohidereply->update([
      'hidden' => !$tohidereply->hidden,
    ]);
    if (!$hideaction) {
      return response()->json([
        'message' => 'could not hide reply please try again',
        'status' => 500,
      ]);
    }
    return response()->json([
      'message' => $tohidereply->hidden ? 'reply hidden' : 'reply unhidden',
      'hidden' => $tohidereply->hidden,
      'status' => 200,
    ]);

  }
  /**
  * public function to get likers list
  *
  * @param  \Illuminate\Http\Request  $request
  *  @return \Illuminate\Http\Response
  */
  public function getLikesList(Request $req) {
    $replyid = $req->replyid;
    if (is_null($replyid) || empty($replyid)) {
      return response()->json([
        'errmsg' => 'Missing values to continue',
        'status' => 400,
      ]);
    }
    $reply = PostCommentReply::with(['origin', 'profile.user'])->firstWhere([
      'replyid' => $replyid,
      'deleted' => false,
    ]);
    if (is_null($reply) || empty($reply) || $reply->profile->user->deleted == true ||
      $reply->profile->user->suspended == true ||
      $reply->profile->user->approved != true || ($reply->hidden == true &&
        $reply->origin->commenter_id != $userprofile->profile_id) || ($reply->hidden == true &&
        $reply->replyer_id != $userprofile->profile_id)
    ) {
      return response()->json([
        'errmsg' => 'not found',
        'status' => 404,
      ]);
    }
    $likers_list = $reply->likes()
    ->whereHas('profile', function (Builder $query) {
      $query->whereHas('user', function (Builder $query) {
        $query->where([
          'deleted' => false,
          'suspended' => false,
          'approved' => true,
        ]);
      });
      $query->whereHas('profile_settings', function (Builder $query) {
        $query->where('blocked_profiles', 'not like', "%{$this->profile->profile_id}%");
      });
      $query->orDoesntHave('profile_settings');
    })
    ->with('profile.user')
    ->orderBy('id', 'desc')
    ->simplePaginate(10);
    if (count($likers_list) < 1) {
      return response()->json([
        'errmsg' => 'No likes yet',
        'status' => 404,
      ]);
    }
    return response()->json([
      'message' => 'found',
      'status' => 200,
      'likes_list' => $likers_list->items(),
      'next_page_url' => $likers_list->nextPageUrl(),
    ]);
  }

  /**
  * Update the specified resource in storage.
  *
  * @param  \Illuminate\Http\Request  $request
  * @param  \App\PostCommentReply  $postCommentReply
  * @return \Illuminate\Http\Response
  */
  public function update(Request $request, PostCommentReply $postCommentReply) {
    //
  }

  /**
  * set deleted to true for postreplycomment
  *
  * @param  \Illuminate\Http\Request  $request
  * @return \Illuminate\Http\Response
  */
  public function destroy(Request $req) {
    $userprofile = $this->profile;
    $todeletereplyid = $req->replyid;
    if (is_null($todeletereplyid) || empty($todeletereplyid)) {
      return response()->json([
        'errmsg' => 'Missing values to continue',
        'status' => 400,
      ]);
    }
    $todeletereply = PostCommentReply::with('origin.profile.user')->firstWhere('replyid', $todeletereplyid);
    if (is_null($todeletereply) || $todeletereply->replyer_id != $userprofile->profile_id) {
      return response()->json([
        'errmsg' => 'Reply could not be deleted please try again',
        'status' => 500,
      ]);
    }
    $delete_action = $todeletereply->update(['deleted' => true]);
    if (!$delete_action) {
      return response()->json([
        'errmsg' => 'Cannot delete reply please try again',
        'status' => 500,
      ]);
    }
    if ($todeletereply->origin->profile->profile_id != $userprofile->profile_id) {
      Notification::deleteNote([
        'receipient_id' => $todeletereply->origin->profile->profile_id,
        'type' => 'postcommentreply',
        'link' => $todeletereply->replyid
      ]);
    }
    return response()->json([
      'message' => 'reply deleted',
      'origin' => $todeletereply->origin,
      'status' => 200,
    ]);

  }

  /**
  * Remove the specified resource from storage.
  *
  * @param  \Illuminate\Http\Request  $request
  * @return \Illuminate\Http\Response
  */
  public function destroyActual(Request $req) {
    $userprofile = $this->profile;
    $todeletereplyid = $req->replyid;
    if (is_null($todeletereplyid) || empty($todeletereplyid)) {
      return response()->json([
        'errmsg' => 'Missing values to continue',
        'status' => 400,
      ]);
    }
    $todeletereply = PostCommentReply::with('origin')->firstWhere('replyid', $todeletereplyid);
    if (is_null($todeletereply) || $todeletereply->replyer_id != $userprofile->profile_id) {
      return response()->json([
        'errmsg' => 'Reply could not be deleted please try again',
        'status' => 500,
      ]);
    }
    if (!$todeletereply->delete()) {
      return response()->json([
        'errmsg' => 'Cannot delete reply please try again',
        'status' => 500,
      ]);
    }
    return response()->json([
      'message' => 'reply deleted',
      'origin' => $todeletereply->origin,
      'status' => 200,
    ]);

  }
}