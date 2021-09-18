<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PostSettings extends Model
{
    //
    protected $guarded = [];
    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'created_at', 'updated_at', 'timeline_post_range','muted_profiles', 'blacklisted_posts',
    ];
    /**
     * accessor to get muted_profiles and json decode it
     */
    public function getMutedProfilesAttribute($value)
    {
        return json_decode($value, true);
    }
    /**
     * accessor to get muted_posts and json decode it
     */
    public function getBlackListedPostsAttribute($value)
    {
        return json_decode($value, true);
    }
    /**
     * eloquent function to establish relationship between  postsettings model and profile model
     */
    public function profile()
    {
        return $this->belongsTo(Profile::class, 'profile_id', 'profile_id');
    }
}
