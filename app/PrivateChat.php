<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PrivateChat extends Model
{
    //
    protected $guarded = [];
    public $timestamps = false;
    protected $hidden = ['num_new_msg', 'partnerprofile'];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['num_new_msg', 'partnerprofile'];

    /**
     * accessor for num_new_msg
     */
    public function getNumNewMsgAttribute()
    {
        $profile = !is_null(auth()->user()) ? auth()->user()->profile : null;
        if (!is_null($profile)) {
            return $this->where([
                'created_chatid' => $this->created_chatid,
                'receiver_id' => $profile->profile_id,
                'receiver_deleted' => false,
                ['read', '!=', 'true'],
            ])->count();
        }
        return 0;
    }

    /**
     * accessor for partnerprofile
     * 
     */
    public function getPartnerProfileAttribute()
    {
        $profile = !is_null(auth()->user()) ? auth()->user()->profile : null;
        if (!is_null($profile)) {
            return  $profile->profile_id == $this->sender_id ?
                Profile::with('user')->firstWhere('profile_id', $this->receiver_id)
                :  Profile::with('user')->firstWhere('profile_id', $this->sender_id);
        }
    }


    /**
     * accessor for chat_pics
     */
    public function getChatPicsAttribute($pics)
    {
        if (is_null($pics) || empty($pics)) {
            return [];
        }
        $chatimagearr = json_decode($pics, true);
        if (count($chatimagearr) > 0) {
            return [
                'chatpic' => url($chatimagearr['chatpicpath']),
                'size' => $chatimagearr['size'],
                'thumbchatpic' => url($chatimagearr['thumbchatpicpath']),
            ];
        }
        return $chatimagearr;
    }

    /**
     * belongsTo relationship between private chat and sender profile
     */
    public function sender_profile()
    {
        return $this->belongsTo(Profile::class, 'sender_id', 'profile_id');
    }

    /**
     * belongsTo relationship between private chat and receiver profile
     */
    public function receiver_profile()
    {
        return $this->belongsTo(Profile::class, 'receiver_id', 'profile_id');
    }

    /**
     *hasMany relationship between privatechat and other 
     */
    public function related_chats()
    {
        return $this->hasMany(PrivateChat::class, 'created_chatid', 'created_chatid');
    }
}
