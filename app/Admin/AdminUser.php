<?php

namespace App\Admin;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class AdminUser extends Authenticatable
{
    //
    use Notifiable;
    protected $guard = "admin";
    protected $guarded = [];
}
