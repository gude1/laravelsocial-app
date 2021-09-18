<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PageVisits extends Model
{
    //
    public $timestamps = false;
    protected $guarded = [];

    public static function saveVisit($pagename = "")
    {
        $user = auth()->user();
        $profile = $user->profile;
        if (!$user || !$profile || empty($pagename) || is_null($pagename)) {
            return false;
        }
        $save = PageVisits::create([
            'profile_id' => $profile->profile_id,
            'pagename' => $pagename,
            'created_at' => time(),
            'updated_at' => time(),
        ]);
        if (!$save) {
            return false;
        }
        return true;
    }
}
