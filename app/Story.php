<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Story extends Model
{
    protected $guarded = [];

    /**
     * Set the story id
     * @return void
     */
    public function setStoryidAttribute(){
        $this->attributes['storyid'] = md5(rand(377373,73838));
    }
    //
    protected $guardable = [];
    /**
     * public function to establish relationship between story model and profile model
     */
    public function profile(){
        return $this->belongsTo(Profile::class,'poster_id','profile_id');
    }
    /**
     * public function to establish relationship between story model and storycomment model
     */
    public function comments(){
        return $this->hasMany(StoryComment::class,'storyid','storyid');
    }
}
