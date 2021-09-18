<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class FollowershipInfo extends Model
{
    //
    protected $guarded = [];
    /**
     * eloquent relationship to get the profile following the user
     */
    public function follower_profile(){
        return $this->belongsTo(Profile::class,'profile_follower_id','profile_id');
    }

    /**
     * eloquent relationship to get  the profile the  user is following
     */
    public function followed_profile(){
        return $this->belongsTo(Profile::class,'profile_followed_id','profile_id');
    }
}
