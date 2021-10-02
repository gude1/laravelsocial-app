<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserActivity extends Model
{
  //
  protected $guarded = [];
  public $timestamps = false;

  /**
  * public function to save activity
  */
  public static function saveActivity() {
    $user = auth()->user();
    $profile = $user->profile;
    if (!$user || !$profile) {
      return false;
    }
    $date = date('d/m/y');
    $activity = $profile->activity_logs()->where('date', $date)->first();
    if ($activity) {
      $save = $activity->update([
        'login_duration' => $activity->login_duration + 1,
        'updated_at' => time(),
      ]);
    } else {
      $save = $profile->activity_logs()->create([
        'created_at' => time(),
        'updated_at' => time(),
        'date' => date('d/m/y'),
      ]);
    }
    if (!$save) {
      return false;
    }
    return true;
  }
}