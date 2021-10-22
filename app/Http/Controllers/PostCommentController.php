<?php

namespace App\Http\Controllers;

use App\Post;
use App\PostComment;
use App\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use App\PageVisits;

class PostCommentController extends Controller
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
    PageVisits::saveVisit('postcomment');
  }

  /**
  * Display a listing of the resource.
  *
  * @return \Illuminate\Http\Response
  */
  public function index() {
    //return [PostComment::truncate()];
    $userprofile = $this->profile;
    $postid = request()->postid;
    if (is_null($postid) || empty($postid)) {
      return response()->json([
        'errmsg' => 'cannot retrieve comments missing values to continue',
        'status' => 400,
      ]);
    }
    $post = Post::with('profile.user')->firstWhere([
      'postid' => $postid,
      'archived' => false,
      'deleted' => false,
    ]);
    if (is_null($post) || empty($post) || $post->profile->user->approved == false || $post->profile->user->deleted == true ||
      $post->profile->user->suspended == true || ($post->archived == true && $userprofile->profile_id != $post->poster_id)) {
      return response()->json([
        'errmsg' => 'owning post not found',
        'status' => 404,
      ]);
    }
    if (Gate::allows('others_verify_block_status', $post->profile)) {
      return response()->json([
        'errmsg' => 'cannot show post post owner has you blocked',
        'status' => 412,
      ]);
    }
    $comments = PostComment::whereHas('profile', function (Builder $query) {
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
      'postid' => $postid,
      'deleted' => false,
    ])
    ->orderBy('id', 'desc')
    ->simplePaginate(20);

    if (count($comments) < 1) {
      return response()->json([
        'errmsg' => 'No comments yet',
        'status' => 404,
      ]);
    }

    return response()->json([
      'message' => 'comments found',
      'ownerpost' => $post,
      'comments' => $comments->items(),
      'nextpageurl' => $comments->nextPageUrl(),
      'hiddens' => $post->comments()->where('hidden', true)->exists(),
      'status' => 302,
    ]);

  }

  /**
  * Store a newly created resource in storage.
  *
  * @param  \Illuminate\Http\Request  $request
  * @return \Illuminate\Http\Response
  */
  public function store(Request $request) {
    $userprofile = $this->profile;
    $user_muted_profiles = $this->muted_profiles;
    $user_blacklisted_posts = $this->blacklisted_posts;

    $validate = Validator::make($request->all(), [
      'postid' => 'bail|required|exists:posts,postid',
      'comment_text' => 'bail|required|string|between:1,300',
      //'anonymous' => 'bail|required|boolean'
    ]);

    if ($validate->fails()) {
      $errors = $validate->errors();
      $postiderr = $errors->first('postid');
      $comment_text_err = $errors->first('comment_text');
      $anonymouserr = $errors->first('anonymous');

      return response()->json([
        'errmsg' => [
          'postiderr' => $postiderr,
          'commenttexterr' => $comment_text_err,
          //'anonymouserr' => $anonymouserr
        ],
        'status' => 400,
      ]);
    }
    if (in_array($request->postid, $user_blacklisted_posts)) {
      return response()->json([
        'errmsg' => 'You cannot comment on a post you have blacklisted',
        'status' => 412,
      ]);
    }
    $ownerpost = Post::with('profile.user')->firstWhere([
      'postid' => $request->postid,
      'deleted' => false,
      'archived' => false,
    ]);
    if (is_null($ownerpost) || $ownerpost->profile->user->approved != true ||
      $ownerpost->profile->user->deleted == true || $ownerpost->profile->user->suspended == true

    ) {
      return response()->json([
        'errmsg' => 'Post to add comment not found',
        'status' => 404,
      ]);
    }
    if (Gate::allows('others_verify_block_status', $ownerpost->profile)) {
      return response()->json([
        'errmsg' => 'comment not posted post owner has you blocked',
        'status' => 412,
      ]);
    }
    if (Gate::allows('user_verify_block_status', $ownerpost->profile)) {
      return response()->json([
        'errmsg' => 'comment not posted you need to unblock post owner first',
        'status' => 412,
      ]);
    }

    $data = $request->only(
      'postid',
      'comment_text',
      'anonymous'
    );
    $data['commentid'] = '';
    $data['commenter_id'] = $userprofile->profile_id;
    $data['created_at'] = time();
    $data['updated_at'] = time();
    $comment = PostComment::create($data);
    if (!$comment) {
      return response()->json([
        'errmsg' => 'could not post comment please try again',
        'status' => 500,
      ]);
    }
    if ($ownerpost->profile->profile_id != $userprofile->profile_id) {
      Notification::saveNote([
        'receipient_id' => $ownerpost->profile->profile_id,
        'type' => 'postcomment',
        'link' => $comment->commentid
      ]);
    }
    /* if($ownerpost->num_post_comments < 0){
        $ownerpost->num_post_comments = 0;
        }
        $ownerpost->num_post_comments = ++$ownerpost->num_post_comments;
        $ownerpost->save();*/
    return response()->json([
      'message' => 'comment posted',
      'ownerpost' => $ownerpost,
      'comment' => $comment->fresh('profile.user'),
      'status' => 201,
    ]);
  }

  /**
  * Display the specified resource.
  *
  *  @param  \App\\Illuminate\Http\Request $request
  * @return \Illuminate\Http\Response
  */
  public function show(Request $request) {
    $commentid = $request->commentid;
    $userprofile = $this->profile;
    if (empty($commentid) || is_null($commentid)) {
      return response()->json([
        'errmsg' => 'Missing values to continue',
        'status' => 400,
      ]);
    }
    $comment = PostComment::with(['owner_post', 'profile.user'])->firstWhere([
      'commentid' => $commentid,
      'deleted' => false,
    ]);
    if (is_null($comment) || ($comment->hidden == true && $comment->commenter_id != $userprofile->profile_id) || ($comment->hidden == true && $comment->owner_post->poster_id != $userprofile->profile_id) || $comment->profile->user->approved != true || $comment->profile->user->deleted == true || $comment->profile->user->suspended == true) {
      return response()->json([
        'errmsg' => 'Cannot find comment',
        'status' => 404,
      ]);
    }
    if (Gate::allows('others_verify_block_status', $comment->profile)) {
      return response()->json([
        'errmsg' => 'Postcomment owner has you blocked',
        'status' => 412,
      ]);
    }

    if ($comment->anonymous) {
      $comment['profile'] = null;
      $comment['user'] = null;
    }
    $ownerpost = $comment->owner_post;
    return response()->json([
      'message' => 'comment found',
      'owner_post' => $ownerpost->fresh('profile.user'),
      'comment' => $comment,
      /*'blockmsg' => Gate::allows('user_verify_block_status', $comment->profile) ?
      "You blocked {$comment->profile->user->username}" : null,*/
      'status' => 302,
    ]);

  }
  /**
  * public function to handle liking/disliking of comment starts here
  *
  * @param  \Illuminate\Http\Request  $request
  *  @return \Illuminate\Http\Response
  */
  public function likeAction(Request $req) {
    $userprofile = $this->profile;
    $commentid = $req->commentid;
    if (empty($commentid) || is_null($commentid)) {
      return response()->json([
        'status' => 400,
        'errmsg' => 'Missing values to continue',
      ]);
    }
    $tolikeactionpostcomment = PostComment::with(['owner_post', 'profile.user'])
    ->firstWhere([
      'commentid' => $commentid,
      'deleted' => false,
    ]);
    if (is_null($tolikeactionpostcomment) || $tolikeactionpostcomment->profile->user->deleted == true ||
      $tolikeactionpostcomment->profile->user->suspended == true ||
      $tolikeactionpostcomment->profile->user->approved != true || ($tolikeactionpostcomment->hidden == true &&
        $tolikeactionpostcomment->commenter_id != $userprofile->profile_id) || ($tolikeactionpostcomment->hidden == true &&
        $tolikeactionpostcomment->owner_post->poster_id != $userprofile->profile_id)
    ) {
      return response()->json([
        'status' => 404,
        'errmsg' => 'like action failed,comment not found',
      ]);
    }

    $checklikestatus = $tolikeactionpostcomment->likes()
    ->firstWhere('liker_id', $userprofile->profile_id);

    if (is_null($checklikestatus) || empty($checklikestatus)) {
      /**ensure certain conditions are met before action are executed */
      if (Gate::allows('others_verify_block_status', $tolikeactionpostcomment->profile)) {
        return response()->json([
          'errmsg' => 'cannot perform action postcomment owner has you blocked',
          'status' => 412,
        ]);
      }
      if (Gate::allows('user_verify_block_status', $tolikeactionpostcomment->profile)) {
        return response()->json([
          'errmsg' => 'cannot perfom action you need to unblock postcomment owner first',
          'status' => 412,
        ]);
      }
      $likeaction = $tolikeactionpostcomment->likes()->create([
        'liker_id' => $userprofile->profile_id,
        'created_at' => time(),
        'updated_at' => time(),
      ]);
      if (!$likeaction) {
        return response()->json([
          'errmsg' => 'like action failed please try again',
          'status' => 500,
        ]);
      }
      $msg = "comment liked";
    } else {
      if (!$checklikestatus->delete()) {
        return response()->json([
          'errmsg' => 'unlike action failed please try again',
          'status' => 500,
        ]);
      }
      $msg = "comment unliked";
    }

    if ($tolikeactionpostcomment->profile->profile_id != $userprofile->profile_id) {
      if ($msg == "comment liked") {
        Notification::saveNote([
          'receipient_id' => $tolikeactionpostcomment->profile->profile_id,
          'type' => 'postcommentlike',
          'link' => $commentid
        ]);
      } else {
        Notification::deleteNote([
          'receipient_id' => $tolikeactionpostcomment->profile->profile_id,
          'type' => 'postcommentlike',
          'link' => $commentid
        ]);
      }
    }
    return response()->json([
      'message' => $msg,
      'comment' => $tolikeactionpostcomment,
      'status' => 200,
    ]);
  }

  /**
  * public function to hide comment for owner post
  *
  * @param  \Illuminate\Http\Request  $request
  * @return \Illuminate\Http\Response
  */
  public function hideCommentAction(Request $req) {
    $userprofile = $this->profile;
    $tohidecommentid = $req->commentid;
    if (is_null($tohidecommentid) || empty($tohidecommentid)) {
      return response()->json([
        'errmsg' => 'Missing values to continue',
        'status' => 400,
      ]);
    }
    $tohidecomment = PostComment::with('owner_post')->firstWhere([
      'commentid' => $tohidecommentid,
      'deleted' => false,
    ]);
    if (is_null($tohidecomment) || empty($tohidecomment)) {
      return response()->json([
        'errmsg' => 'Comment to hide not found it may have being deleted',
        'status' => 404,
      ]);
    }
    //user must be owner of post to hide
    if ($tohidecomment->owner_post->poster_id != $userprofile->profile_id) {
      return response()->json([
        'errmsg' => 'You are not allowed to hide this comment',
        'status' => 412,
      ]);
    }

    $hideaction = $tohidecomment->update([
      'hidden' => !$tohidecomment->hidden,
    ]);

    if (!$hideaction) {
      return response()->json([
        'errmsg' => 'could not perform action please try again',
        'status' => 500,
      ]);
    }
    //after changes have being saved determine message based on value of hidden property
    return response()->json([
      'message' => $tohidecomment->hidden ? 'comment hidden' : 'comment unhidden',
      'hidden' => $tohidecomment->hidden,
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
    $userprofile = $this->profile;
    $commentid = $req->commentid;
    if (is_null($commentid) || empty($commentid)) {
      return response()->json([
        'errmsg' => 'Missing values to continue',
        'status' => 400,
      ]);
    }
    $comment = PostComment::with(['owner_post', 'profile.user'])->firstWhere([
      'commentid' => $commentid,
      'deleted' => false,
    ]);
    if (is_null($comment) || empty($comment) || $comment->profile->user->deleted == true || $comment->profile->user->suspended == true || $comment->profile->user->approved != true || ($comment->hidden == true && $comment->owner_post->poster_id != $userprofile->profile_id) || ($comment->hidden == true && $comment->commenter_id != $userprofile->profile_id)
    ) {
      return response()->json([
        'errmsg' => 'not found',
        'status' => 404,
      ]);
    }
    $likers_list = $comment->likes()
    ->whereHas('profile', function (Builder $query) {
      $query->whereHas('user', function (Builder $query) {
        $query->where([
          'deleted' => false,
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
  * @param  \App\PostComment  $postComment
  * @return \Illuminate\Http\Response
  */
  public function update(Request $request, PostComment $postComment) {
    //
  }

  /**
  * sets deleted to true for post comments
  *
  *  @param  \Illuminate\Http\Request  $request
  * @return \Illuminate\Http\Response
  */
  public function destroy(Request $request) {

    $userprofile = $this->profile;
    $postcommentid = $request->postcommentid;
    if (empty($postcommentid) || is_null($postcommentid)) {
      return response()->json([
        'errmsg' => 'Missing values to continue',
        'status' => 400,
      ]);
    }
    $postcomment = PostComment::with('owner_post.profile.user')->firstWhere('commentid', $postcommentid);
    if (is_null($postcomment) || $postcomment->commenter_id != $userprofile->profile_id) {
      return response()->json([
        'errmsg' => 'Cannot delete comment please try again',
        'status' => 500,
      ]);
    }
    $delete_action = $postcomment->update(['deleted' => true]);
    if (!$delete_action) {
      return response()->json([
        'errmsg' => 'could not delete comment please try again',
        'status' => 500,
      ]);
    }
    if ($postcomment->owner_post->profile->profile_id != $userprofile->profile_id) {
      Notification::deleteNote([
        'receipient_id' => $postcomment->owner_post->profile->profile_id,
        'type' => 'postcomment',
        'link' => $postcomment->commentid
      ]);
    }
    $ownerpost = $postcomment->owner_post;
    return response()->json([
      'message' => 'comment deleted',
      'ownerpost' => $ownerpost->fresh('profile.user'),
      'status' => 200,
    ]);

  }

  /**
  * Remove the specified resource from storage.
  *
  *  @param  \Illuminate\Http\Request  $request
  * @return \Illuminate\Http\Response
  */
  public function destroyActual(Request $request) {
    /*
        # when you know your comment was dumb as fuck! :)
        # i am the code to erase it
         */
    $userprofile = $this->profile;
    $postcommentid = $request->postcommentid;
    if (empty($postcommentid) || is_null($postcommentid)) {
      return response()->json([
        'errmsg' => 'Missing values to continue',
        'status' => 400,
      ]);
    }
    $postcomment = PostComment::with('owner_post')->firstWhere('commentid', $postcommentid);
    if (is_null($postcomment) || $postcomment->commenter_id != $userprofile->profile_id) {
      return response()->json([
        'errmsg' => 'Cannot delete comment please try again',
        'status' => 500,
      ]);
    }
    if (!$postcomment->delete()) {
      return response()->json([
        'errmsg' => 'Cannot delete comment please try again',
        'status' => 500,
      ]);
    }
    $ownerpost = $postcomment->owner_post;
    return response()->json([
      'message' => 'comment deleted',
      'ownerpost' => $ownerpost->fresh('profile.user'),
      'status' => 200,
    ]);

  }
}
