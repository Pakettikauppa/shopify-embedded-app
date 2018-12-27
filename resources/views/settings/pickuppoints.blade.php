@extends('layouts.default')

@section('card-content')
<form id="setting-form" method="GET" action="{{route('shopify.update-settings')}}">

    <article>
        <div class="card">
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
        </div>
    </article>
    <article>
        <div class="card">
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

</form>

@endsection
