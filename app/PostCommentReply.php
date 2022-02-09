<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PostCommentReply extends Model
{
    //
    protected $guarded = [];
    public $timestamps = false;
    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['replyliked', 'mentions'];

    /**
     * get accesss to posttimecreated and transform to user readable format
     */
    /* public function getCreatedAtAttribute($value)
    {
    $value = $value + 0;
    return Carbon::parse($value)->longAbsoluteDiffForHumans();
    }*/
    /**
     * mutator for commentid
     */
    public function getReplyLikedAttribute($r)
    {
        return null;
        $userprofile = auth()->user()->profile;
        return $this->likes()->where('liker_id', $userprofile->profile_id)->exists();
    }
    /**
     * mutator for commentid
     */
    public function setReplyidAttribute($replyid)
    {
        $this->attributes['replyid'] = md5(rand(3567, 26626266));
    }
    /**
     * get accesss to num likes
     */
    public function getNumLikesAttribute($value)
    {
        return $this->likes()->count();
    }

    /**
     * get accesss to num replies
     */
    public function getNumRepliesAttribute($value)
    {
        return $this->replies()->where(['deleted' => false])->count();
    }

    /**
     * get note mentions if exists
     */
    public function getMentionsAttribute()
    {
        return Notification::where('link', $this->replyid)->limit(20)->pluck('receipient_id', 'mentioned_name');
    }

    /**
     *eloquent  get parent  of postcomment reply
     */
    public function origin()
    {
        return $this->belongsTo(PostComment::class, 'originid', 'commentid');
    }
    /**
     * eloquent to get owner profile
     */
    public function profile()
    {
        return $this->belongsTo(Profile::class, 'replyer_id', 'profile_id');
    }
    /**
     * function to establish relationship between postccomment model and postcommentlike model
     */
    public function likes()
    {
        return $this->hasMany(PostCommentReplyLike::class, 'replyid', 'replyid');
    }
    /**
     * eloquent function to get the number of replies for a reply
     */
    public function replies()
    {
        return $this->hasMany(PostCommentReply::class, 'originid', 'replyid');
    }
}
