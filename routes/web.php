<?php
use App\Events\NotifyEvent;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
 */

Route::get('/', function () {
    return 'request sent';
});

Route::get('/eve', function () {
  broadcast(new NotifyEvent('GOD IS GOOD'));
  return 'request sent';
});

Route::get('adminregisterscreen', 'Admin\AdminUserController@viewRegister');
Route::get('/adminloginscreen', 'Admin\AdminUserController@viewLogin');

Route::prefix('admindashboard')->middleware('auth.admin')->group(function () {
    Route::prefix('profiles')->group(function () {
        Route::get('/', 'Admin\AccessProfileController@fetchUsers');
        Route::get('/bygender', 'Admin\AccessProfileController@fetcthProfilesByGender');
    });
});

Route::match(['get', 'post'], '/registeradmin', 'Admin\AdminUserController@store');
Route::match(['get', 'post'], '/loginadmin', 'Admin\AdminUserController@logAdmin');
