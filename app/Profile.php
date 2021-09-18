<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    //
    protected $guarded = [];
    protected $hidden = ['created_at', 'updated_at'];
    public $timestamps = false;
    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'profilemuted',
        'ublockedprofile',
        'profileblockedu',
        'following',
        'followsu',
    ];

    /**
     * Set the profile id
     * @return void
     */
    public function setProfileIdAttribute()
    {
        $this->attributes['profile_id'] = md5(rand(2452, 1632662727));
    }

    /**
     * accessor for profilemuted
     */
    public function getProfilemutedAttribute()
    {
        if (auth()->user()) {
            $authprofilesettings = auth()->user()->profile->profile_settings;
            if (is_null($authprofilesettings) || empty($authprofilesettings)) {
                return false;
            }
            return in_array($this->profile_id, $authprofilesettings->muted_profiles);
        }

    }

    /**
     * accessor for following
     */
    public function getFollowingAttribute()
    {
        if (auth()->user()) {
            $authuserprofile_id = auth()->user()->profile->profile_id;
            return $this->followers()->where('profile_follower_id', $authuserprofile_id)->exists();
        }
    }

    /**
     * accessor for following
     */
    public function getFollowsuAttribute()
    {
        if (auth()->user()) {
            $authuserprofile_id = auth()->user()->profile->profile_id;
            return $this->followings()->where('profile_followed_id', $authuserprofile_id)->exists();
        }
    }

    /**
     * accessor for ublockedprofile
     */
    public function getUBlockedProfileAttribute()
    {
        if (auth()->user()) {
            $authprofilesettings = auth()->user()->profile->profile_settings;
            if (is_null($authprofilesettings) || empty($authprofilesettings)) {
                return false;
            }
            return in_array($this->profile_id, $authprofilesettings->blocked_profiles);
        }
    }
    /**
     * accessor for profileblockedu
     */
    public function getProfileBlockedUAttribute()
    {
        if (auth()->user()) {
            $authprofile_id = auth()->user()->profile->profile_id;
            $profilesettings = $this->profile_settings;
            if (is_null($authprofile_id) || empty($authprofile_id)) {
                return false;
            } elseif (is_null($profilesettings) || empty($profilesettings)) {
                return false;
            }
            return in_array($authprofile_id, $profilesettings->blocked_profiles);
        }
    }

    /**
     * accessor for num_followers
     */
    public function getNumFollowersAttribute()
    {
        return $this->followers()->count();
    }
    /**
     * accessor for num_following
     */
    public function getNumFollowingAttribute()
    {
        return $this->followings()->count();
    }
    /**
     * accssort to get num_posts
     */
    public function getNumPostsAttribute()
    {
        return $this->posts()->count();
    }

    /**
     * get profile avatar
     */
    public function getAvatarAttribute($value)
    {
        if (!is_null($value) || !empty($value)) {
            return [$value, url($value)];
        }
        return [null, null];
    }
    /**
     * function that establish relationship between profile and user model
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'userid', 'userid');
    }

    /**
     * function to establish relationship between profile and followers --FollowershipInfo model
     */
    public function activity_logs()
    {
        return $this->hasMany(UserActivity::class, 'profile_id', 'profile_id');
    }

    /**
     * function to establish relationship between profile and followers --FollowershipInfo model
     */
    public function notifications()
    {
        return $this->hasMany(Notification::class, 'receipient_id', 'profile_id');
    }

    /**
     * function to establish relationship between profile and followers --FollowershipInfo model
     */
    public function followers()
    {
        return $this->hasMany(FollowershipInfo::class, 'profile_followed_id', 'profile_id');
    }

    /**
     * function to establish relationship between profile and followings --FollowershipInfo model
     */
    public function followings()
    {
        return $this->hasMany(FollowershipInfo::class, 'profile_follower_id', 'profile_id');
    }

    /**
     * function to establish relationship between profile and profilevisit model
     */
    public function profilevisits()
    {
        return $this->hasMany(ProfileVisit::class, 'profile_owner_id', 'profile_id');
    }

    /**
     * function to establish relationship between profile and post model
     */
    public function posts()
    {
        return $this->hasMany(Post::class, 'poster_id', 'profile_id');
    }

    /**
     * hasmany function to establish relationship between profile and meetuprequest model
     */
    public function meetup_setting()
    {
        return $this->hasOne(MeetupSetting::class, 'owner_id', 'profile_id');
    }

    /**
     * hasmany function to establish relationship between profile and meetuprequest model
     */
    public function meetup_requests()
    {
        return $this->hasMany(MeetupRequest::class, 'requester_id', 'profile_id');
    }

    /**
     * function to establish relationship between profile and story
     */
    public function stories()
    {
        return $this->hasMany(Story::class, 'poster_id', 'profile_id');
    }

    /**
     * has many through relationships to get access to the profiles that are following the user
     */
    public function follower_profiles()
    {
        return $this->hasManyThrough(
            Profile::class,
            FollowershipInfo::class,
            'profile_followed_id', //this establishes the relationship between Followershipinfo(intermediate) and this particular model class
            'profile_id', //this establishes the relationship between the final model class and intermediate
            'profile_id', //the value expected in the relationship column of the particular model class and intermediate(FollowershipINfo)
            'profile_follower_id' //the value expected in the relationship column for the  final model and the intermdeiate model
        );
    }

    /**
     * has many through relationships to get access to the profiles that the user is following
     */
    public function followed_profiles()
    {
        return $this->hasManyThrough(
            Profile::class,
            FollowershipInfo::class,
            'profile_follower_id',
            'profile_id',
            'profile_id',
            'profile_followed_id'
        );
    }

    /**
     * has many through relationship to get access to posts made by profile that the user is following
     */
    public function followed_profiles_posts()
    {
        return $this->hasManyThrough(
            Post::class,
            FollowershipInfo::class,
            'profile_follower_id',
            'poster_id',
            'profile_id',
            'profile_followed_id'
        );
    }

    /**
     * has many through relationship to get access stories of  profiles that the user is following
     *
     */
    public function followings_stories()
    {
        return $this->hasManyThrough(
            Story::class,
            FollowershipInfo::class,
            'profile_follower_id',
            'poster_id',
            'profile_id',
            'profile_followed_id'
        );
    }

    /**
     * has one relationship between profile and postsettings
     */
    public function post_settings()
    {
        return $this->hasOne(PostSettings::class, 'profile_id', 'profile_id');
    }

    /**
     * has one realtionship between profile and profilesettings
     */
    public function profile_settings()
    {
        return $this->hasOne(ProfileSetting::class, 'profile_id', 'profile_id');
    }

    /**
     *  hasMany relationships to get  that visited you
     */
    public function getVisitors()
    {
        return $this->hasMany(ProfileVisit::class, 'profile_owner_id', 'profile_id');
    }

    /**
     *  hasMany relationships to get that you visited
     */
    public function getVisited()
    {
        return $this->hasMany(ProfileVisit::class, 'visitor_id', 'profile_id');
    }

    /**
     * hasMany through rekationship to get profiles you visited
     */
    public function getVisitedProfiles()
    {
        return $this->hasManyThrough(
            Profile::class,
            ProfileVisit::class,
            'visitor_id',
            'profile_id',
            'profile_id',
            'profile_owner_id'
        );
    }

}
