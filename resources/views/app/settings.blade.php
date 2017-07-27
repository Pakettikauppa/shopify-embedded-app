@extends('layouts.default')

@section('card-content')

<article>
    <div class="info column">
        @if(!isset($shop->api_key))
            <div class="alert notification">
                <dl>
                    <dt>{{trans('app.messages.no_api')}}</dt>
                    <dd>{{trans('app.messages.only_test_mode')}}</dd>
                </dl>
            </div>
        @endif

        @if(!$api_valid)
            <div class="alert error">
                <dl>
                    <dt>{{trans('app.messages.invalid_credentials')}}</dt>
                </dl>
            </div>
        @endif
        {{--<span class="tag green" style="margin-bottom: 15px;">{{trans('app.messages.ready')}}</span>--}}

        @if(session()->has('error'))
            <div class="alert error">
                <dl>
                    <dt>{{session()->get('error')}}</dt>
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
                    {{$rate['name']}}
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

    <article>
        <div class="card">
            <h2>{{trans('app.settings.cash_on_delivery')}}</h2>
            <div class="row">
                <div class="input-group">
                    <span class="append">{{trans('app.settings.iban')}}</span>
                    <input type="email" name="iban" value="{{$shop->iban}}">
                </div>
            </div>
            <div class="row">
                <div class="input-group">
                    <span class="append">{{trans('app.settings.bic')}}</span>
                    <input type="text" name="bic" value="{{$shop->bic}}">
                </div>
            </div>
        </div>

    </article>

    <article>
        <div class="card">
            <h2>{{trans('app.settings.testing')}}</h2>
            <div class="row">
                <label><input type="checkbox" name="test_mode" @if($shop->test_mode) checked @endif value="1">{{trans('app.settings.test_mode')}}</label>
            </div>
        </div>

    </article>
</form>

@endsection

@section('after-style-end')

@endsection

@section('after-scripts-end')

<script type='text/javascript'>

    ShopifyApp.ready(function(){
        ShopifyApp.Bar.initialize({
            title: '{{trans('app.settings.settings')}}',
            icon: '{{url('/img/favicon-96x96.png')}}',
            buttons: {
                primary: {
                    label: "{{trans('app.settings.save_settings')}}",
                    callback: function(){
                        $('#setting-form').submit();
                    }
                },
                secondary: [{
                    label: "{{trans('app.settings.setup_wizard')}}",
                    href: '{{route('shopify.setup-wizard')}}'
                }]
            }
        });
    });

    document.addEventListener('DOMContentLoaded', ready);
    function ready(){
        showServices();
        function showServices(){
            services = $('select[name=shipping_method] option:selected').data('services');
            $checkboxes = $('.additional-services input[type=checkbox]').prop('disabled', true);
            $('.additional-services .checkbox-label').hide();

            $.each(services, function(index, service){
                $checkboxes.each(function(){
                    $this = $(this);
                    if(service.service_code == $this.val()){
                        $this.prop('disabled', false).closest('label').show();
                    }
                });
            });

            if($('.additional-services .checkbox-label:visible').length == 0){
                $('.additional-services .title-label').hide();
            }else{
                $('.additional-services .title-label').show();
            }
        }
        $('select[name=shipping_method]').change(showServices);
    }

</script>

@endsection