@php

$links = [
['url' => request()->url(),'name' => 'Users'],
['url' => '','name' => 'Posts' ],
['url' => '','name' => 'Chats'],
['url' => '','name' => 'Feedback'],
['url' => '','name' => 'MeetupRequest'],
];

@endphp
<!doctype html>

<html lang="{{ app()->getLocale() }}">

<head>

    <meta charset="utf-8">

    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="csrf-token" content="{{ csrf_token() }}">

     <link rel = "stylesheet" href="{{url('/css/w3.css')}}"/>
<style>
#stickyHeader{
}

#body{
margin-Top:140px;
min-height:300px;
width:100%;
height:100%;
}

</style>
<title>@yield('title')</title>
</head>
<body class='w3-animate-left w3-light-grey'>

<!--sticky header-->
<div id='stickyHeader' class='w3-top'>
<div class='w3-bar w3-white w3-container w3-border w3-border-bottom'>
<h5 class='w3-bar-item'>DashBoard</h5>
<p class='w3-btn w3-bar-item w3-right' style="color:silver;">
{{$admin->admin_username}}
</p>
</div>

<!--navbar-->
@includeIf('admin.includes.dashboardnav',compact('links'))
<!--navbar-->

@yield('stickyheader')
</div>
<!--stickyheader-->

<!--body-->
<div id='body'>
@yield('body')
</div>
<!--body-->
</body>
</html>