<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CreateChat extends Model
{
    //
    public $timestamps = false;
    protected $guarded = [];

    /**
     * Defines hasMany eloquent relationship between CreateChat and  PrivateChat
     */
    public function private_chats()
    {
        return $this->hasMany(PrivateChat::class, 'create_chatid', 'chatid');
    }

    /**
     * Defines hasOne eloquent relationship  that return chat initiator profile
     */
    public function initiator_profile()
    {
        return $this->hasOne(Profile::class, 'profile_id', 'profile_id1');
    }

    /**
     * Defines hasOne eloquent relationship  that return chat receipient profile
     */
    public function receipient_profile()
    {
        return $this->hasOne(Profile::class, 'profile_id', 'profile_id2');
    }

}
