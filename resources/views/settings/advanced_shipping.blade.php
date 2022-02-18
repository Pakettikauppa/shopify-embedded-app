@extends('layouts.default')

@section('card-content')

<form id="setting-form" method="POST" action="{{route('shopify.update-shipping')}}">

    <article>
        <div class="card">
            <h2>{{trans('app.settings.shipment_settings')}}</h2>
            @if ($shop->carrier_service_id == null)
            {{trans('app.settings.enable_carrier_api')}}
            @else
            <div class="row" style="margin-bottom: 2em">
                <div class="columns four rate-name-column">
                    {{trans('app.settings.additional.add_shipping_method')}}

                </div>
                <div class="columns eight">
                    <div class="row">
                        <div class="input-group">
                            <select>
                                <option value="">—</option>
                                @foreach($shipping_methods as $key => $service_provider)
                                    @if(count($service_provider) > 0)
                                    <optgroup label="{{$key}}">
                                        @foreach($service_provider as $product)
                                            @if (!$product['has_pickup_points'])
                                            <option value="{{ $product['shipping_method_code'] }}" data-name ="{{ $product['service_provider'] . ': ' . $product['name'] }}" data-services="{{json_encode($product['additional_services'])}}"
                                                    @if (isset($shipping_settings[$product['shipping_method_code']])) disabled @endif>
                                                {{ $product['name'] }}
                                            </option>
                                            @endif
                                        @endforeach
                                    </optgroup>
                                    @endif
                                @endforeach
                            </select>
                            <a href ="#" class = "button add_shipping_method">Add</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                    <table class = "advanced_shipping_table">
                        <thead>
                        <tr>
                            <th width = "130px;">
                                {{trans('app.settings.pickuppoints.provider')}}
                            </th>
                            <th></th>
                            <th width="90px"></th>
                        </tr>
                        </thead>
                        <tbody>
                            
                        </tbody>    
                    </table>
            </div>
            @endif
        </div>
    </article>

    @include('settings.shipping_services')

</form>
@include('settings.shipping_scripts')
@endsection
