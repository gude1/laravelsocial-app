<?php

namespace App;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use Notifiable;

    protected $guarded = [];
    //protected $with = ['profile'];

    /**
     * Mutator function for column userid
     */
    public function setUseridAttribute($userid)
    {
        $this->attributes['userid'] = bcrypt($userid);
    }

    /**
     * Accessor to username
     *
     */
    public function getUsernameAttribute($username)
    {
        return "@$username";
    }

    /**
     * Mutator function for column password
     *
     */
    public function setPasswordAttribute($password)
    {
        $this->attributes['password'] = bcrypt($password);
    }

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token', 'email',
        'phone', 'email_verified_at','created_at', 
        'updated_at','device_token'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName()
    {
        return 'userid';
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    /**
     * Function to establish relationship between user model and profile model
     *
     */
    public function profile()
    {
        return $this->hasOne(Profile::class, 'userid', 'userid');
    }

    /**
     * Get user's meet profile
     *
     */
    public function meet_setting()
    {
        return $this->hasOneThrough(
            MeetupSetting::class,
            Profile::class,
            'userid',
            'owner_id',
            'userid',
            'profile_id'
        );
    }

    /**
     * Function to establish relationship between user model and test model
     *
     */
    public function tests()
    {
        return $this->hasMany(Test::class, 'alpha', 'userid') ?
        dd('un')
        : dd('fsd');
    }

}
