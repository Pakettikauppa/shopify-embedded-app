@extends('layouts.app')

@section('content')
    <section id="custom-page">
        <div class="column">
    <form id="custom-shipment" method="POST" action="{{ route('shopify.update-order') }}">
        <article>
            <div class="card column ten">
                <h2>{{trans('app.custom_shipment.title')}}</h2>
                <div class="row" style="margin-bottom: 2em">
                    <h3>{{trans('app.custom_shipment.service_title')}}</h3>
                    <div class="column six">
                        <div class="row">
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
                        </div>
                    </div>
                </div>
                <div class="row" style="margin-bottom: 2em">
                    <h3>{{trans('app.custom_shipment.address_title')}}</h3>
                    <div class="column twelve">
                        <div class="row">
                            <div class="columns six">
                                <label for="first_name">Firstname</label>
                                <input type="text" name="first_name" value="{{ $shipping_address['first_name'] }}">
                            </div>
                            <div class="columns six">
                                <label for="last_name">Lastname</label>
                                <input type="text" name="last_name" value="{{ $shipping_address['last_name'] }}">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row" style="margin-bottom: 2em">
                    <div class="column twelve">
                        <div class="columns twelve">
                            <label for="company">Company</label>
                            <input type="text" name="company" value="{{ $shipping_address['company'] }}">
                        </div>
                    </div>
                </div>
                <div class="row" style="margin-bottom: 2em">
                    <div class="column twelve">
                        <div class="columns twelve">
                            <label for="address1">Address</label>
                            <input type="text" name="address1" value="{{ $shipping_address['address1'] }}">
                        </div>
                    </div>
                </div>
                <div class="row" style="margin-bottom: 2em">
                    <div class="column twelve">
                        <div class="columns twelve">
                            <label for="address2">Apartment, suite, etc.</label>
                            <input type="text" name="address2" value="{{ $shipping_address['address2'] }}">
                        </div>
                    </div>
                </div>
                <div class="row" style="margin-bottom: 2em">
                    <div class="column twelve">
                        <div class="columns six">
                            <label for="zip">Postal code</label>
                            <input type="text" name="zip" value="{{ $shipping_address['zip'] }}">
                        </div>
                        <div class="columns six">
                            <label for="city">City</label>
                            <input type="text" name="city" value="{{ $shipping_address['city'] }}">
                        </div>
                    </div>
                </div>
                <div class="row" style="margin-bottom: 2em">
                    <div class="column twelve">
                        <div class="columns twelve">
                            <label for="country">Country/region</label>
                            <select name="country">
                                @foreach(getCountryList() as $code => $country)
                                    <option value="{{$code}}"  @if($shipping_address['country_code'] == $code) selected @endif>
                                        {{$country}}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row" style="margin-bottom: 2em">
                    <div class="column twelve">
                        <div class="row">
                            <div class="columns twelve">
                                <label for="phone">Phone</label>
                                <input type="text" name="phone" value="{{ $shipping_address['phone'] }}">
                            </div>
                        </div>
                    </div>
                </div>
                <button name="submit">Update Shipment Info</button>
            </div>
        </article>
    </form>
        </div>
    </section>
@endsection

@section('after-scripts-end')
    <script type='text/javascript'>

        {{--$spinner = $('.loading');--}}
        {{--$spinner.show();--}}
        {{--$.ajax({--}}
        {{--    url: '{{route('shopify.ajax-load-pickups')}}' + '?hmac=' + '{{ $hmac }}' + '&shop=' + '{{ $shop->shop_origin }}',--}}
        {{--}).success(function(resp){--}}
        {{--    $spinner.hide();--}}
        {{--    console.log(resp);--}}

        {{--    ShopifyApp.flashNotice('{{trans('app.messages.api_credentials_saved')}}');--}}
        {{--});--}}
    </script>
@endsection