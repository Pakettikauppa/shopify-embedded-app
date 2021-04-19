@extends('layouts.default')

@section('card-content')

<form id="setting-form" method="POST" action="{{route('shopify.update-api')}}">
    <article>
        <div class="card" id="generic-card">
            <div class="row">
                <h1>{{trans('app.settings.api-settings-' . $type)}}</h1>
                <p>{!! trans('app.settings.api-info-' . $type) !!}</p>
            </div>
            <div class="row">
                <div class="input-group">
                    <span class="append">{{trans('app.settings.api_key')}}</span>
                    <input type="text" name="api_key" value="{{$shop->api_key}}">
                </div>
            </div>

            <div class="row">
                <div class="input-group">
                    <span class="append">{{trans('app.settings.api_secret')}}</span>
                    <input type="text" name="api_secret" value="{{$shop->api_secret}}">
                </div>
            </div>
        </div>
        @if (isset($error_message))
        <script>
            showToast({
                message:"<?php echo $error_message; ?>",
                isError:true
            });
        </script>
        @endif
    </article>
</form>

@endsection
