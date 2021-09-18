<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProfileSetting extends Model
{
    //
    protected $guarded = [];
    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'created_at', 'updated_at', 'muted_profiles', 'blocked_profiles',
    ];
    /**
     * get blocked_profiles
     */
    public function getBlockedProfilesAttribute($val)
    {
        return json_decode($val, true);
    }
    /**
     * get muted_profiles
     */
    public function getMutedProfilesAttribute($val)
    {
        return json_decode($val, true);
    }

    /**
     * eloquent relationship between profile and profilesettings
     */
    public function owner_profile()
    {
        return $this->belongsTo(Profile::class, 'profile_id', 'profile_id');
    }
}
