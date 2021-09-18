<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Reply extends Model
{
    //
    protected $guarded = [];

    /**
     *  replyid  mutator
     */
    public function setReplyidAttribute($replyid){
        $this->attributes['replyid'] = md5(rand(823823,8883830));
    }

    /**
     * public function to establish relationship between reply
     *  and its origin model(postcomment, reply, etc)
     */
    public function origin(){
       $p = $this->belongsTo(PostComment::class,'originid','commentid');
       $r = $this->belongsTo(Reply::class,'originid','replyid');
       return  is_null($p->first()) && !is_null($r->first()) ? $r : $p;  
    }

    /**
     * public function to establish eloquent relationship among reply model and profile model
     */
    public function profile(){
        return $this->belongsTo(Profile::class,'replyerid','profile_id');
    }
}
