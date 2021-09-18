<style>
.topnavitem{
margin:5px;
width:100%;
text-decoration:none;

}
</style>

@if(isset($links) && count($links) > 0)
	
<div 
class= 'w3-bar w3-teal w3-container '
style="display:flex;flex-direction:row;width:100%;overflow-x:auto;">

@foreach($links as $link)
 @if(request()->fullUrl() == $link['url'])
   <a  href="{{$link['url']}}" style=""  
       class='topnavitem w3-bar-item w3-center w3-blue'>
        {{$link['name']}}
</a>
@else 
   <a  href="{{$link['url']}}" style=""  
       class='topnavitem w3-bar-item w3-center w3-hover-blue'>
        {{$link['name']}}
</a>
@endif
@endforeach

</div>

@endif
