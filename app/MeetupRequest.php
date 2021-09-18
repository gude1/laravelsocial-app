<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MeetupRequest extends Model
{
    //
    public $timestamps = false;
    protected $guarded = [];

    public function requester_profile()
    {
        return $this->belongsTo(Profile::class, 'requester_id', 'profile_id');
    }

    public function requester_meet_profile()
    {
        return $this->belongsTo(MeetupSetting::class, 'requester_id', 'owner_id');
    }

    /**
     * Defines hasMany eloquent relationship between MeetupRequest and  MeetupRequestConversation
     */
    public function conversations()
    {
        return $this->hasMany(MeetupRequestConversation::class, 'meet_request_id', 'request_id');
    }

    /**
     * to get meeetup_avatar attribute
     */
    public function getMeetupAvatarAttribute($value)
    {
        return url($value);
    }

    /**
     * to get responder_ids attribute responders_ids
     */
    public function getRespondersIds($value)
    {
        if (is_null($value) || empty($value)) {
            return [];
        }
        return json_decode($value, true);
    }
}
