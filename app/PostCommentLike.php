<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PostCommentLike extends Model
{
    //
    protected $guarded = [];
    public $timestamps = false;
    /**
     * eloquent relationship to establish relation between postcommentlike and postcomment
     */
    public function owner_comment(){
        return $this->belongsTo(PostComment::class,'commentid','commentid');
    }

    /**
     * eloquent relationship to establish relation between postcommentlike and likerprofile
     *
     */
    public function profile(){
        return $this->belongsTo(Profile::class,'liker_id','profile_id');
    }
}
