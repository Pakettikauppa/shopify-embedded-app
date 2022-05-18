@extends('layouts.app-no-menu')

@section('content')
    <section id="custom-page">
        <div class="column">
    <form id="setting-form" method="POST" action="{{ route('shopify.create-shipment') }}">
        <article>
            <div class="card column ten">
                <div class="row" style="margin-bottom: 2em">
                    <div class="columns six">
                        <div class="row">
                            <h3>{{trans('app.custom_shipment.service_title')}}</h3>
                            <select id="shipping-method" name="shipping_method">
                                <option value="">—</option>
                                @foreach($shipping_methods as $key => $service_provider)
                                    @if(count($service_provider) > 0)
                                        <optgroup label="{{$key}}">
                                            @foreach($service_provider as $product)
                                                <option value="{{ $product['shipping_method_code'] }}" @if($product['shipping_method_code'] == $selected_method) selected @endif data-services="{{json_encode($product['additional_services'])}}">
                                                    {{ $product['name'] }}
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div id="pickup-select-block" class="columns six">
                        <div class="row">
                            <h3>{{trans('app.custom_shipment.pickups_title')}}</h3>
                            <select id="pickup-select" name="pickup">
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row" style="margin-bottom: 2em; display: none;" id = "additional-services">
                    <h3>{{trans('app.custom_shipment.additional_service_title')}}</h3>
                    <div id="additional-services-items" style="display: inline-block; width: 100%;"></div>
                </div>

                <div class="row" id="lq-specifiers" style="margin-bottom: 2em; display: none;">
                    <h3>{{trans('app.custom_shipment.lq_specifiers_title')}}</h3>
                    <div class="column twelve">
                        <div class="columns twelve" style="margin-bottom: 1em;">
                            <label for="lqcount">{{trans('app.custom_shipment.qty_dangerous_products')}}</label>
                            <input class="columns one" type="number" id="lqcount" name="lqcount" min="1">
                        </div>
                        <div class="columns twelve">
                            <label for="lqweight">{{trans('app.custom_shipment.hazardous_substances')}}</label>
                            <input class="columns one" type="number" id="lqweight" name="lqweight" min="0.001" step="0.001">
                        </div>
                    </div>
                </div>

                <div class="row" style="margin-bottom: 2em">
                    <div id="multi-parcel-block" class="columns six hidden">
                        <div class="row">
                            <h3>{{trans('app.custom_shipment.multiparcel_title')}}</h3>
                            <input type="number" name="packets" value="1" style="width: 3em;" min="1" step="1" max="15">
                        </div>
                    </div>
                </div>
                <div class="row" style="margin-bottom: 2em">
                    <h3>{{trans('app.custom_shipment.unfulfiled_products')}} (enter quantities you want to fulfil with this shipment)</h3>
                    <div class="column twelve">
                        <div class="columns six">
                            <h5>Product Name</h5>
                        </div>
                        <div class="columns six">
                            <h5>Quantity to fulfil</h5>
                        </div>
                        @foreach($unfulfiled_items as $item)
                            <div class="column twelve">
                                <div class="columns six">
                                    <h6>{{ $item['name'] }}</h6>
                                </div>
                                <div class="columns two">
                                    <div class="columns eight">
                                        <input id="quantity_{{ $item['id'] }}" type="number" step="1" min="0" max="{{ $item['fulfillable_quantity'] }}" name="quantity[{{ $item['id'] }}]" value="{{ $item['fulfillable_quantity'] }}"> 
                                    </div>
                                    <div class="columns four">
                                        <h5 style="margin-top: 7px;">/ {{ $item['fulfillable_quantity'] }}</h5>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                        <input type="checkbox" name="fulfil">Fulfil shipment
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
                            <select id="country" name="country">
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
                <div class="row" style="margin-bottom: 2em">
                    <div class="column twelve">
                        <div class="row">
                            <div class="columns twelve">
                                <label for="email">E-mail</label>
                                <input type="text" name="email" value="{{ $email }}">
                            </div>
                        </div>
                    </div>
                </div>
                <input type="hidden" id="service_name" name="service_name" value="">
                <input type="hidden" name="order_id" value="{{ $order_id }}">
            </div>
        </article>
    </form>
        </div>
    </section>
@endsection

