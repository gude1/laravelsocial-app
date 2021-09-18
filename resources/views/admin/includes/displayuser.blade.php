<style>
#displayUserCtn{
display:flex;
flex-direction:column;
align-items:center;
}

.displayUserCard{
width:80%;
max-width:450px;
display:flex;
flex-direction:column;
justify-content:space-around;
padding:30px 10px;
align-items:center
}

.displayUserCardImg{
width:70px;
height:70px;
}

.displayUserCardText{
margin:3px;
}

</style>

@if(isset($displayusersarr) && count($displayusersarr))
<div id="displayUserCtn" class=''>

@foreach($displayusersarr as $user)
@php
$img = $user->profile->avatar[0];
@endphp
<div  class="displayUserCard  w3-panel w3-card">
<img class="w3-image w3-circle displayUserCardImg" src="{{url($img ?? '/images/default.png')}}" alt="Good lord">
<p class='displayUserCardText'>Name : <b>{{$user->name}}</b></p>
<p class='displayUserCardText'>Username : <b>{{$user->username}}</b></p>
<p class='displayUserCardText'>
Email: <b>{{$user->email}}</b>
</p>
<p class='displayUserCardText'>
Gender :<b>{{$user->gender}}</b>
</p>
<p class='displayUserCardText'>
Phone :<b>{{$user->phone}}</b>
</p>

<p class='displayUserCardText'>
UserRow id :<b>{{$user->id}}</b>
</p>

<p class='displayUserCardText'>
ProfileRow id :<b>{{$user->profile->id}}</b>
</p>

<p class='displayUserCardText'>
ProfileName :<b>{{$user->profile->profile_name}}</b>
</p>
<p class='displayUserCardText'>
Campus: <b>{{$user->profile->campus}}</b>
</p>

</div>
@endforeach


</div>
@endif