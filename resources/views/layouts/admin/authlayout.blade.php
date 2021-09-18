<!doctype html>

<html lang="{{ app()->getLocale() }}">

<head>

    <meta charset="utf-8">

    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="csrf-token" content="{{ csrf_token() }}">

     <link rel = "stylesheet" href="{{url('/css/w3.css')}}"/>

    <title>@yield('title')</title>
<style>
.flexcontainer{
 display:flex;
flex-direction : column;
align-items:center;
}
</style>
</head>
<body class='w3-light-grey w3-animate-left'>
<ul class='w3-bar w3-light-blue w3-ul w3-border-bottom'>
<li class='w3-bar-item'><h5>Admin </h5></li>
<li class='w3-bar-item'><a class='w3-button w3-hover-light-grey' href="{{url('/adminregisterscreen')}}">Register</a></li>
<li class='w3-bar-item'><a class='w3-button w3-hover-light-grey'  href="{{url('/adminloginscreen')}}">Login</a></li>
</ul>
<div class="  flexcontainer">
@yield('form')
</div>




</body>
</html>