@section('after-scripts-end')
    <script type='text/javascript'>
        buttonSave.set({label: '{{ trans('app.custom_shipment.create_button') }}'});
        saveBtnDisabled(false);
        function customPageInit() {
            titleBar.set({
                title: '{{trans('app.custom_shipment.title')}}'
            });
        }
        $(document).on('ready', () => {
            handlePickupsAndAdditionalServices();
            $('#pickup-select-block').hide();
            $('#shipping-method, #country').on('change', e => {
                handlePickupsAndAdditionalServices();
            });
            $('[name="address1"], [name="zip"]').on('focusout', () => {
                handlePickupsAndAdditionalServices();
            });
            
            $(document).on('change', '#service-3143', function() {
                if($(this).is(':checked')){
                    $('#lq-specifiers').show();
                }else{
                    $('#lq-specifiers').hide();
                }
            });
            
        });

        function handlePickupsAndAdditionalServices()
        {
            $('#service_name').val($('#shipping-method :selected').text().trim());
            $('#pickup-select-block').hide();
            $('#additional-services').hide();
            $('#additional-services-items').html('');
            $('#pickup-select-block option').remove();
            if(!$('#shipping-method').val())
            {
                return;
            }
            startLoading();
            $('#multi-parcel-block').hide();
            $('#shipping-method').find(':selected').data('services').forEach((service) => {
                if(service['service_code'] == '3102')
                {
                    $('#multi-parcel-block').show();
                }
            });
            ax(
                {
                    method: 'POST',
                    url: '{{route('shopify.ajax-load-pickups')}}',
                    data:
                        $.param($('#setting-form').serializeArray()),
                }
            ).then(
                response => {
                    if (response.status === 200 && response.data.status !== 'error' && response.data != '') {
                        showToast({message: response.data.message, isError: false});
                        console.log(response);
                        if(response.data.pickups.length > 0)
                        {
                            let methodCodes, pickupCode;
                            let selectedPickup = (typeof response.data.selected_pickup !== "undefined" && response.data.selected_pickup) ? response.data.selected_pickup : null;
                            response.data.pickups.forEach((pickup) => {
                                methodCodes = pickup['service_code'].split(':');
                                pickupCode = (typeof methodCodes[1] !== "undefined" && methodCodes[1]) ? methodCodes[1] : null;
                                if(pickupCode == selectedPickup)
                                    $('#pickup-select').append(`<option value='"${JSON.stringify(pickup)}"' selected>${pickup.service_name}</option>`);
                                else
                                    $('#pickup-select').append(`<option value='"${JSON.stringify(pickup)}"'>${pickup.service_name}</option>`);
                            });
                            $('#pickup-select-block').show();
                        }
                    } else if (response.status === 200 && response.data.status === 'error') {
                        showToast({
                            message: response.data.message,
                            isError: true
                        });
                        console.error('RESPONSE ERROR:', response.data.message);
                    } else {
                        showToast({
                            message: 'Something went wrong',
                            isError: true
                        });
                    }
                    stopLoading();
                }
            ).catch(
                error => {
                    showToast({
                        message: 'Something went wrong',
                        isError: true
                    });
                    console.error('API ERROR:', error);
                    stopLoading();
                }
            );
            //load additional services
            ax(
                {
                    method: 'POST',
                    url: '{{route('shopify.ajax-load-additional-services')}}',
                    data:
                        $.param($('#setting-form').serializeArray()),
                }
            ).then(
                response => {
                    if (response.status === 200 && response.data.status !== 'error') {
                        showToast({message: response.data.message, isError: false});
                        if(response.data.data.length > 0)
                        {
                            response.data.data.forEach((service) => {
                                if (service.specifiers === null){
                                    $('#additional-services-items').append(`<div class="columns six inline-labels" style ="margin-left:0;"><input type ="checkbox" name = "additional_services[]" id = "service-${service.service_code}" value="${service.service_code}"/><label for = "service-${service.service_code}">${service.name}</label></div>`);
                                }
                            });
                            $('#additional-services').show();
                        }
                    } else if (response.status === 200 && response.data.status === 'error') {
                        showToast({
                            message: response.data.message,
                            isError: true
                        });
                        console.error('RESPONSE ERROR:', response.data.message);
                    } else {
                        showToast({
                            message: 'Something went wrong',
                            isError: true
                        });
                    }
                    stopLoading();
                }
            ).catch(
                error => {
                    showToast({
                        message: 'Something went wrong',
                        isError: true
                    });
                    console.error('API ERROR:', error);
                    stopLoading();
                }
            );
        }

    </script>
@endsection