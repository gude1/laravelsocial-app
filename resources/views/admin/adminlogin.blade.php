@extends('layouts.admin.authlayout')

@section('title','Admin login')

@section('form')
<h5 class=''>Admin Login</h5>
@if(Session::has('login_panelmsg'))
<div class='w3-panel w3-grey w3-text-black'>
<p>{{Session::get('login_panelmsg')}}</p>
</div>
@endif

<form  class="flexcontainer w3-container" action="/loginadmin" method="post">

<input value="{{old('admin_username')}}" class='w3-input' type="text" name="admin_username" 
maxLength=20 min=3 placeholder="eg: john"/>
<br/>

<input  value="{{old('password')}}" class='w3-input'  type='password'  name='password' maxLength=20 min=5 placeholder='*****'/>
<br/>
@isset($loginerror)
   <b class='w3-bold w3-text-red w3-small'>{{$loginerror}}</b><br/>
@endisset

@isset($loginmsg)
	  <b class='w3-bold w3-text-green w3-small'>{{$loginmsg}}</b><br/>
@endisset

@if(Session::has('rederror'))
<div class='w3-white w3-container w3-text-red w3-margin-bottom'>
<p>{{Session::get('rederror')}}</p>
</div>
@endif


<button type='submit' class='w3-btn w3-blue w3-round'>Login</button>
@csrf
</form>
@endsection
