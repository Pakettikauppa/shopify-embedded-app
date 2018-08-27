@extends('layouts.default')

@section('card-content')

<form id="setting-form" method="GET" action="{{route('shopify.update-settings')}}">
    <article>
        <div class="card" id="generic-card">
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

    </article>
</form>

@endsection
