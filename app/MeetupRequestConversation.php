<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MeetupRequestConversation extends Model
{
    //
    public $timestamps = false;
    protected $guarded = [];
    protected $hidden = ['num_new_msg', 'partnermeetprofile'];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['num_new_msg', 'partnermeetprofile'];

    /**
     * Accessor for num_new_msg
     * 
     */
    public function getNumNewMsgAttribute()
    {
        $profile = !is_null(auth()->user()) ? auth()->user()->profile : null;
        if (!is_null($profile)) {
            return $this->where([
                'receiver_id' => $profile->profile_id,
                'conversation_id' => $this->conversation_id,
                ['status', '!=', 'read'],
            ])->count();
        }
    }

    /**
     * accessor for partnerprofile
     * 
     */
    public function getPartnermeetprofileAttribute()
    {
        $profile = !is_null(auth()->user()) ? auth()->user()->profile : null;
        if (!is_null($profile)) {
            return  $profile->profile_id == $this->sender_id ?
                MeetupSetting::firstWhere('owner_id', $this->receiver_id)
                :  MeetupSetting::firstWhere('owner_id', $this->sender_id);
        }
    }

    /**
     * Accessor for chat_pics
     * 
     */
    public function getChatPicAttribute($pics)
    {
        if (is_null($pics) || empty($pics)) {
            return [];
        }
        $chatimagearr = json_decode($pics, true);
        if (is_array($chatimagearr) && count($chatimagearr) > 0) {
            $newarr = [
                'chatpic' => url($chatimagearr['chatpicpath']),
                'size' => $chatimagearr['size'],
                'thumbchatpic' => url($chatimagearr['thumbchatpicpath']),
            ];
            return $newarr;
        }
        return $chatimagearr;
    }

    /**
     * belongs to relationship between meetupconversation  and meetuprequest
     *
     */
    public function origin_meet_request()
    {
        return $this->belongsTo(MeetupRequest::class, 'meet_request_id', 'request_id');
    }

    /**
     * belongs to relationship between meetupconversation  and meetupsetting
     *
     */
    public function sender_meet_profile()
    {
        return $this->belongsTo(MeetupSetting::class, 'sender_id', 'owner_id');
    }

    /**
     * belongs to relationship between meetupconversation  and meetupsetting
     *
     */
    public function receiver_meet_profile()
    {
        return $this->belongsTo(MeetupSetting::class, 'receiver_id', 'owner_id');
    }

    /**
     * belongs to relationship between meetupconversation  and meetupsetting
     */
    public function sender_profile()
    {
        return $this->belongsTo(Profile::class, 'sender_id', 'profile_id');
    }

    /**
     * belongs to relationship between meetupconversation  and meetupsetting
     *
     */
    public function receiver_profile()
    {
        return $this->belongsTo(Profile::class, 'receiver_id', 'profile_id');
    }
}
