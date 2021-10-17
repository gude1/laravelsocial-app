<?php

namespace App\Http\Controllers;

use App\Notification;
use App\Post;
use App\Profile;
use App\PageVisits;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Intervention\Image;

//to do stop plucking and paasing as array to the get post methods
class PostController extends Controller
{
  protected $user;
  protected $profile;
  protected $postsettings;
  protected $blacklisted_posts = [];
  protected $muted_profiles_id = [];
  protected $followed_profiles_id = [];
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
    $this->followed_profiles_id = json_decode($this->profile->followed_profiles->pluck('profile_id'), true);
    $this->postsettings = $this->profile->post_settings;
    if (!is_null($this->postsettings)) {
      $this->blacklisted_posts = $this->postsettings->blacklisted_posts;
    }
    if (!is_null($this->profile->profile_settings)) {
      $this->muted_profiles_id = $this->profile->profile_settings->muted_profiles;
      $this->user_blocked_profiles_id = $this->profile->profile_settings->blocked_profiles;
    }
    PageVisits::saveVisit('post');
  }

  /**
  * protected function to sort  according to id in db
  */
  protected function idSort($data) {
    usort($data, function ($item1, $item2) {
      return $item2->id - $item1->id;
    });
    return $data;
  }

  /**
  * function to get list of timelinepost for user
  *
  * @return \Illuminate\Http\Response
  */
  public function index() {
    //return Post::all();
    $userprofile = $this->profile;
    $postsettings = $userprofile->post_settings;
    $followedposts = $this->addKnownSharersProfile($this->getFollowedPosts());
    $withincampusposts = $this->addKnownSharersProfile($this->getWithinCampusPost());
    $generalposts = $this->addKnownSharersProfile($this->getAnyPost());
    //determine the range of post to return based on user preference
    if (is_null($postsettings) || empty($postsettings) ||
      is_null($postsettings->timeline_post_range) ||
      empty($postsettings->timeline_post_range) ||
      $postsettings->timeline_post_range == 'all'
    ) {
      $posts = array_merge($generalposts->items(), $followedposts->items());
      $posts = $this->idSort($posts);
      return response()->json([
        'message' => 'posts fetched',
        'postlistrange' => 'all',
        'timelineposts' => $posts,
        'generalpostnexturl' => $generalposts->nextPageUrl(),
        'followedpostnexturl' => $followedposts->nextPageUrl(),
      ]);

    } elseif ($postsettings->timeline_post_range == 'campus') {
      $posts = array_merge($followedposts->items(), $withincampusposts->items());
      $posts = $this->idSort($posts);
      return response()->json([
        'message' => 'posts fetched',
        'postlistrange' => 'campus',
        'timelineposts' => $posts,
        'withincampuspostsnexturl' => $withincampusposts->nextPageUrl(),
        'followedpostnexturl' => $followedposts->nextPageUrl(),
      ]);

    } elseif ($postsettings->timeline_post_range == 'followedpost') {
      return response()->json([
        'message' => 'posts fetched',
        'postlistrange' => 'followedpost',
        'timelineposts' => $followedposts->items(),
        'followedpostnexturl' => $followedposts->nextPageUrl(),
        'status' => 200,
      ]);

    } else {
      return response()->json([
        'errmsg' => 'error occured while fetching post',
        'status' => 500,
      ]);

    }
  }

  /**
  * function to return post settings of user
  *
  * @return \Illuminate\Http\Response
  */
  public function getPostSetting() {
    $postsettings = $this->profile->post_settings;
    if (is_null($postsettings) || empty($postsettings)) {
      return response()->json([
        'errmsg' => 'you dont have a post settings yet',
        'status' => 404,
      ]);
    }
    $postsettings->makeVisible(['timeline_post_range', 'blacklisted_posts']);
    return response()->json([
      'message' => 'found',
      'postsetting' => $postsettings,
      'status' => 200,
    ]);
  }

  /**
  * public function to update post_settings for user
  *
  * @param  \Illuminate\Http\Request  $req
  * @return \Illuminate\Http\Response
  */
  public function updatePostSetting(Request $req) {
    $timeline_post_range = $req->timeline_post_range;
    if (is_null($timeline_post_range) ||
      empty($timeline_post_range) ||
      !in_array($timeline_post_range, ['all', 'campus', 'followedpost'])
    ) {
      return response()->json([
        'errmsg' => 'Invalid value for post range',
        'status' => 400,
      ]);
    }
    $userprofile = $this->profile;
    $update = $userprofile->post_settings()->updateOrCreate(
      ['profile_id' => $userprofile->profile_id],
      ['timeline_post_range' => $timeline_post_range]
    );
    if (!$update) {
      return response()->json([
        'errmsg' => 'could not update post setting please try again',
        'status' => 500,
      ]);
    }
    return response()->json([
      'message' => 'update successful',
      'postsetting' => $update->makeVisible(['timeline_post_range', 'blacklisted_posts']),
      'status' => 200,
    ]);
  }

  /***
  * function to get post of profiles that user is following
  */
  public function getFollowedPosts() {
    $disallowedprofiles = array_merge(
      $this->muted_profiles_id,
      $this->user_blocked_profiles_id
    );
    return $this->profile->followed_profiles_posts()
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
    ->where([
      'archived' => false,
      'deleted' => false,
    ])
    ->whereNotIn('postid', $this->blacklisted_posts)
    ->with('profile.user')
    ->whereNotIn('poster_id', $disallowedprofiles)
    ->orderBy('id', 'desc')
    ->simplePaginate(5);
  }

  /***
  * function to get post by profiles from users campus
  */
  public function getWithinCampusPost($arr = []) {
    $arr = array_merge($arr, $this->blacklisted_posts);
    $disallowedprofiles = array_merge(
      $this->followed_profiles_id,
      $this->muted_profiles_id,
      $this->user_blocked_profiles_id
    );
    return Post::whereHas('profile', function (Builder $query) {
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
      $query->where('campus', $this->profile->campus);
    })
    ->where([
      'archived' => false,
      'deleted' => false,
    ])
    ->whereNotIn('postid', $arr)
    ->whereNotIn('poster_id', $disallowedprofiles)
    ->with('profile.user')
    ->orderBy('id', 'desc')
    ->simplePaginate(5);
  }

  /**
  * function to get post generally
  */
  public function getAnyPost($arr = []) {
    $disallowedprofiles = array_merge(
      $this->followed_profiles_id,
      $this->muted_profiles_id,
      $this->user_blocked_profiles_id
    );
    $arr = array_merge($arr, $this->blacklisted_posts);
    return Post::whereHas('profile', function (Builder $query) {
      $query->whereHas('user', function (Builder $query) {
        $query->where([
          'deleted' => false,
          'suspended' => false,
          'approved' => true,
        ]);
      });
      $query->WhereHas('profile_settings', function (Builder $query) {
        $query->where('blocked_profiles', 'not like', "%{$this->profile->profile_id}%");
      });
      $query->orDoesntHave('profile_settings');
    })
    ->where([
      'archived' => false,
      'deleted' => false,
    ])
    ->whereNotIn('postid', $arr)
    ->whereNotIn('poster_id', $disallowedprofiles)
    ->with('profile.user')
    ->orderBy('id', 'desc')
    ->simplePaginate(5);
  }
  /**
  * protected function by to get profiles you are following that shared a post
  */
  public function addKnownSharersProfile($data) {
    if (is_null($data) || empty($data)) {
      return $data;
    }
    foreach ($data as $post) {
      $post->known_sharers_profile = $post->post_sharers_profile()
      ->with('user')
      ->whereHas('user', function (Builder $query) {
        $query->where([
          'deleted' => false,
          'suspended' => false,
          'approved' => true,
        ]);
      })
      ->where(function (Builder $query) {
        $query->whereHas('profile_settings', function (Builder $query) {
          $query->where('blocked_profiles', 'not like', "%{$this->profile->profile_id}%");
        });
        $query->orDoesntHave('profile_settings');
      })
      ->whereHas('followers', function (Builder $query) {
        $query->where('profile_follower_id', $this->profile->profile_id);
      })->limit(2)->get();
    }
    return $data;
  }

  /**
  * Store a newly created resource in storage.
  *
  * @param  \Illuminate\Http\Request  $request
  * @return \Illuminate\Http\Response
  */
  public function store(Request $request) {
    $userprofile = $this->profile;
    $usernumpost = $userprofile->num_posts;
    $usernumpost = $usernumpost < 0 ? 0 : $usernumpost;
    $validate = Validator::make($request->all(), [
      'post_image' => 'required|array|min:1,max:7',
      'thumb_post_image' => 'required|array|min:1,max:7',
      'post_image.*' => 'required|mimes:jpg,jpeg,png,bmp,gif,svg,webp|dimensions:max_width=1500,max_height=1500',
      'thumb_post_image.*' => 'required|mimes:jpg,jpeg,png,bmp,gif,svg,webp|dimensions:max_width=150,max_height=150',
      'post_text' => 'bail|sometimes|required|string|between:1,140',
      'anonymous' => 'bail|required|boolean',
    ]);
    if ($validate->fails()) {
      $errors = $validate->errors();
      $errpostimage = $errors->first('post_image');
      $errpostimages = $errors->first('post_image.*');
      $errthumbpostimage = $errors->first('thumb_post_image');
      $errthumbpostimages = $errors->first('thumb_post_image.*');
      $errposttext = $errors->first('post_text');
      $erranonymous = $errors->first('anonymous');
      return response()->json([
        'errors' => [
          'errpostimage' => $errpostimage,
          'errpostimages' => $errpostimages,
          'errthumbpostimage' => $errthumbpostimage,
          'errthumbpostimages' => $errthumbpostimages,
          'errorposttext' => $errposttext,
          'erranonymous' => $erranonymous,
        ],
        'status' => 400,
      ]);
    }
    $post_image_length = count($request->post_image);
    $thumbnail_post_length = count($request->thumb_post_image);
    //check if postimages and thumbnails match
    if ($post_image_length != $thumbnail_post_length) {
      return response()->json([
        'errmsg' => 'Post images and thumbnails mismatch!',
        'status' => 400,
      ]);
    }
    //upload post images
    $postimages = $this->uploadPostPics();
    if (count($postimages) < 1) {
      return response()->json([
        'errmsg' => 'could not make post at this time please try again',
        'status' => 500,
      ]);
    }
    //upload post images  ends here
    $data = $request->only(
      'post_text',
      'anonymous'
    );
    $data['post_image'] = json_encode($postimages, true);
    $data['poster_id'] = $userprofile->profile_id;
    $data['postid'] = '';
    $data['anonymous'] = $data['anonymous'] ? true : false;
    $data['created_at'] = time();

    try {
      $post = Post::create($data);
      $post->refresh();
      $post->profile->user;
      $userprofile->update([
        'num_posts' => ++$usernumpost,
      ]);
      $dbpostimgcount = count($postimages);
      return response()->json([
        'message' => 'Posted',
        'post' => $post,
        'perror' => $post_image_length > $dbpostimgcount ? "$dbpostimgcount/$post_image_length images was posted" : null,
        'status' => 200,
      ]);
    } catch (Exception $e) {
      return response()->json([
        'errmsg' => 'could not upload post please try again',
        'status' => 500,
      ]);
    }
  }

  /**
  * this method handling uploading of profile image
  *
  * @return []
  */
  public function uploadPostPics() {
    $profileid = $this->profile->profile_id;
    $images = [];
    $postimage = request()->post_image;
    $thumbpostimage = request()->thumb_post_image;
    if (is_array($postimage) &&
      count($postimage) > 0 &&
      request()->hasFile('post_image') &&
      is_array($thumbpostimage) &&
      count($thumbpostimage) > 0 &&
      request()->hasFile('thumb_post_image')
    ) {
      for ($num = 0; $num < count($postimage); $num++) {
        if (!$postimage[$num]->isValid() || !$thumbpostimage[$num]->isValid()) {
          continue;
        }
        $postimageext = $postimage[$num]->extension();
        $thumbimageext = $thumbpostimage[$num]->extension();
        $uniqueid = rand(0, 73737);
        $postimagefilename = "$profileid$uniqueid.$postimageext";
        $thumbimagefilename = "$profileid$uniqueid.$thumbimageext";
        $postimagepath = $postimage[$num]->storeAs('images/uploads/postimages', $postimagefilename, 'publics');
        $thumbimagepath = $thumbpostimage[$num]->storeAs('images/uploads/thumbnailpostimages', $thumbimagefilename, 'publics');
        if (!$postimagepath || !$thumbimagepath) {
          continue;
        }
        $images[] = [
          //'postimage' => url($postimagepath),
          //'thumbnailpostimage' => url($thumbimagepath),
          'postimagepath' => $postimagepath,
          'thumbimagepath' => $thumbimagepath,
        ];
      } //for loop

    } //parent if statement
    return $images;
  }

  /**
  * Display the specified resource.
  *
  * @param  \App\\Illuminate\Http\Request $request
  * @return \Illuminate\Http\Response
  */
  public function show(Request $request) {
    $postid = $request->postid;
    $userprofile = $this->profile;
    if (empty($postid) || is_null($postid)) {
      return response()->json([
        'errmsg' => 'something went wrong please try again',
        'status' => 400,
      ]);
    }
    $post = Post::with('profile.user')->firstWhere([
      'postid' => $postid,
      'deleted' => false,
    ]);
    if (is_null($post) || is_null($post->profile) || is_null($post->profile->user) || $post->profile->user->deleted == true || $post->profile->user->suspended == true || $post->profile->user->approved != true || ($post->archived == true && $post->poster_id != $userprofile->profile_id)) {
      return response()->json([
        'errmsg' => 'could not find post it may have being removed,hidden or deleted',
        'status' => 500,
      ]);
    }
    if (Gate::allows('others_verify_block_status', $post->profile)) {
      return response()->json([
        'errmsg' => "Post owner ${$post->profile->user->username} has you blocked",
        'status' => 412,
      ]);
    }
    return response()->json([
      'message' => 'fetch successful',
      'blockmsg' => Gate::allows('user_verify_block_status', $post->profile) ?
      "{$post->profile->user->username} is blocked by you" : null,
      'postdetails' => $post,
      'status' => 200,
    ]);

  }

  /**
  * Update the specified resource in storage.
  *
  * @param  \Illuminate\Http\Request  $request
  * @return \Illuminate\Http\Response
  */
  public function update(Request $request) {}
  /**
  * public function to get all posts of a particular profile
  *
  * @param  \Illuminate\Http\Request  $request
  */
  public function getProfilePosts(Request $req) {
    $profileid = $req->profileid;
    $userprofile = $this->profile;
    if (is_null($profileid) || empty($profileid)) {
      return response()->json([
        'errmsg' => 'Missing values to continue',
        'status' => 400,
      ]);
    }
    $profile = Profile::whereHas('user', function (Builder $query) {
      $query->where([
        'deleted' => false,
        'suspended' => false,
        'approved' => true,
      ]);
    })
    ->with('user')
    ->firstWhere('profile_id', $profileid);
    if (is_null($profile) || empty($profile)) {
      return response()->json([
        'errmsg' => 'not found',
        'status' => 404,
      ]);
    }
    if ($userprofile->profile_id == $profile->profile_id) {
      $profile_posts = $profile->posts()->with('profile.user')
      ->where([
        'deleted' => false,
      ])
      ->orderBy('id', 'desc')
      ->simplePaginate(10);
    } else {
      if (Gate::allows('others_verify_block_status', $profile)) {
        return response()->json([
          'errmsg' => "$profile->user->username has you blocked",
          'status' => 412,
        ]);
      }
      $profile_posts = $profile->posts()->with('profile.user')
      ->where([
        'archived' => false,
        'deleted' => false,
      ])
      ->orderBy('id', 'desc')
      ->simplePaginate(10);
    }
    if (count($profile_posts) < 1) {
      return response()->json([
        'errmsg' => 'no posts',
        'status' => 404,
      ]);
    }
    return response()->json([
      'message' => 'found',
      'profile_posts' => $profile_posts->items(),
      'reqprofile' => $profile,
      'nextpageurl' => $profile_posts->nextPageUrl(),
      'status' => 200,
    ]);
  }

  /**
  * function to perform like action on post
  *
  * @param  \Illuminate\Http\Request  $request
  * @return \Illuminate\Http\Response
  */
  public function postLikeAction(Request $request) {
    $userprofile = $this->profile;
    $delete_repost = $create_repost = null;
    $postid = $request->postid;
    if (empty($postid) || is_null($postid)) {
      return response()->json([
        'errmsg' => 'Missing values to continue',
        'status' => 400,
      ]);
    }
    $post = Post::with('profile.user')->firstWhere([
      'postid' => $postid,
      'archived' => false,
      'deleted' => false,
    ]);
    if (is_null($post) || is_null($post->profile) || is_null($post->profile->user) || $post->profile->user->approved == false || $post->profile->user->deleted == true || $post->profile->user->suspended == true
    ) {
      return response()->json([
        'errmsg' => 'could not find post it may have being removed,hidden or deleted',
        'status' => 500,
      ]);
    }
    $checklikestatus = $post->postlikes()->firstWhere('liker_id', $userprofile->profile_id);

    if (is_null($checklikestatus)) {
      /** before allowing action check to make user certain conditions are met */
      if (Gate::allows('others_verify_block_status', $post->profile)) {
        return response()->json([
          'errmsg' => 'cannot perform action postowner has you blocked',
          'status' => 412,
        ]);
      }
      if (Gate::allows('user_verify_block_status', $post->profile)) {
        return response()->json([
          'errmsg' => 'cannot perform action you need to unblock post owner first',
          'status' => 412,
        ]);
      }
      $likeaction = $post->postlikes()->create([
        'liker_id' => $userprofile->profile_id,
      ]);
      $msg = "post liked";
    } else {
      $likeaction = $checklikestatus->delete();
      $msg = "post unliked";
    }
    if (!$likeaction) {
      return response()->json([
        'errmsg' => 'could not perform  action please try again',
        'status' => 500,
      ]);
    }
    if ($post->profile->profile_id != $userprofile->profile_id) {
      if ($msg == "post liked") {
        Notification::saveNote([
          'receipient_id' => $post->profile->profile_id,
          'type' => 'postlike',
          'link' => $post->postid
        ]);
      } else {
        Notification::deleteNote([
          'receipient_id' => $post->profile->profile_id,
          'type' => 'postlike',
          'link' => $post->postid
        ]);
      }
    }

    return response()->json([
      'message' => $msg,
      'postdetails' => $post,
      'status' => 200,
    ]);

  }

  /**
  * function to perform share action on post
  *
  * @param  \Illuminate\Http\Request  $request
  * @return \Illuminate\Http\Response
  */
  public function postShareAction(Request $request) {
    /*
        $repost_data = $post->fresh();
        $t = array_merge(
        $tocreate_repost->toArray(),
        [
        'sharer_id' => $userprofile->profile_id,
        'shared_at' => time(),
        'shared' => true,
        ],
        );
         */
    $userprofile = $this->profile;
    $tosharepostid = $request->postid;
    if (empty($tosharepostid) || is_null($tosharepostid)) {
      return response()->json([
        'errmsg' => 'Missing values to continue',
        'status' => 400,
      ]);
    }
    $post = Post::with('profile.user')->firstWhere([
      'postid' => $tosharepostid,
      'archived' => false,
      'deleted' => false,
    ]);
    if (is_null($post) || is_null($post->profile) || is_null($post->profile->user) || $post->profile->user->approved == false || $post->profile->user->deleted == true || $post->profile->user->suspended == true
    ) {
      return response()->json([
        'errmsg' => 'could not find post it may have being removed,hidden or deleted',
        'status' => 500,
      ]);
    }
    $checksharestatus = $post->postshares()->firstWhere('sharer_id', $userprofile->profile_id);
    if (is_null($checksharestatus)) {

      /** before allowing action check to make user certain conditions are met */
      if (Gate::allows('others_verify_block_status', $post->profile)) {
        return response()->json([
          'errmsg' => 'cannot perform action postowner has you blocked',
          'status' => 412,
        ]);
      }
      if (Gate::allows('user_verify_block_status', $post->profile)) {
        return response()->json([
          'errmsg' => 'cannot perform action you need to unblock post owner first',
          'status' => 412,
        ]);
      }
      $shareaction = $post->postshares()->create([
        'sharer_id' => $userprofile->profile_id,
      ]);
      $msg = "post shared";
    } else {
      $shareaction = $checksharestatus->delete();
      $msg = "post unshared";
    }
    if (!$shareaction) {
      return response()->json([
        'errmsg' => 'could not perform  action please try again',
        'status' => 500,
      ]);
    }

    if ($post->profile->profile_id != $userprofile->profile_id) {
      if ($msg == "post shared") {
        Notification::saveNote([
          'receipient_id' => $post->profile->profile_id,
          'type' => 'postshare',
          'link' => $post->postid
        ]);
      } else {
        Notification::deleteNote([
          'receipient_id' => $post->profile->profile_id,
          'type' => 'postshare',
          'link' => $post->postid
        ]);
      }
    }
    return response()->json([
      'message' => $msg,
      'postdetails' => $post,
      'status' => 200,
    ]);

  }

  /**
  * public function to delete file from storage
  */
  public function deleteFile($file) {
    if (File::exists($file)) {
      File::delete($file);
    }
  }

  /**
  * to archive users sepcified  post
  * when user archives a post only him/she can see it
  *
  * @param  \Illuminate\Http\Request  $request
  * @return \Illuminate\Http\Response
  */
  public function archivePostAction(Request $req) {
    $userprofile = $this->profile;
    $toarchivepostid = $req->postid;
    if (is_null($toarchivepostid) || empty($toarchivepostid)) {
      return response()->json([
        'errmsg' => 'Missing Values to continue',
        'status' => 400,
      ]);
    }
    $post = $userprofile->posts()->where('postid', $toarchivepostid)->first();
    if (is_null($post) || empty($post)) {
      return response()->json([
        'errmsg' => 'Post not found',
        'status' => 404,
      ]);
    }
    $archivevalue = $post->archived ? false : true;
    $archiveaction = $post->update([
      'archived' => $archivevalue,
    ]);
    if (!$archiveaction) {
      return response()->json([
        'errmsg' => 'operation failed please try again',
        'status' => 500,
      ]);
    } else {
      return $archivevalue ? response()->json([
        'message' => 'Post archived',
        'resetpost' => $userprofile->posts()->latest()->first(),
        'status' => 200,
      ])
      : response()->json([
        'message' => 'Post unarchived',
        'status' => 200,
      ]);
    }
  }

  /**
  * function to mute post from a particular profile
  * @param  \Illuminate\Http\Request  $request
  * @return \Illuminate\Http\Response
  */
  public function muteProfilePostAction(Request $req) {
    $userprofile = $this->profile;
    $tomuteprofileid = $req->profileid;
    if (is_null($tomuteprofileid) || empty($tomuteprofileid) ||
      $tomuteprofileid == $userprofile->profile_id) {
      return response()->json([
        'errmsg' => 'You did something wrong please try again',
        'status' => 400,
      ]);
    }
    $tomuteprofile = Profile::firstWhere('profile_id', $tomuteprofileid);
    if (is_null($tomuteprofile) || empty($tomuteprofile)) {
      return response()->json([
        'errmsg' => 'To be muted profile not  found',
        'status' => 404,
      ]);
    }

    if (is_null($userprofile->post_settings) ||
      empty($userprofile->post_settings) ||
      is_null($userprofile->post_settings->muted_profiles) ||
      empty($userprofile->post_settings->muted_profiles)
    ) {
      $muted_profiles = [];
    } else {
      $muted_profiles = $userprofile->post_settings->muted_profiles;
    }

    if (in_array($tomuteprofileid, $muted_profiles)) {
      $index = array_search($tomuteprofileid, $muted_profiles);
      unset($muted_profiles[$index]);
      $muted_profiles = array_values($muted_profiles);
      $msg = 'Posts from profile unmuted';
    } else {
      array_push($muted_profiles, $tomuteprofileid);
      $msg = 'Posts from profile muted';
    }
    $tomuteaction = $userprofile->post_settings()->updateOrCreate(
      ['profile_id' => $userprofile->profile_id],
      ['muted_profiles' => json_encode($muted_profiles)]
    );
    return (!$tomuteaction) ? response()->json([
      'errmsg' => 'Could not mute profile please try again',
      'status' => 500,
    ]) : response()->json([
      'message' => $msg,
      'status' => 200,
    ]);
  }

  /**
  * function to blacklist a particular post for the user
  *  @param  \Illuminate\Http\Request  $request
  * @return \Illuminate\Http\Response
  */
  public function blackListPostAction(Request $req) {
    $userprofile = $this->profile;
    $toblacklistpostid = $req->postid;
    if (is_null($toblacklistpostid) || empty($toblacklistpostid)) {
      return response()->json([
        'errmsg' => 'your request is incomplete',
        'status' => 400,
      ]);
    }
    if (is_null($userprofile->post_settings) ||
      empty($userprofile->post_settings) ||
      is_null($userprofile->post_settings->blacklisted_posts) ||
      empty($userprofile->post_settings->blacklisted_posts)
    ) {
      $blacklisted_posts = [];
    } else {
      $blacklisted_posts = $userprofile->post_settings->blacklisted_posts;
    }
    if (in_array($toblacklistpostid, $blacklisted_posts)) {
      $index = array_search($toblacklistpostid, $blacklisted_posts);
      unset($blacklisted_posts[$index]);
      $blacklisted_posts = array_values($blacklisted_posts);
      $msg = 'Blacklist tag removed';
    } else {
      array_push($blacklisted_posts, $toblacklistpostid);
      $msg = 'Post Blacklisted';
    }

    $toblacklistaction = $userprofile->post_settings()->updateOrCreate(
      ['profile_id' => $userprofile->profile_id],
      ['blacklisted_posts' => json_encode($blacklisted_posts)]
    );
    return (!$toblacklistaction) ? response()->json([
      'errmsg' => 'Could not blacklist post please try again later',
      'status' => 500,
    ]) : response()->json([
      'message' => $msg,
      'status' => 200,
    ]);

  }
  /**
  * @param  \Illuminate\Http\Request  $request
  * public function to get likes_list for post starts here
  */
  public function getPostLikesList(Request $req) {
    $postid = $req->postid;
    $userprofile = $this->profile;
    if (is_null($postid) || empty($postid)) {
      return response()->json([
        'errmsg' => 'Missing values to continue',
        'status' => 400,
      ]);
    }
    $post = Post::with('profile.user')->firstWhere([
      'postid' => $postid,
      'deleted' => false,
    ]);
    if (is_null($post) || empty($post) || $post->profile->user->approved == false || $post->profile->user->deleted == true || $post->profile->user->suspended == true || ($post->archived == true && $userprofile->profile_id != $post->poster_id)) {
      return response()->json([
        'errmsg' => 'post not found',
        'status' => 404,
      ]);
    }

    /** before allowing action check to make user certain conditions are met */
    if (Gate::allows('others_verify_block_status', $post->profile)) {
      return response()->json([
        'errmsg' => 'cannot perform action postowner has you blocked',
        'status' => 412,
      ]);
    }

    if ($userprofile->profile_id == $post->poster_id) {
      $likers_list = $post->postlikes()
      ->whereHas('profile', function (Builder $query) {
        $query->whereHas('user', function (Builder $query) {
          $query->where([
            'deleted' => false,
            'suspended' => false,
            'approved' => true,
          ]);
        });
      })
      ->with('profile.user')
      ->orderBy('id', 'desc')
      ->simplePaginate(10);
    } else {
      $likers_list = $post->postlikes()
      ->whereHas('profile', function (Builder $query) {
        $query->whereHas('user', function (Builder $query) {
          $query->where([
            'deleted' => false,
            'suspended' => false,
            'approved' => true,
          ]);
        });
        $query->where(function (Builder $query) {
          $query->whereHas('profile_settings', function (Builder $query) {
            $query->where('blocked_profiles', 'not like', "%{$this->profile->profile_id}%");
          });
          $query->orDoesntHave('profile_settings');
        });
      })
      ->with('profile.user')
      ->orderBy('id', 'desc')
      ->simplePaginate(10);
    }
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
  * @param  \Illuminate\Http\Request  $request
  * public function to get shares_list for post starts here
  */
  public function getPostSharesList(Request $req) {
    $postid = $req->postid;
    $userprofile = $this->profile;
    if (is_null($postid) || empty($postid)) {
      return response()->json([
        'errmsg' => 'Missing values to continue',
        'status' => 400,
      ]);
    }
    $post = Post::with('profile.user')->firstWhere([
      'postid' => $postid,
      'deleted' => false,
    ]);
    if (is_null($post) || empty($post) || $post->profile->user->approved == false || $post->profile->user->deleted == true || $post->profile->user->suspended == true || ($post->archived == true && $userprofile->profile_id != $post->poster_id)) {
      return response()->json([
        'errmsg' => 'post not found',
        'status' => 404,
      ]);
    }

    /** before allowing action check to make user certain conditions are met */
    if (Gate::allows('others_verify_block_status', $post->profile)) {
      return response()->json([
        'errmsg' => 'cannot perform action postowner has you blocked',
        'status' => 412,
      ]);
    }

    if ($userprofile->profile_id == $post->poster_id) {
      $shares_list = $post->postshares()
      ->whereHas('profile', function (Builder $query) {
        $query->whereHas('user', function (Builder $query) {
          $query->where([
            'deleted' => false,
            'suspended' => false,
            'approved' => true,
          ]);
        });

      })
      ->with('profile.user')
      ->orderBy('id', 'desc')
      ->simplePaginate(10);
    } else {
      $shares_list = $post->postshares()
      ->whereHas('profile', function (Builder $query) {
        $query->whereHas('user', function (Builder $query) {
          $query->where([
            'deleted' => false,
            'suspended' => false,
            'approved' => true,
          ]);
        });
        $query->where(function (Builder $query) {
          $query->whereHas('profile_settings', function (Builder $query) {
            $query->where('blocked_profiles', 'not like', "%{$this->profile->profile_id}%");
          });
          $query->orDoesntHave('profile_settings');
        });
      })
      ->with('profile.user')
      ->orderBy('id', 'desc')
      ->simplePaginate(10);
    }

    if (count($shares_list) < 1) {
      return response()->json([
        'errmsg' => 'No shares yet',
        'status' => 404,
      ]);
    }
    return response()->json([
      'message' => 'found',
      'status' => 200,
      'shares_list' => $shares_list->items(),
      'next_page_url' => $shares_list->nextPageUrl(),
    ]);
  }

  /**
  * public function to search post for words
  *
  * @param  \Illuminate\Http\Request  $request
  */
  public function searchPosts(Request $req) {
    $searchword = $req->word;
    if (is_null($searchword) || empty($searchword)) {
      return response()->json([
        'errmsg' => 'missing values to continue',
        'status' => 400,
      ]);
    }
    $result_posts = Post::with('profile.user')->where('post_text', 'like', "%$searchword%")
    ->where([
      'deleted' => false,
      'archived' => false,
    ])

    ->simplePaginate(10);
    if (count($result_posts) < 1) {
      return response()->json([
        'errmsg' => "no results for search word : $searchword",
        'status' => 404,
      ]);
    }
    return response()->json([
      'message' => 'search results found',
      'search_results' => $result_posts->items(),
      'nextpageurl' => $result_posts->nextPageUrl(),
      'status' => 200,
    ]);
  }

  /**
  * set deleted to true for post
  *
  * @param  \Illuminate\Http\Request  $request
  * @return \Illuminate\Http\Response
  */
  public function destroy(Request $request) {
    $postid = $request->postid;
    $userprofile = $this->profile;
    if (empty($postid) || is_null($postid)) {
      return response()->json([
        'errmsg' => 'could not delete post please try again',
        'status' => 400,
      ]);
    }

    $delete_post = Post::where([
      'postid' => $postid,
      'poster_id' => $userprofile->profile_id,
    ])->update(['deleted' => true]);

    if (!$delete_post) {
      return response()->json([
        'errmsg' => 'could not delete post please try again',
        'status' => 500,
      ]);
    }
    return response()->json([
      'message' => 'post deleted',
      'resetpost' => $userprofile->posts()->latest()->firstWhere([
        'deleted' => false,
        'archived' => false,
      ]),
      'status' => 200,
    ]);

  }

  /**
  * Remove the specified resource from storage.
  *
  * @param  \Illuminate\Http\Request  $request
  * @return \Illuminate\Http\Response
  */
  public function destroyActual(Request $request) {
    $postid = $request->postid;
    $userprofile = $this->profile;
    if (empty($postid) || is_null($postid)) {
      return response()->json([
        'errmsg' => 'could not delete post please try again',
        'status' => 400,
      ]);
    }

    $post = Post::firstWhere('postid', $postid);
    $images = $post->post_image;
    if (is_null($post) || $post->poster_id != $userprofile->profile_id) {
      return response()->json([
        'errmsg' => 'something went wrong please try again',
        'status' => 500,
      ]);
    }
    if (!$post->delete()) {
      return response()->json([
        'errmsg' => 'could not delete post please try again',
        'status' => 500,
      ]);
    }
    foreach ($images as $postimagerow) {
      $this->deleteFile($postimagerow['postimagepath']);
      $this->deleteFile($postimagerow['thumbimagepath']);
    }
    return response()->json([
      'message' => 'post deleted',
      'resetpost' => $userprofile->posts()->latest()->first(),
      'status' => 200,
    ]);

  }
}
