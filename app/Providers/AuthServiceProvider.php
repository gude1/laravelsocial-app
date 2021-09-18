<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        // 'App\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();
        // return true if user is blocked and false if user is not blocked
        Gate::define('others_verify_block_status', function ($user, $profile) {
            $authuserprofile = $user->profile;
            if (is_null($profile->profile_settings) || empty($profile->profile_settings)) {
                return false;
            }
            $blocked_profiles = $profile->profile_settings->blocked_profiles;

            if (in_array($authuserprofile->profile_id, $blocked_profiles)) {
                return true;
            } else {
                return false;
            }
        });

        //return true if user has blocked the profile return false if user has  not blocked
        Gate::define('user_verify_block_status', function ($user, $otherprofile) {
            $userprofile = $user->profile;
            if (is_null($userprofile->profile_settings) || empty($userprofile->profile_settings)) {
                return false;
            }
            $blocked_profiles = $userprofile->profile_settings->blocked_profiles;
            if (in_array($otherprofile->profile_id, $blocked_profiles)) {
                return true;
            } else {
                return false;
            }
        });

        // return true if user is  muted and false if user is not muted
        Gate::define('verify_mute_status', function ($profile) {
            $authuserprofile = auth()->user()->profile;
            if (is_null($profile->profile_settings) || empty($profile->profile_settings)) {
                return false;
            }
            $blocked_profiles = $profile->profile_settings->blocked_profiles;

            if (in_array($authuserprofile->profile_id, $blocked_profiles)) {
                return true;
            } else {
                return false;
            }
        });
    }
}
