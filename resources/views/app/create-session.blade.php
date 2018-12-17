<html>
<body>
<form action="{{route('shopify.auth.index')}}" method="get">
    @foreach($params as $key => $value)
        @if($key != '_pk_s')
        <input type="hidden" name="{{$key}}" value="{{$value}}">
        @endif
    @endforeach

    <button type="submit">Jos tämä sivu jää näkyviin, paina tästä</button>
</form>
</body>
<script>
    document.getElementById("form").submit();
</script>
</html>