<?php

use App\Events\NotifyEvent;
use Illuminate\Http\Request;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
 */

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/', function () {
    return view('testevent');
});

Route::get('/start', function () {
    broadcast(new NotifyEvent("hsdhshh"));
});

/**
 * authentication controller route
 */
Route::prefix('auth')->group(function () {
    Route::match(['get', 'post'], '/register', 'UserController@store');
    Route::match(['get', 'post'], '/login', 'UserController@login');
});

/**
 * user controller route
 */
Route::match(['get', 'post'], 'user/adddevicetoken', 'UserController@addDeviceToken');
Route::match(['get', 'post'], 'user/sendnotification', 'UserController@sendNotification');
Route::match(['get', 'delete'], 'userdelete/', 'UserController@destroy');
Route::match(['get', 'patch', 'put'], 'user/{user}', 'UserController@update');

/**
 * profile controller route
 */
Route::match(['get', 'patch', 'post', 'put'], 'profileupdate', 'ProfileController@update');
Route::match(['get', 'post'], 'profilefollowaction', 'ProfileController@followProfileAction');
Route::match(['get', 'post'], 'profileshow', 'ProfileController@show');
Route::match(['get', 'post'], 'profile', 'ProfileController@index');
Route::match(['get', 'post'], 'profileblockaction', 'ProfileController@blockProfileAction');
Route::match(['get', 'post'], 'profilemuteaction', 'ProfileController@muteProfileAction');
Route::match(['get', 'post'], 'profileslist', 'ProfileController@fetchProfiles');
Route::match(['get', 'post'], 'profilesearch', 'ProfileController@searchProfiles');
Route::match(['get', 'post'], 'profilefollowerslist', 'ProfileController@getProfileFollowers');
Route::match(['get', 'post'], 'profilesfollowinglist', 'ProfileController@getProfilesFollowing');
Route::match(['get', 'post'], 'uknowprofilesfollowerslist', 'ProfileController@getknowProfileFollowers');

/**
 * profilevisit controller
 */
Route::match(['get', 'post'], 'profilevisit', 'ProfileVisitController@store');

/**
 * post controller
 */
Route::match(['get', 'put', 'post'], 'post', 'PostController@store');
Route::match(['get', 'post'], 'postlist', 'PostController@index');
Route::match(['get', 'post'], 'postshow', 'PostController@show');
Route::match(['get', 'post', 'delete'], 'postdelete', 'PostController@destroy');
Route::match(['get', 'post'], 'postarchive', 'PostController@archivePostAction');
Route::match(['get', 'post'], 'postlikeaction', 'PostController@postLikeAction');
Route::match(['get', 'post'], 'postlikes', 'PostController@getPostLikesList');
Route::match(['get', 'post'], 'postshares', 'PostController@getPostSharesList');
Route::match(['get', 'post'], 'postshareaction', 'PostController@postShareAction');
Route::match(['get', 'post'], 'postsearch', 'PostController@searchPosts');
Route::match(['get', 'post'], 'muteprofilepostaction', 'PostController@muteProfilePostAction');
Route::match(['get', 'post'], 'getpostsetting', 'PostController@getPostSetting');
Route::match(['get', 'post'], 'updatepostsetting', 'PostController@updatePostSetting');
Route::match(['get', 'post'], 'profileposts', 'PostController@getProfilePosts');
Route::match(['get', 'post'], 'blacklistpostaction', 'PostController@blackListPostAction');
/**
 * postcomment controller
 */
Route::match(['get', 'post'], 'postcommentlist', 'PostCommentController@index');
Route::match(['get', 'put', 'post'], 'postcomment', 'PostCommentController@store');
Route::match(['get', 'post'], 'postcommentshow', 'PostCommentController@show');
Route::match(['get', 'post'], 'postcommentlikeaction', 'PostCommentController@likeAction');
Route::match(['get', 'post'], 'postcommentlikes', 'PostCommentController@getLikesList');
Route::match(['get', 'post'], 'postcommenthide', 'PostCommentController@hideCommentAction');
Route::match(['get', 'post'], 'postcommentdelete', 'PostCommentController@destroy');

/**
 * postcommentreply controller
 */
