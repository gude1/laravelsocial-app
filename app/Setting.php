<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    //
    protected $guarded = [];

    /***
     * public function to establish relation between setting model and profile model
     */
    public function profile(){
        return $this->belongsTo(Profile::class,'profile_id','profile_id');
    }
    
}
