@extends('layouts.app')

@section('content')

<section>
    <div class="row" style="padding-top: 1em; width: 100%;">
        <div class="alert {{$type}}">
            <dl>
                <dt>{{$title}}</dt>
                <dd>{!!$message!!}</dd>
            </dl>
        </div>
    </div>
</section>



@endsection

@section('after-style-end')

@endsection

@section('after-scripts-end')

<script type='text/javascript'>

    ShopifyApp.ready(function(){
        ShopifyApp.Bar.initialize({
            title: '{{trans('app.messages.error')}}',
            icon: '{{url('/img/favicon-96x96.png')}}'
        });
    });

</script>

@endsection