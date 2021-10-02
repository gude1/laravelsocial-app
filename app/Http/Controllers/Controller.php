<?php

namespace App\Http\Controllers;

use App\Profile;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
  use AuthorizesRequests,
  DispatchesJobs,
  ValidatesRequests;

  public static function checkStatus ($profile_id, $other_profile_id, $case) {
    if (empty($profile_id) || is_null($profile_id) || empty($other_profile_id) || is_null($other_profile_id) || $profile_id == $other_profile_id) {
      return [
        'status' => 0,
        'errmsg' => 'argument is invalid',
      ];
    }
    $profile1 = Profile::with(['user'])->firstWhere('profile_id', $profile_id);

    $profile2 = Profile::with(['user'])->firstWhere('profile_id', $other_profile_id);

    if (is_null($profile1) || is_null($profile2) || is_null($profile2->user)) {
      return [
        'status' => 0,
        'errmsg' => 'profiles not found',
      ];
    }
    if ($profile2->user->approved != true || $profile2->user->suspended == true || $profile2->user->deleted == true) {
      return [
        'status' => 0,
        'errmsg' => 'profile not found it might have being suspended or deleted',
      ];
    }
    switch ($case) {
      case 'a':
        if ($profile2->ublockedprofile == true) {
          return [
            'status' => 0,
            'errmsg' => "You blocked profile {$profile2->profile_name}",
          ];
        }
        break;
      case 'b':
        if ($profile2->profileblockedu == true) {
          return [
            'status' => 0,
            'errmsg' => "Profile {$profile2->profile_name} has you blocked",
          ];
        }
        break;
      default:
        if ($profile2->ublockedprofile == true) {
          return [
            'status' => 0,
            'errmsg' => "You blocked profile {$profile2->profile_name}",
          ];
        } elseif ($profile2->profileblockedu == true) {
          return [
            'status' => 0,
            'errmsg' => "Profile {$profile2->profile_name} has you blocked",
          ];
        }
        break;
    }

    return [
      'status' => 1,
      'msg' => "checked",
    ];
  }
}