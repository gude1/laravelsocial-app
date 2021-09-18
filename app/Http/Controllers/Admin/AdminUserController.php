<?php

namespace App\Http\Controllers\Admin;

use App\Admin\AdminUser;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AdminUserController extends Controller
{
    protected $validated = false;

    public function __construct()
    {
        $this->middleware('auth.admin')
            ->except(['store', 'logAdmin']);
        $this->validated = Auth::guard('admin')->check();
    }

    /**
     *
     *public function to return register view
     */
    public function viewRegister()
    {
        if (Auth::guard('admin')->check()) {
            return redirect('/admindashboard/profiles');
        }
        return view('admin.adminregister');
    }

    /**
     *
     *public function to return login view
     */
    public function viewLogin()
    {
        //return [Auth::guard('admin')->check()];
        //return [$this->validated];
        if (Auth::guard('admin')->check()) {
            return redirect('/admindashboard/profiles');
        }
        return view('admin.adminlogin');
    }

    /**
     *
     *public  function to return dashboard view
     */
    /*public function viewDashboard()
    {
    return view('admin.admindashboard');
    }*/

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validate = $request->validate([
            'admin_username' => 'bail|required|between:3,20|string|unique:admin_users',
            'password' => 'bail|required|between:5,20|alpha_num',
        ]);

        $adminuser = new AdminUser();
        $adminuser->admin_username = $request->admin_username;
        $adminuser->password = Hash::make($request->password);
        $adminuser->approved = true;
        $adminuser->admin_id = md5(rand(253, 151717171));

        if (!$adminuser->save()) {
            return back()->with('rederror',
                'could not create admin please try again later');
        }

        return redirect('/adminloginscreen')
            ->with('login_panelmsg', 'Signup success login here!');
    }

    /**
     * to login admin_user in
     * @param  \Illuminate\Http\Request  $req
     */
    public function logAdmin(Request $req)
    {
        $credentials = array_merge(
            $req->only('admin_username', 'password'),
            ['approved' => true]
        );
        // Auth::guard('admin')->attempt($credentials);
        //dd(Auth::guard('admin')->check());

        if (Auth::guard('admin')->attempt($credentials)) {
            return redirect()->intended('/admindashboard');
        } else {
            return back()->with('rederror', 'invalid email or password');
        }

    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Admin\AdminUser  $adminUser
     * @return \Illuminate\Http\Response
     */
    public function show(AdminUser $adminUser)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Admin\AdminUser  $adminUser
     * @return \Illuminate\Http\Response
     */
    public function edit(AdminUser $adminUser)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Admin\AdminUser  $adminUser
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, AdminUser $adminUser)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Admin\AdminUser  $adminUser
     * @return \Illuminate\Http\Response
     */
    public function destroy(AdminUser $adminUser)
    {
        //
    }
}
