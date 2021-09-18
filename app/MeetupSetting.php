<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MeetupSetting extends Model
{
    //
    protected $guarded = [];

    /**
     * getter for  black_listed_arr attribute
     */
    public function getBlackListedArrAttribute($arr)
    {
        if (is_null($arr) || empty($arr)) {
            return [];
        }
        return json_decode($arr, true);
    }

    /**
     * getter for meetup_avatar_attribute
     */
    public function getMeetupAvatarAttribute($avatar)
    {
        return url($avatar);
    }
}
