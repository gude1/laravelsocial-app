@extends('layouts.admin.authlayout')

@section('title','Admin Register')

@section('form')
<h5 class=''>Register as Admin</h5>

<form  class="flexcontainer w3-container" action="/registeradmin" method="post">

<input value="{{old('admin_username')}}" class='w3-input' type="text" name="admin_username" 
maxLength=20 min=3 placeholder="eg: john"/>

<b class='w3-text-red w3-small'>{{$errors->first('admin_username')}}</b>

<br/>

<input  value="{{old('password')}}" class='w3-input'  type='password'  name='password' maxLength=20 min=5 placeholder='*****'/>

<b class='w3-text-red w3-small'>{{$errors->first('password')}}</b>
<br/>

<button type='submit' class='w3-btn w3-blue w3-round'>Register</button>

@if(Session::has('rederror'))
<div class='w3-panel w3-light-grey w3-text-red'>
<p>{{Session::get('rederror')}}</p>
</div>
@endif

@csrf
</form>
@endsection
