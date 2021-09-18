@php
$admin = Auth::guard('admin')->user();
//dd(request()->fullUrl());
$validated = Auth::guard('admin')->check();
$base_url = url('/admindashboard/profiles');
$links = [
['url' => $base_url,'name' => 'All'],
['url' => null,'name'=>'Logins'],
['url' => "$base_url/bygender?q=male",'name'=>'Male'],
['url' => "$base_url/bygender?q=female",'name'=>'Female'],
['url' => null,'name'=>'IProfiles'],
['url' => null,'name'=>'FollowInfo'],
['url' => null,'name'=>'ProfileVisitInfo']
];
@endphp

@extends('layouts.admin.markup')

@section('title','Admin Dashboard|Profiles')

@section('stickyheader')
@endsection

@section('body')
<!--navbar-->
<div class='' style="width:70%;margin:auto;">
@includeIf('admin.includes.dashboardnav',
compact('links'))
</div>
<!--navbar-->

<div id='profilectn'>
@if(isset($users) && count($users) > 0)
@includeIf('admin.includes.displayuser',[
     'displayusersarr' => $users
  ])
@else
<p class=' w3-center w3-text-gray w3-large'>No results yet</p>
@endif
</div>

@endsection