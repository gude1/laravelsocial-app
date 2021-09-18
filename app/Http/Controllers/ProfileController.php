<?php

namespace App\Http\Controllers;

use App\Profile;
use App\User;
use App\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
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
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $userprofile = $this->user->profile;
        $userprofile->user;
        return response()->json([
            'message' => 'profile found',
            'profile' => $userprofile,
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
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Request  $req
     * @return \Illuminate\Http\Response
     */
    public function show(Request $req)
    {
        $userprofile = $this->profile;
        $showprofileid = $req->profileid;
        if (is_null($showprofileid) || empty($showprofileid)) {
            return response()->json([
                'errmsg' => 'Missing values to continue',
                'status' => 400,
            ]);
        }
        if ($showprofileid == $userprofile) {
            $userprofile->user->makeVisible(['phone']);
            $userprofile->user;
            return response()->json([
                'message' => 'profile found',
                'profile' => $userprofile,
                'status' => 302,
            ]);
        }

        $showprofile = Profile::with('user')->firstWhere('profile_id', $showprofileid);
        if (
            is_null($showprofile) || is_null($showprofile->user) ||
            $showprofile->user->approved != true || $showprofile->user->deleted == true
        ) {
            return response()->json([
                'errmsg' => 'Profile not found',
                'status' => 404,
            ]);
        }
        if (Gate::allows('others_verify_block_status', $showprofile)) {
            return response()->json([
                'errmsg' => "profile owner has blocked you",
                'status' => 412,
            ]);
        }
        $this->save_visit($showprofileid);
        return response()->json([
            'message' => 'profile found',
            'known_followers_info' => $this->getKnownFollowers($showprofile->profile_id),
            'profile' => $showprofile->fresh('user'),
            'status' => 302,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        $user = $this->user;
        $profile = $user->profile;
        $oldprofilepic = $profile->avatar[0];
        $validate = Validator::make($request->all(), [
            'avatar' => 'bail|sometimes|required|mimes:jpg,jpeg,png,bmp,gif,svg,webp|dimensions:max_width=400,max_height=400',
            'bio' => 'bail|sometimes|required|string|between:3,125',
            'phone' => [
                'bail', 'sometimes', 'required', 'digits:11',
                Rule::unique('users', 'phone')->ignore($user->userid, 'userid'),
            ],
            'campus' => 'bail|sometimes|required|string|between:2,15',
            'gender' => 'bail|sometimes|required|string|between:4,6',
            'profile_name' => 'bail|sometimes|required|string|between:1,20',
            /*'username' => [
        'sometimes',
        'bail',
        'required',
        'between:3,10',
        Rule::unique('users', 'username')->ignore($user->userid, 'userid'),
        'string',
        ],*/
        ]);
        if ($validate->fails()) {
            $errors = $validate->errors();
            $avatarerr = $errors->first('avatar');
            $bioerr = $errors->first('bio');
            $gendererr = $errors->first('gender');
            $campuserr = $errors->first('campus');
            $profile_nameerr = $errors->first('profile_name');
            return response()->json([
                'errors' => [
                    'avatarerr' => $avatarerr,
                    'profile_nameerr' => $profile_nameerr,
                    'bioerr' => $bioerr,
                    'phoneerr' => $errors->first('phone'),
                    'gendererr' => $gendererr,
                    'campuserr' => $campuserr,
                ],
                'status' => 400,
            ]);
        } else {
            $profile_name = $request->profile_name;
            if ($request->filled('profile_name') && $profile_name[0] == "@") {
                return response()->json([
                    'errors' => [
                        'profile_nameerr' => 'profile name cannot start with the @ character'
                    ],
                    'status' => 400,
                ]);
            } else if (in_array($user->gender, ['male', 'female']) && $request->filled('gender')) {
                return response()->json([
                    'errors' => [
                        'gendererr' => 'you have already set a gender and cannot change it',
                    ],
                    'status' => 400,
                ]);
            }
            $newprofilepic = $this->uploadProfilePic();
            $profiledata = $request->only(
                'bio',
                'profile_name',
                'campus',
            );
            /*** snippet of code for the user model */
            $userdata = $request->only('gender', 'phone');
            if (File::exists($newprofilepic)) {
                $profiledata['avatar'] = $newprofilepic;
            }
            if (count($profiledata) < 1 && count($userdata) < 1) {
                return response()->json([
                    'errmsg' => 'Missing values to continue',
                    'status' => 400,
                ]);
            }
            $profile['updated_at'] = time();
            try {
                $profile->update($profiledata);
                $user->update($userdata);
                if (File::exists($newprofilepic)) {
                    $this->deleteFile($oldprofilepic);
                }
                $profile->refresh();
                $profile->user->makeVisible(['phone']);
                return response()->json([
                    'message' => 'updated',
                    'profile' => $profile,
                    'status' => 200,
                ]);
            } catch (Exception $e) {
                $this->deleteFile($newprofilepic);
                return response()->json([
                    'errmsg' => 'something went wrong could not update please try again',
                    'status' => 500,
                ]);
            }
        }
    }

    /***
     * this function handling uploading of profile image
     * @return \Illuminate\Http\Response
     *
     */
    public function uploadProfilePic()
    {
        $user = $this->user;
        if (request()->hasFile('avatar') && request()->file('avatar')->isValid()) {
            $ext = request()->avatar->extension();
            $filename = $user->username . rand(0, 73737) . '.' . $ext;
            if (!$path = request()->avatar->storeAs('images/uploads/profilepics', $filename, 'publics')) {
                return response()->json([
                    'errmsg' => 'Could not update profile pics at this time please try again',
                    'status' => 500,
                ]);
            } else {
                return $path;
            }
        }
        return '';
    }
    /**
     * public function to  handle followprofileaction starts here
     *
     * @param  \Illuminate\Http\Request  $req
     * @return \Illuminate\Http\Response
     */
    public function followProfileAction(Request $req)
    {
        $userprofile = $this->profile;
        $tofollowprofileid = $req->profileid;
        if (is_null($tofollowprofileid) || empty($tofollowprofileid)) {
            return response()->json([
                'errmsg' => 'Missing values to continue',
                'status' => 400,
            ]);
        }
        if ($userprofile->profile_id == $tofollowprofileid) {
            return response()->json([
                'errmsg' => 'you cannot follow yourself',
                'status' => 400,
            ]);
        }
        $tofollowactionprofile = Profile::with('user')->firstWhere('profile_id', $tofollowprofileid);
        if (
            is_null($tofollowactionprofile) ||
            $tofollowactionprofile->user->approved != true
            || $tofollowactionprofile->user->deleted == true
        ) {
            return response()->json([
                'errmsg' => 'Profile not found',
                'status' => 404,
            ]);
        }
        $checkfollowstatus = $tofollowactionprofile->followers()
            ->firstWhere('profile_follower_id', $userprofile->profile_id);
        if (is_null($checkfollowstatus)) {
            /**ensure that certain conditions are met before performing action */
            if (Gate::allows('others_verify_block_status', $tofollowactionprofile)) {
                return response()->json([
                    'errmsg' => "can not complete action {$tofollowactionprofile->user->username} has you blocked",
                    'status' => 412,
                ]);
            }
            if (Gate::allows('user_verify_block_status', $tofollowactionprofile)) {
                return response()->json([
                    'errmsg' => "can not complete action you need to unblock this profile first",
                    'status' => 412,
                ]);
            }
            $followaction = $tofollowactionprofile->followers()->create([
                'profile_follower_id' => $userprofile->profile_id,
            ]);
            $msg = "profile followed";
        } else {
            $followaction = $checkfollowstatus->delete();
            $msg = "profile unfollowed";
        }
        if (!$followaction) {
            return response()->json([
                'errmsg' => 'could not complete action please try again',
                'status' => 5000,
            ]);
        }
            if ($msg == "profile followed") {
                Notification::saveNote([
                    'receipient_id' => $tofollowactionprofile->profile_id,
                    'type' => 'profilefollow',
                    'link'=> 'empty'
                ]);
           }else {
               Notification::deleteNote([
                    'receipient_id' => $tofollowactionprofile->profile_id,
                    'type' => 'profilefollow',
                    'link' => 'empty' 
                ]);
           }
        return response()->json([
            'message' => $msg,
            'status' => 200,
        ]);
    }

    /**
     * public function to get profile followers starts here
     *
     * @param \Illuminate\Http\Request  $req
     * @return \Illuminate\Http\Response
     */
    public function getProfileFollowers(Request $req)
    {
        $userprofile = $this->profile;
        $profile_id = $req->profile_id;
        if (is_null($profile_id) || empty($profile_id)) {
            return response()->json([
                'errmsg' => 'Missing values to continue',
                'status' => 400,
            ]);
        }
        if ($profile_id == $userprofile->profile_id) {
            $togetfollowers_profile = $userprofile;
        } else {
            $togetfollowers_profile = Profile::whereHas('user', function (Builder $query) {
                $query->where([
                    'approved' => true,
                    'deleted' => false,
                ]);
            })
                ->with('user')
                ->firstWhere('profile_id', $profile_id);
        }
        if (is_null($togetfollowers_profile)) {
            return response()->json([
                'errmsg' => 'no followers!',
                'status' => 404,
            ]);
        }
        /**ensure that certain conditions are met before performing action */
        if (Gate::allows('others_verify_block_status', $togetfollowers_profile)) {
            return response()->json([
                'errmsg' => "can not complete action {$togetfollowers_profile->user->username} has you blocked",
                'status' => 412,
            ]);
        }

        /*->whereHas('profile',function(Builder $query){
        $query->whereHas('profile_settings',function(Builder $query){
        $query->where('blocked_profiles','not like',"%{$this->profile->profile_id}%");
        });
        $query->orDoesntHave('profile_settings');
        })*/

        $followers_list = $togetfollowers_profile
            ->follower_profiles()
            ->whereHas('user', function (Builder $query) {
                $query->where([
                    'approved' => true,
                    'deleted' => false,
                ]);
            })
            ->where(function (Builder $query) {
                $query->whereHas('profile_settings', function (Builder $query) {
                    $query->where('blocked_profiles', 'not like', "%{$this->profile->profile_id}%");
                });
                $query->orDoesntHave('profile_settings');
            })
            ->with('user')
            ->orderBy('id', 'desc')
            ->simplePaginate(50);
        if (count($followers_list) < 1) {
            return response()->json([
                'errmsg' => 'no followers!',
                'status' => 404,
            ]);
        }
        return response()->json([
            'message' => 'found!',
            'profile' => $togetfollowers_profile,
            'status' => 200,
            'followers_list' => $followers_list->items(),
            'next_url' => $followers_list->nextPageUrl(),
        ]);
    }

    /**
     * public function to get profiles followed by specified profile starts here
     *
     * @param \Illuminate\Http\Request  $req
     * @return \Illuminate\Http\Response
     */
    public function getProfilesFollowing(Request $req)
    {
        $userprofile = $this->profile;
        $profile_id = $req->profile_id;
        if (is_null($profile_id) || empty($profile_id)) {
            return response()->json([
                'errmsg' => 'Missing values to continue',
                'status' => 400,
            ]);
        }
        if ($profile_id == $userprofile->profile_id) {
            $togetfollowings_profile = $userprofile;
        } else {
            $togetfollowings_profile = Profile::whereHas('user', function (Builder $query) {
                    $query->where([
                        'approved' => true,
                        'deleted' => false,
                    ]);
                })->with('user')
                ->firstWhere('profile_id', $profile_id);
        }
        if (is_null($togetfollowings_profile)) {
            return response()->json([
                'errmsg' => 'not found!',
                'status' => 404,
            ]);
        }
        /**ensure that certain conditions are met before performing action */
        if (Gate::allows('others_verify_block_status', $togetfollowings_profile)) {
            return response()->json([
                'errmsg' => "can not complete action {$togetfollowings_profile->user->username} has you blocked",
                'status' => 412,
            ]);
        }
        $followings_list = $togetfollowings_profile
            ->followed_profiles()
            ->whereHas('user', function (Builder $query) {
                $query->where([
                    'approved' => true,
                    'deleted' => false,
                ]);
            })
            ->where(function (Builder $query) {
                $query->whereHas('profile_settings', function (Builder $query) {
                    $query->where('blocked_profiles', 'not like', "%{$this->profile->profile_id}%");
                });
                $query->orDoesntHave('profile_settings');
            })
            ->with('user')
            ->orderBy('id', 'desc')
            ->simplePaginate(50);
        if (count($followings_list) < 1) {
            return response()->json([
                'errmsg' => 'not found!',
                'status' => 404,
            ]);
        }
        return response()->json([
            'message' => 'found!',
            'profile' => $togetfollowings_profile,
            'status' => 200,
            'followings_list' => $followings_list->items(),
            'next_url' => $followings_list->nextPageUrl(),
        ]);
    }

    /**
     * public function to profile followers you know
     * @param  \Illuminate\Http\Request  $req
     * @return \Illuminate\Http\Response
     */
    public function getknowProfileFollowers(Request $req)
    {
        $userprofile = $this->profile;
        if (is_null($req->profileid) || empty($req->profileid) || $userprofile->profile_id == $req->profileid) {
            return response()->json([
                'errmsg' => 'cant fetch something went wrong',
                'status' => 400,
            ]);
        }
        $userprofile = $this->profile;
        $related_profiles = $userprofile->followed_profiles()
            ->whereHas('user', function (Builder $query) {
                $query->where([
                    'approved' => true,
                    'deleted' => false,
                ]);
            })
            ->where(function (Builder $query) {
                $query->whereHas('profile_settings', function (Builder $query) {
                    $query->where('blocked_profiles', 'not like', "%{$this->profile->profile_id}%");
                });
                $query->orDoesntHave('profile_settings');
            })
            ->whereHas('followings', function (Builder $query) {
                $query->where('profile_followed_id', request()->profileid);
            })
            ->orderBy('id', 'desc')
            ->simplePaginate(50);
        $related_profiles->loadMissing('user');
        $items = $related_profiles->items();
        $msg = null;
        switch (count($related_profiles)) {
            case 0:
                $msg = 'not followed by anyone you are following';
                return response()->json([
                    'errmsg' => $msg,
                    'status' => 404,
                ]);
                break;
            case 1:
                $msg = "followed by {$items[0]->user->username}";
                break;
            case 2:
                $msg = "followed by {$items[0]->user->username} and others";
                break;
            default:
                $msg = null;
                break;
        }
        return response()->json([
            'message' => $msg,
            'related_profiles' => $items,
            'next_page_url' => $related_profiles->nextPageUrl(),
            'status' => 200,
        ]);
    }

    /**
     * local function to store profile visits
     */
    protected function save_visit($profileid)
    {
        $userprofile = $this->profile;
        if (is_null($profileid) || empty($profileid) || $userprofile->profile_id == $profileid) {
            return false;
        }
        $visit_record = $userprofile->getVisited()->firstWhere([
            'profile_owner_id' => $profileid,
            'date' => date("d/m/Y", time()),
        ]);
        if (is_null($visit_record)) {
            $savevisit = $userprofile->getVisited()->create([
                'profile_owner_id' => $profileid,
                'date' => date("d/m/Y", time()),
                'created_at' => time(),
                'num_visits' => 1,
                'updated_at' => time(),
            ]);
        } else {
            $num_visits = $visit_record->num_visits + 1;
            $num_visits = $num_visits > 0 ? $num_visits : 1;
            $savevisit = $visit_record->update([
                'num_visits' => $num_visits,
                'updated_at' => time(),
            ]);
        }

        if (!$savevisit) {
            return false;
        }
        return true;
    }

    /**
     * local function to known if profile have known followers
     *
     */
    protected function getKnownFollowers($profileid)
    {
        $userprofile = $this->profile;
        if (is_null($profileid) || empty($profileid) || $userprofile->profile_id == $profileid) {
            return null;
        }
        $this->store_string = $profileid;
        $related_profiles = $userprofile->followed_profiles()
            ->whereHas('user', function (Builder $query) {
                $query->where([
                    'approved' => true,
                    'deleted' => false,
                ]);
            })
            ->where(function (Builder $query) {
                $query->whereHas('profile_settings', function (Builder $query) {
                    $query->where('blocked_profiles', 'not like', "%{$this->profile->profile_id}%");
                });
                $query->orDoesntHave('profile_settings');
            })
            ->whereHas('followings', function (Builder $query) {
                $query->where('profile_followed_id', $this->store_string);
            })
            ->orderBy('id', 'desc')
            ->take(2)
            ->get();
        $related_profiles->loadMissing('user');
        $msg = null;
        switch (count($related_profiles)) {
            case 0:
                $msg = 'not followed by anyone you are following';
                break;
            case 1:
                $msg = "followed by {$related_profiles[0]->user->username}";
                break;
            case 2:
                $msg = "followed by {$related_profiles[0]->user->username} and others";
                break;
            default:
                $msg = null;
                break;
        }
        return $msg;
    }

    /**
     * public function to get list of profiles
     *
     * @param \Illuminate\Http\Request  $req
     * @return \Illuminate\Http\Response
     */
    public function fetchProfiles(Request $req)
    {
        $userprofile = $this->profile;
        $offsetarr = $req->offsetarr;
        $ignore_arr = $req->delimiter_arr;
        $ignore_arr = !is_array($ignore_arr) || count($ignore_arr) < 1 ? [$userprofile->id] : $ignore_arr;
        $visited_profiles = $userprofile->getVisitedProfiles()
            ->with('user')
            ->whereHas('user', function (Builder $query) {
                $query->where([
                    'approved' => true,
                    'deleted' => false,
                ]);
            })
            ->where(function (Builder $query) {
                $query->whereHas('profile_settings', function (Builder $query) {
                    $query->where('blocked_profiles', 'not like', "%{$this->profile->profile_id}%");
                });
                $query->orDoesntHave('profile_settings');
                $query->whereNotNull([
                    'avatar',
                    'bio',
                    'campus',
                ]);
            })
            ->whereNotIn('profiles.id', $ignore_arr)
            ->orderBy('id', 'desc')
            ->limit(10)
            ->distinct()
            ->get();
        $ignore_arr = array_merge($visited_profiles->pluck('id')->toArray(), $ignore_arr);

        $followed_profiles = $userprofile->followed_profiles()
            ->with('user')
            ->whereHas('user', function (Builder $query) {
                $query->where([
                    'approved' => true,
                    'deleted' => false,
                ]);
            })
            ->where(function (Builder $query) {
                $query->whereHas('profile_settings', function (Builder $query) {
                    $query->where('blocked_profiles', 'not like', "%{$this->profile->profile_id}%");
                });
                $query->orDoesntHave('profile_settings');
                $query->whereNotNull([
                    'avatar',
                    'bio',
                    'campus',
                ]);
            })
            ->whereNotIn('profiles.id', $ignore_arr)
            ->limit(10)
            ->distinct()
            ->get();
        $ignore_arr = array_merge($followed_profiles->pluck('id')->toArray(), $ignore_arr);

        $campus_profiles = Profile::with('user')->whereHas('user', function (Builder $query) {
            $query->where([
                'approved' => true,
                'deleted' => false,
            ]);
        })
            ->where(function (Builder $query) {
                $query->whereNotNull([
                    'avatar',
                    'bio',
                    'campus',
                ]);
                $query->whereHas('profile_settings', function (Builder $query) {
                    $query->where('blocked_profiles', 'not like', "%{$this->profile->profile_id}%");
                });
                $query->orDoesntHave('profile_settings');
            })
            ->where('campus', $userprofile->campus)
            ->whereNotIn('id', $ignore_arr)
            ->limit(10)
            ->distinct()
            ->get();
        $ignore_arr = array_merge($campus_profiles->pluck('id')->toArray(), $ignore_arr);

        $general_profiles = Profile::with('user')->WhereHas('user', function (Builder $query) {
            $query->where([
                'approved' => true,
                'deleted' => false,
            ]);
        })
            ->where(function (Builder $query) {
                $query->whereHas('profile_settings', function (Builder $query) {
                    $query->where('blocked_profiles', 'not like', "%{$this->profile->profile_id}%");
                });
                $query->orDoesntHave('profile_settings');
                $query->whereNotNull([
                    'avatar',
                    'bio',
                    'campus',
                ]);
            })
            ->whereNotIn('id', $ignore_arr)
            ->orderBy('id', 'desc')
            ->limit(10)
            ->distinct()
            ->get();
        $ignore_arr = array_merge($general_profiles->pluck('id')->toArray(), $ignore_arr);

        $result_arr = array_merge(
            $visited_profiles->toArray(),
            $followed_profiles->toArray(),
            $campus_profiles->toArray(),
            $general_profiles->toArray()
        );

        if (count($result_arr) < 1) {
            return response()->json([
                'errmsg' => 'No results',
                'status' => 404,
            ]);
        }
        return response()->json([
            'message' => 'fetch successful',
            'results' => $result_arr,
            'offset_links' => $ignore_arr,
            'status' => 200,
        ]);
    }

    /**
     * public function to search through profile via keyword
     *
     * @param  \Illuminate\Http\Request  $req
     * @return \Illuminate\Http\Response
     */
    public function searchProfiles(Request $req)
    {
        $keyword = $req->keyword;
        if (is_null($keyword) || empty($keyword)) {
            return response()->json([
                'errmsg' => "No result for key word '$keyword'",
                'status' => 404,
            ]);
        }
        if ($keyword[0] == "@") {
            $keyword = substr($keyword, 1);
            $search_results = User::with('profile')
                ->whereHas('profile', function (Builder $query) {
                    $query->where(function (Builder $query) {
                        $query->whereNotNull([
                            'avatar',
                            'bio',
                            'campus',
                        ]);
                        $query->whereHas('profile_settings', function (Builder $query) {
                            $query->where('blocked_profiles', 'not like', "%{$this->profile->profile_id}%");
                        });
                        $query->orDoesntHave('profile_settings');
                    });
                })
                ->where([
                    ['username', 'like', "%{$keyword}%"],
                    ['approved', '=', true],
                    ['deleted', '=', false],
                ])
                ->simplePaginate(20);
        } else {
            $search_results = User::with('profile')
                ->whereHas('profile', function (Builder $query) {
                    $query->where('profile_name', 'like', "%" . request()->keyword . "%");
                    $query->where(function (Builder $query) {
                        $query->whereNotNull([
                            'avatar',
                            'bio',
                            'campus',
                        ]);
                        $query->whereHas('profile_settings', function (Builder $query) {
                            $query->where('blocked_profiles', 'not like', "%{$this->profile->profile_id}%");
                        });
                        $query->orDoesntHave('profile_settings');
                    });
                })
                ->where([
                    'approved' => true,
                    'deleted' => false,
                ])
                ->simplePaginate(20);
        }
        if (count($search_results) < 1) {
            return response()->json([
                'errmsg' => "No result for key word '$keyword'",
                'status' => 404,
            ]);
        }
        return response()->json([
            'message' => "results found for '$keyword'",
            'results' => $search_results->items(),
            'next_url' => $search_results->nextPageUrl(),
            'status' => 200,
        ]);
    }

    /**
     * public function to handle muteprofile action
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function muteProfileAction(Request $req)
    {
        $userprofile = $this->profile;
        $tomuteprofileid = $req->profileid;
        if (
            is_null($tomuteprofileid) || empty($tomuteprofileid) ||
            $tomuteprofileid == $userprofile->profile_id
        ) {
            return response()->json([
                'errmsg' => 'something went wrong please try again',
                'status' => 400,
            ]);
        }
        if (is_null($userprofile->profile_settings) || empty($userprofile->profile_settings)) {
            $muted_profiles = [];
        } else {
            $muted_profiles = $userprofile->profile_settings->muted_profiles;
        }

        if (in_array($tomuteprofileid, $muted_profiles)) {
            $index = array_search($tomuteprofileid, $muted_profiles);
            unset($muted_profiles[$index]);
            $muted_profiles = array_values($muted_profiles);
            $msg = 'profile unmuted';
        } else {
            array_push($muted_profiles, $tomuteprofileid);
            $msg = 'profile muted';
        }
        $tomuteaction = $userprofile->profile_settings()->updateOrCreate(
            ['profile_id' => $userprofile->profile_id],
            ['muted_profiles' => json_encode($muted_profiles)]
        );
        if (!$tomuteaction) {
            return response()->json([
                'errmsg' => 'could not perform mute action please try again',
                'status' => 500,
            ]);
        }
        return response()->json([
            'message' => $msg,
            'status' => 200,
        ]);
    }

    /**
     * public function to handle muteprofile action
     *
     * @param  \Illuminate\Http\Request  $req
     * @return \Illuminate\Http\Response
     */
    public function blockProfileAction(Request $req)
    {
        $userprofile = $this->profile;
        $toblockprofileid = $req->profileid;
        if (
            is_null($toblockprofileid) || empty($toblockprofileid) ||
            $toblockprofileid == $userprofile->profile_id
        ) {
            return response()->json([
                'errmsg' => 'something went wrong please try again',
                'status' => 400,
            ]);
        }
        if (is_null($userprofile->profile_settings) || empty($userprofile->profile_settings)) {
            $blocked_profiles = [];
        } else {
            $blocked_profiles = $userprofile->profile_settings->blocked_profiles;
        }

        if (in_array($toblockprofileid, $blocked_profiles)) {
            $index = array_search($toblockprofileid, $blocked_profiles);
            unset($blocked_profiles[$index]);
            $blocked_profiles = array_values($blocked_profiles);
            $msg = 'profile unblocked';
        } else {
            array_push($blocked_profiles, $toblockprofileid);
            $msg = 'profile blocked';
        }
        $toblockaction = $userprofile->profile_settings()->updateOrCreate(
            ['profile_id' => $userprofile->profile_id],
            ['blocked_profiles' => json_encode($blocked_profiles)]
        );
        if (!$toblockaction) {
            return response()->json([
                'errmsg' => 'could not perform block action please try again',
                'status' => 500,
            ]);
        }
        return response()->json([
            'message' => $msg,
            'status' => 200,
        ]);
    }

    /**
     * public function to delete file from storage
     */
    public function deleteFile($file)
    {
        if (File::exists($file)) {
            File::delete($file);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Profile  $profile
     * @return \Illuminate\Http\Response
     */
    public function destroy(Profile $profile)
    {
        //
    }
}
