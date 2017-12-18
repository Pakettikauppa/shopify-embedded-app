@extends('layouts.app')

@section('content')

<section>
    <div class="column">
        <div class="card" style="margin-top: 1em">
            <div class="row">
                <h2>{{$error_messgae}}</h2>
                <a href="{{$orders_url}}" target="_blank">{{trans('app.print_labels.back_to_orders')}}</a>
            </div>
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
            title: '{{trans('app.error.' . $page_title)}}',
            icon: '{{url('/img/favicon-96x96.png')}}'
        });

    });

</script>

@endsection