@extends('layouts.default')

@section('card-content')
<form id="setting-form" method="POST" action="{{route('shopify.update-pickuppoints')}}">

    <article>
        <div class="card">
            <h2>{{trans('app.settings.pickuppoints.title')}}</h2>
            @if ($shop->carrier_service_id == null)
            {{trans('app.settings.enable_carrier_api')}}
            @else
            <div class="row">
                <div class="columns four">
                    <h5>{{trans('app.settings.pickup_filter')}}</h5>
                </div>
                <div class="columns eight">
                    <select name="pickup_filter[]" multiple>
                        @foreach($pickup_filter_types as $key => $value)
                            <option value="{{ $value }}" @if(in_array($value, $shop->pickup_filter) || (is_null($value) && empty($shop->pickup_filter))) selected @endif>
                                {{ trans("app.settings.pickuppoints.$key") }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
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
                        @foreach($shipping_methods as $shipping_method)
                        @if ($shipping_method['has_pickup_points'])
                        @php
                        $shippingMethodCode = (string) $shipping_method['shipping_method_code'];
                        @endphp
                        <tr>
                            <td>
                                <input type="hidden" name="pickuppoint[{{$shippingMethodCode}}][active]" value="false">
                                <label>
                                    <input type="checkbox" name="pickuppoint[{{$shippingMethodCode}}][active]" value="true" @if($pickuppoint_settings[$shippingMethodCode]['active']=='true' ) checked @endif>
                                </label>
                            </td>
                            <td>
                                {{$shipping_method['service_provider']}}: {{$shipping_method['name']}}
                            </td>
                            <td>
                                <input type="number" min="0" name="pickuppoint[{{$shippingMethodCode}}][base_price]" value="{{$pickuppoint_settings[$shippingMethodCode]['base_price']}}">
                            </td>
                            <td>
                                <input type="number" min="0" name="pickuppoint[{{$shippingMethodCode}}][trigger_price]" value="{{$pickuppoint_settings[$shippingMethodCode]['trigger_price']}}">
                            </td>
                            <td>
                                <input type="number" min="0" name="pickuppoint[{{$shippingMethodCode}}][triggered_price]" value="{{$pickuppoint_settings[$shippingMethodCode]['triggered_price']}}">
                            </td>
                        </tr>
                        @endif
                        @endforeach
                    </table>
                </div>
            </div>
        </div>
    </article>
    <article>
        <div class="card">
            <div class="row">
                <div class="input-group">
                    <span class="append">{{trans('app.settings.pickuppoints_count')}}</span>
                    <select name="pickuppoints_count">
                        @for($i=0; $i<11; $i++) <option value="{{$i}}" @if($shop->pickuppoints_count == $i) selected @endif>
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
            <div class="row">
                <div class="input-group">
                    <span class="append">{{trans('app.settings.pickuppoints.limit_pickup_points_by_weight')}}</span>
                    <select name="weight_limit">
                        <option value="1" @if($shop->weight_limit == 1) selected @endif>
                            {{trans('app.settings.pickuppoints.yes')}}
                        </option>
                        <option value="0" @if($shop->weight_limit == 0) selected @endif>
                            {{trans('app.settings.pickuppoints.no')}}
                        </option>
                    </select>
                </div>
            </div>
        </div>
        @endif
    </article>

</form>

@endsection