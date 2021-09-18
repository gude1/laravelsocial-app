<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProfileVisit extends Model
{
    //
    protected $guarded = [];
    public $timestamps = false;

    /**
     *belongsTo relatinship  to get proflile owner
     */
    public function profile_owner()
    {
        return $this->belongsTo(Profile::class, 'profile_owner_id', 'profile_id');
    }

    /**
     *belongsTo relatinship  to get visitor profile
     */
    public function visitor_profile()
    {
        return $this->belongsTo(Profile::class, 'visitor_id', 'profile_id');
    }
}