Route::match(['get', 'post'], 'postcommentreplylist', 'PostCommentReplyController@index');
Route::match(['get', 'post'], 'postcommentreply', 'PostCommentReplyController@store');
Route::match(['get', 'post'], 'postcommentreplylikeaction', 'PostCommentReplyController@handleLikeAction');
Route::match(['get', 'post'], 'postcommentreplyshow', 'PostCommentReplyController@show');
Route::match(['get', 'post'], 'postcommentreplylikes', 'PostCommentReplyController@getLikesList');
Route::match(['get', 'post'], 'postcommentreplyhide', 'PostCommentReplyController@hideReplyAction');
Route::match(['get', 'post'], 'postcommentreplydelete', 'PostCommentReplyController@destroy');

/**
 *PrivateChat Controller
 */
Route::match(['get', 'post'], 'privatechatlist', 'PrivateChat2Controller@index');
Route::match(['get', 'post'], 'sendprivatechat', 'PrivateChat2Controller@store');
Route::match(['get', 'post'], 'showprivatechat', 'PrivateChat2Controller@show');
Route::match(['get', 'post'], 'showprivatechatandupdateread', 'PrivateChatController@showAndUpdateCreateChatRead');
Route::match(['get', 'post'], 'setprivatechatread', 'PrivateChat2Controller@setChatToRead');
Route::match(['get', 'post'], 'setprivatechatarrread', 'PrivateChatController@setReqChatArrayToRead');
Route::match(['get', 'post'], 'deleteaprivatechat', 'PrivateChat2Controller@destroy');
Route::match(['get', 'post'], 'deleteprivatechat', 'PrivateChat2Controller@deletePrivateChatList');
Route::match(['get', 'post'], 'getaprivatechatinfo', 'PrivateChat2Controller@getAPrivateChatInfo');
Route::match(['get', 'post'], 'searchprivatechatlist', 'PrivateChatController@searchPrivateChatList');
Route::match(['get', 'post'], 'deleteaprivatechat', 'PrivateChat2Controller@deleteAPrivateChat');

/**
 *MeetupRequest Controller
 */
Route::match(['get', 'post'], 'meetupreqlist', 'MeetupRequestController@index');
Route::match(['get', 'post'], 'updatemeetupsetting', 'MeetupRequestController@saveMeetupDetails');
Route::match(['get', 'post'], 'addmeetupreq', 'MeetupRequestController@store');
Route::match(['get', 'post'], 'showmeetupreq', 'MeetupRequestController@show');
Route::match(['get', 'post'], 'meetblacklistaction', 'MeetupRequestController@handleBlackList');
Route::match(['get', 'post'], 'searchmeetupreq', 'MeetupRequestController@searchRequests');
//Route::match(['get', 'post'], 'respondtomeetupreq', 'MeetupRequestController@respondToRequest');
Route::match(['get', 'post'], 'mymeetupreqs', 'MeetupRequestController@getProfilesMeetupRequests');
Route::match(['get', 'post'], 'meetsetting', 'MeetupRequestController@getAMeetSetting');
Route::match(['get', 'post'], 'deletemeetupreq', 'MeetupRequestController@destroy');

/**
 *MeetupRequestConversation Controller
 *
 */
Route::match(['get', 'post'], 'meetupreqconvlist', 'MeetupRequestConversationController@index');
Route::match(['get', 'post'], 'setmeetconvstatus', 'MeetupRequestConversationController@setConvStatus');
Route::match(['get', 'post'], 'addmeetupreqconv', 'MeetupRequestConversationController@store');
Route::match(['get', 'post'], 'getandreadmeetconvs', 'MeetupRequestConversationController@getConvsAndSetRead');

/**
 *ComplaintSuggestReport Controller
 */
Route::match(['get', 'post'], 'makereport', 'ComplaintSuggestReportController@store');

/**
 *Notification Controller
 */
Route::match(['get', 'post'], 'fetchnotes', 'NotificationController@index');
Route::match(['get', 'post'], 'makenote', 'NotificationController@store');

/**
 * story controller
 */
Route::match(['get', 'put'], 'storylist', 'StoryController@index');
Route::match(['get', 'put'], 'story', 'StoryController@store');
Route::match(['get', 'post'], 'storyshow', 'StoryController@show');
