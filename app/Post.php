<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    //
    protected $guarded = [];
    public $timestamps = false;

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['postliked', 'postshared', 'mentions'];


    /**
     * get accesss to postimages json_decoding the json postimages containing
     * 
     */
    public function getPostImageAttribute($value)
    {
        $postimagesarr = json_decode($value, true);
        $newarr = [];
        if (is_array($postimagesarr) && count($postimagesarr) > 0) {
            foreach ($postimagesarr as $postimagearr) {
                array_push($newarr, [
                    'postimage' => url($postimagearr['postimagepath']),
                    'thumbnailpostimage' => url($postimagearr['thumbimagepath']),
                ]);
            }
            return $newarr;
        }
        return $postimagesarr;
    }

    /**
     * to know if user has liked post
     */
    public function getPostlikedAttribute()
    {
        $user = auth()->user();
        if ($user) {
            $likestatus = $this->postlikes()->where('liker_id', $user->profile->profile_id)->exists();
            return $likestatus ? 'postliked' : 'notliked';
        }
    }

    /**
     * to know if user has shared post
     */
    public function getPostsharedAttribute()
    {
        $user = auth()->user();
        if ($user) {
            $likestatus = $this->postshares()->where('sharer_id', $user->profile->profile_id)->exists();
            return $likestatus ? 'postshared' : 'notshared';
        }
    }

    public function getMentionsAttribute()
    {
        return Notification::where('link', $this->postid)->limit(20)->pluck('mentioned_name', 'receipient_id');
    }

    /**
     * return number of postlikes
     */
    public function getNumPostLikesAttribute($value)
    {
        return $this->postlikes()->count();
    }
    /**
     * return number of postlikes
     */
    public function getNumPostSharesAttribute($value)
    {
        return $this->postshares()->count();
    }
    /**
     * return number of postcomments
     */
    public function getNumPostCommentsAttribute($value)
    {
        return $this->comments()->where(['deleted' => false])->count();
    }

    /**
     * eloquent function to establish relationship between post and profile
     *
     */
    public function profile()
    {
        return $this->belongsTo(Profile::class, 'poster_id', 'profile_id');
    }

    /**
     * eloquent function to establish relationship between post and comments
     *
     */
    public function comments()
    {
        return $this->hasMany(PostComment::class, 'postid', 'postid');
    }

    /**
     * postid mutator
     */
    public function setPostidAttribute($postid)
    {
        $this->attributes['postid'] = md5(rand(377373, 73838));
    }
    /**
     * has many relationship between post and postlikes
     */
    public function postlikes()
    {
        return $this->hasMany(PostLike::class, 'postid', 'postid');
    }
    /**
     * has many relationship between post and postshares
     */
    public function postshares()
    {
        return $this->hasMany(PostShare::class, 'postid', 'postid');
    }

    /**
     * has may through relationship to get post sharers profile
     */
    public function post_sharers_profile()
    {
        return $this->hasManyThrough(
            Profile::class, //final model
            PostShare::class, //intermediate model
            'postid', // relationship column on the intermdiate model that connects to this particular model
            'profile_id', //relationship column on the final model that connects to the intermediate model
            'postid', //value of a colum in this particular model expected in the relationship column of intermediate
            'sharer_id', //value of a column in the intermediate model expected in relationship column of final
        );
    }

    /**
     * has may through relationship to get post likers profile
     */
    public function post_likers_profile()
    {
        return $this->hasManyThrough(
            Profile::class, //final model
            PostLike::class, //intermediate model
            'postid', // relationship column on the intermdiate model that connects to this particular model
            'profile_id', //relationship column on the final model that connects to the intermediate model
            'postid', //value of a colum in this particular model expected in the relationship column of intermediate
            'liker_id', //value of a column in the intermediate model expected in relationship column of final
        );
    }
}
