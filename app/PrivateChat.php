<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PrivateChat extends Model
{
    //
    protected $guarded = [];
    public $timestamps = false;
    protected $hidden = ['num_new_msg'];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['num_new_msg'];

    /**
     * accessor for num_new_msg
     */
    public function getNumNewMsgAttribute()
    {
        $profile = auth()->user()->profile;
        //if ($this->receiver_id == $profile->profile_id) {
        return $this->where([
            'create_chatid' => $this->create_chatid,
            'receiver_id' => $profile->profile_id,
            'receiver_deleted' => false,
            ['read', '!=', 'true'],
        ])->count();
        //}
        //return 0;

    }

    /**
     * accessor for chat_pics
     */
    public function getChatPicsAttribute($pics)
    {
        if (is_null($pics) || empty($pics)) {
            return [];
        }
        $chatimagesarr = json_decode($pics, true);
        $newarr = [];
        if (is_array($chatimagesarr) && count($chatimagesarr) > 0) {
            foreach ($chatimagesarr as $chatimagearr) {
                array_push($newarr, [
                    'chatpic' => url($chatimagearr['chatpicpath']),
                    'size' => $chatimagearr['size'],
                    'thumbchatpic' => url($chatimagearr['thumbchatpicpath']),
                ]);
            }
            return $newarr;
        }
        return $chatimagesarr;
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
     * belongsTo relationship between private chat and createchat
     */
    public function creator_chat()
    {
        return $this->belongsTo(CreateChat::class, 'create_chatid', 'chatid');
    }

    /**
     *
     */
    public function related_chats()
    {
        return $this->hasMany(PrivateChat::class, 'create_chatid', 'create_chatid')->orderBy('id', 'desc');
    }

}
