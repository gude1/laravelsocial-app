<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PostCommentReplyLike extends Model
{
    //
    protected $guarded = [];
    public $timestamps = false;
    /**
     * eloqunent relationship between profile and PostcommentReplyLike
     */
    public function profile(){
        return $this->belongsTo(Profile::class,'liker_id','profile_id');
    }
}
