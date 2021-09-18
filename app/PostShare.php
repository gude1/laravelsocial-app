<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PostShare extends Model
{
    protected $guarded = [];
    /**
     * eloquent relationship between postshare and post
     */
    public function ownerpost()
    {
        return $this->belongsTo(Post::class, 'postid', 'postid');
    }
    /**
     * eloquent relationship to establish relation between postshare and likerprofile
     *
     */
    public function profile()
    {
        return $this->belongsTo(Profile::class, 'sharer_id', 'profile_id');
    }
}
