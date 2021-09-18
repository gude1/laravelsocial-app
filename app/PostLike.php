<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PostLike extends Model
{
    protected $guarded = [];

    /**
     * eloqueny function  to establish relationship between PostLike Model and Post Model
     */
    public function ownerpost(){
        return $this->belongsTo(Profile::class,'postid','postid');
    }
    /**
     * eloquent relationship to establish relation between postlike and likerprofile
     *
    */
    public function profile(){
        return $this->belongsTo(Profile::class,'liker_id','profile_id');
    }
    
}
