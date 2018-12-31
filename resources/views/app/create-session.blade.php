<html>
<body>
<form id="form" action="{{route('shopify.auth.index')}}" method="get">
    @foreach($params as $key => $value)
        @if($key != '_pk_s')
            @if(is_array($value))
                @foreach($value as $_arrValue)
                    <input type="hidden" name="{{$key}}[]" value="{{$_arrValue}}">
                @endforeach
            @else
                <input type="hidden" name="{{$key}}" value="{{$value}}">
            @endif
        @endif
    @endforeach

    <button type="submit">Jos tämä sivu jää näkyviin, paina tästä</button>
</form>
</body>
</html>