<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class PostComment extends Model
{
    //
    protected $guarded = [];
    public $timestamps = false;
    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['commentliked'];

    /**
     * mutator for created_at
     */
    public function setCreatedAtAttribute($value)
    {
        $this->attributes['created_at'] = time();
    }
    /**
     * mutator for updated_at
     */
    public function setUpdatedAtAttribute($value)
    {
        $this->attributes['updated_at'] = time();
    }
    /**
     * get accesss to posttimecreated and transform to user readable format
     */
    /*public function getCreatedAtAttribute($value)
    {
        $value = $value + 0;
        return Carbon::parse($value)->longAbsoluteDiffForHumans();
    }*/
    /**
     * mutator for commentid
     */
    public function setCommentidAttribute($commentid)
    {
        $this->attributes['commentid'] = md5(rand(3567, 26626266));
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
     * return true or false depending on if user has liked the comment
     */
    public function getCommentlikedAttribute($value)
    {
        $userprofile = auth()->user()->profile;
        return $this->likes()->where('liker_id', $userprofile->profile_id)->exists();
    }

    /**
     * function to establish eloquent relationship between postcomment model and profile model
     */
    public function profile()
    {
        return $this->belongsTo(Profile::class, 'commenter_id', 'profile_id');
    }

    /**
     * function to establish eloquent relationship between postcomment model and post model
     */
    public function owner_post()
    {
        return $this->belongsTo(Post::class, 'postid', 'postid');
    }
    /**
     * function to establish relationship between postccomment model and postcommentlike model
     */
    public function likes()
    {
        return $this->hasMany(PostCommentLike::class, 'commentid', 'commentid');
    }
    /**
     * eloquent function to get the number of replies for a comment
     */
    public function replies()
    {
        return $this->hasMany(PostCommentReply::class, 'originid', 'commentid');
    }

}
