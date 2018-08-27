@extends('layouts.default')

@section('card-content')

<article>
    <div class="info column">
        @if(session()->has('success'))
            <div class="alert success">
                <dl>
                    <dt>{{session()->get('success')}} #{{time()}}</dt>
                </dl>
            </div>
        @endif

        @if(!isset($shop->api_key))
            <div class="alert notification">
                <dl>
                    <dt>{{trans('app.messages.no_api')}} #{{time()}}</dt>
                    <dd>{{trans('app.messages.only_test_mode')}}</dd>
                </dl>
            </div>
        @endif

        @if(!$api_valid)
            <div class="alert error">
                <dl>
                    <dt>{{trans('app.messages.invalid_credentials')}} #{{time()}}</dt>
                </dl>
            </div>
        @endif

        @if(session()->has('error'))
            <div class="alert error">
                <dl>
                    <dt>{{session()->get('error')}} #{{time()}}</dt>
                </dl>
            </div>
        @endif
    </div>
</article>

<form id="setting-form" method="GET" action="{{route('shopify.update-settings')}}">

    <article>
        <div class="card">
            <h2>{{trans('app.settings.shipment_settings')}}</h2>

            <div class="row" style="margin-bottom: 2em">
                <div class="columns four rate-name-column">
                    {{trans('app.settings.default_shipping_method')}}
                </div>
                <div class="columns eight">
                    <div class="row">
                        {{--<div class="input-group">--}}
                        {{--<span class="append">{{trans('app.settings.shipping_method')}}</span>--}}
                        <select name="default_shipping_method">
                            <option value="">—</option>
                            @foreach($shipping_methods as $key => $service_provider)
                                @if(count($service_provider) > 0)
                                    <optgroup label="{{$key}}">
                                        @foreach($service_provider as $product)
                                            <option value="{{ $product['shipping_method_code'] }}" data-services="{{json_encode($product['additional_services'])}}"
                                                    @if($shop->default_service_code == $product['shipping_method_code']) selected @endif>
                                                {{ $product['name'] }}
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endif
                            @endforeach
                        </select>
                        {{--</div>--}}
                    </div>
                </div>
            </div>

            <div>
                <div class="columns four">
                    <h5>{{trans('app.settings.shopify_method')}}</h5>
                </div>
                <div class="columns eight">
                    <h5>{{trans('app.settings.pk_method')}}</h5>
                </div>
            </div>

            @foreach($shipping_rates as $rate)

            <div class="row">
                <div class="columns four rate-name-column">
                    {{$rate['zone']}}: {{$rate['name']}}
                </div>
                <div class="columns eight">
                    <div class="row">
                        {{--<div class="input-group">--}}
                            {{--<span class="append">{{trans('app.settings.shipping_method')}}</span>--}}
                            <select name="shipping_method[{{$rate['name']}}]">
                                <option value="">—</option>
                                @foreach($shipping_methods as $key => $service_provider)
                                    @if(count($service_provider) > 0)
                                        <optgroup label="{{$key}}">
                                            @foreach($service_provider as $product)
                                                <option value="{{ $product['shipping_method_code'] }}" data-services="{{json_encode($product['additional_services'])}}"
                                                        @if($rate['product_code'] == $product['shipping_method_code']) selected @endif>
                                                    {{ $product['name'] }}
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    @endif
                                @endforeach
                            </select>
                        {{--</div>--}}
                    </div>
                </div>
            </div>

            @endforeach
        </div>
    </article>

            <article>
                <div class="card">

                <div class="row">
            <label><input type="checkbox" name="print_return_labels" @if($shop->always_create_return_label) checked @endif value="1">{{trans('app.settings.print_return_labels')}}</label>
        </div>
        </div>

    </article>

</form>

@endsection
