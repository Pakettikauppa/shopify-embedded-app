@extends('layouts.default')

@section('card-content')
<style>
    .input-group .append {
        min-width: 15%;
    }
</style>
<form id="setting-form" method="POST" action="{{route('shopify.update-sender')}}">

    <article>
        <div class="card">
            <h2>{{trans('app.settings.company_info')}}</h2>
            <div class="row">
                <div class="input-group">
                    <span class="append">{{trans('app.settings.business_name')}}</span>
                    <input type="text" name="business_name" value="{{$shop->business_name}}">
                </div>
            </div>
            <div class="row">
                <div class="input-group">
                    <span class="append">{{trans('app.settings.address')}}</span>
                    <input type="text" name="address" value="{{$shop->address}}">
                </div>
            </div>
            <div class="row">
                <div class="input-group">
                    <span class="append">{{trans('app.settings.postcode')}}</span>
                    <input type="text" name="postcode" value="{{$shop->postcode}}">
                </div>
            </div>
            <div class="row">
                <div class="input-group">
                    <span class="append">{{trans('app.settings.city')}}</span>
                    <input type="text" name="city" value="{{$shop->city}}">
                </div>
            </div>
            <div class="row">
                <div class="input-group">
                    <span class="append">{{trans('app.settings.country')}}</span>
                    <select name="country">
                        {{--<option value="" disabled selected hidden>{{ trans('app.shipping_method.choose_sending_method') }}</option>--}}
                        @foreach(getCountryList() as $code => $country)
                            <option value="{{$code}}"  @if($shop->country == $code) selected @endif>
                                {{$country}}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="input-group">
                    <span class="append">{{trans('app.settings.email')}}</span>
                    <input type="email" name="email" value="{{$shop->email}}">
                </div>
            </div>
            <div class="row">
                <div class="input-group">
                    <span class="append">{{trans('app.settings.phone')}}</span>
                    <input type="text" name="phone" value="{{$shop->phone}}">
                </div>
            </div>
        </div>
    </article>

</form>

@endsection
