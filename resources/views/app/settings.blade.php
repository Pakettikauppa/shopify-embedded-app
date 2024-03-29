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
            <!-- Pickup points 1 -->
            <h2>{{trans('app.settings.pickuppoints.title')}}</h2>
            @if ($shop->carrier_service_id == null)
            {{trans('app.settings.enable_carrier_api')}}
            @else
            <div class="row">
                <div class="input-group">
                       <table>
                           <tr>
                               <th colspan="2">
                                   {{trans('app.settings.pickuppoints.provider')}}
                               </th>
                               <th>
                                   {{trans('app.settings.pickuppoints.base_price')}}
                               </th>
                               <th>
                                   {{trans('app.settings.pickuppoints.trigger_price')}}
                               </th>
                               <th>
                                   {{trans('app.settings.pickuppoints.triggered_price')}}
                               </th>
                           </tr>
                            @foreach($shipping_methods as $key => $_service_provider)
                                <tr>
                                    <td>
                                        <input type="hidden" name="pickuppoint[{{$key}}][active]" value="false">
                                        <input type="checkbox" name="pickuppoint[{{$key}}][active]" value="true" @if($pickuppoint_settings[$key]['active'] == 'true') checked @endif>
                                    </td>
                                    <td>
                                        {{$key}}
                                    </td>
                                    <td>
                                        <input type="number" name="pickuppoint[{{$key}}][base_price]" value="{{$pickuppoint_settings[$key]['base_price']}}">
                                    </td>
                                    <td>
                                        <input type="number" name="pickuppoint[{{$key}}][trigger_price]" value="{{$pickuppoint_settings[$key]['trigger_price']}}">
                                    </td>
                                    <td>
                                        <input type="number" name="pickuppoint[{{$key}}][triggered_price]" value="{{$pickuppoint_settings[$key]['triggered_price']}}">
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                </div>
            </div>
            <div class="row">
                <div class="input-group">
                    <span class="append">{{trans('app.settings.pickuppoints_count')}}</span>
                    <select name="pickuppoints_count">
                     @for($i=0; $i<11; $i++)
                        <option value="{{$i}}" @if($shop->pickuppoints_count == $i) selected @endif>
                            @if($i == 0)
                                {{trans('app.settings.pickuppoints_count_0')}}
                            @else
                                {{$i}}
                            @endif
                        </option>
                     @endfor
                    </select>
                </div>
            </div>
        </div>
        @endif
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

<!--
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
-->
    <article>
        <div class="card">
            <h2>{{trans('app.settings.settings')}}</h2>
            <div class="row">
                <div class="input-group">
                    <span class="append">{{trans('app.settings.language')}}</span>
                    <select name="language">
                        @foreach(getLanguageList() as $code => $language)
                            <option value="{{$code}}"  @if($shop->locale == $code) selected @endif>
                                {{$language}}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="row">
                <label><input type="checkbox" name="print_return_labels" @if($shop->always_create_return_label) checked @endif value="1">{{trans('app.settings.print_return_labels')}}</label>
            </div>

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
                secondary: [
                    { label: "Testitila on päällä", callback: function(){ alert('help'); } },
                    { label: "Ajankohtaista", href: "http://my-app.com/preview_url", target: "new" },
                    { label: "Asetukset",
                        type: "dropdown",
                        links: [
                            { label: "Lähetysasetukset", href: "{{route('shopify.settings.shipping-link')}}", target: "app" },
                            { label: "Noutopisteasetukset", href: "{{route('shopify.settings.pickuppoints-link')}}", target: "app" },
                            { label: "Lähettäjän tiedot", href: "{{route('shopify.settings.sender-link')}}", target: "app" },
                            { label: "API asetukset", href: "{{route('shopify.settings.api-link')}}", target: "app" },
                            { label: "Muut", href: "{{route('shopify.settings.generic-link')}}", target: "app" },
                        ]
                    }
            ]
            },
            title: 'Page Title',
            icon: 'https://example.com/path/to/icon.png'
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