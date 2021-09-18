<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use App\Profile;
use App\User;
use Illuminate\Http\Request;

class AccessProfileController extends Controller
{
	
	
	public function __construct() { 
		$this->middleware('auth.admin');
	 }
	

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
       
    }

    public function fetchUsers()
    {
	
	    $users = User::with('profile')
	   ->orderBy('id','desc')->paginate(10);
	    $users->res_info='All users';
	    return view('admin.admindashboardprofile',
	           compact('users'));
	}
	
	public function fetchUnApprovedUsers()
	{
		$users = User::where([
		  'approved' => false,
		  'suspended' => true
		])->paginate(10);
		 return view('admin.admindashboardprofile',
	           compact('users'));
	}
	
	public function fetcthProfilesByGender(Request $req){
	     $gender = $req->q;
	    if(!in_array($gender,['male','female'])){
		    return back()->with('profileerrmsg','Request failed invalid gender value');
		}
	    $users = User::with('profile')
	       ->where('gender',$gender)
	       ->orderBy('id','desc')
	       ->paginate(10);
	 //  $users->res_info="$gender users";
	   return view('admin.admindashboardprofile',
	           compact('users'));
		
	}
	
	public function fetchProfilesByLogin(Request $req)
	{
	   $order = $req->order;
	   if(!in_array($order,['desc','asc'])){
		    back() ->with('profilerrmsg','order param empty');
		}
		//$profiles =  ;
	}
	

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Profile  $profile
     * @return \Illuminate\Http\Response
     */
    public function show(Profile $profile)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Profile  $profile
     * @return \Illuminate\Http\Response
     */
    public function edit(Profile $profile)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Profile  $profile
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Profile $profile)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Profile  $profile
     * @return \Illuminate\Http\Response
     */
    public function destroy(Profile $profile)
    {
        //
    }
}
