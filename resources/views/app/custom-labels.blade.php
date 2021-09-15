@extends('layouts.default')

@section('card-content')

    <section id="custom-page">
        <div class="column">
            <div class="card" style="margin-top: 1em">
                <div class="row">
                    <table>
                        <thead>
                        <tr>
                            <th>{{trans('app.print_labels.order_id')}}</th>
                            <th>{{trans('app.print_labels.status')}}</th>
                            <th>{{trans('app.print_labels.tracking_code')}}</th>
                            <th>{{trans('app.print_labels.get_the_label')}}</th>
                        </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><a href="{{$shipment['admin_order_url']}}" target="_top">{{$shipment['id']}}</a></td>
                                <td>
                                    @if($shipment['status'] == 'created')
                                        <span class="tag green">{{trans('app.print_labels.statuses.created')}}</span>
                                    @elseif($shipment['status'] == 'sent')
                                        <span class="tag yellow">{{trans('app.print_labels.statuses.sent')}}</span>
                                    @elseif($shipment['status'] == 'need_shipping_address')
                                        <span class="tag red">{{trans('app.print_labels.statuses.need_shipping_address')}}</span>
                                    @elseif($shipment['status'] == 'no_shipping_service')
                                        <span class="tag red">{{trans('app.print_labels.statuses.no_shipping_service')}}</span>
                                    @elseif($shipment['status'] == 'not_in_inventory')
                                        <span class="tag red">{{trans('app.print_labels.statuses.not_in_inventory')}}</span>
                                    @elseif($shipment['status'] == 'custom_error')
                                        <span class="tag red">{{$shipment['error_message']}}</span>
                                    @endif
                                </td>
                                <td>
                                    @if(isset($tracking_codes) && !empty($tracking_codes))
                                        @foreach($tracking_codes as $tracking_code)
                                            <a href="{{$tracking_url.$tracking_code}}" target="pakettikauppa-seuranta">{{$tracking_code}}</a><br>
                                        @endforeach
                                    @endif
                                </td>
                                <td>
                                    @if($shipment['status'] == 'created' || $shipment['status'] == 'sent')
                                        <a class="print-label" href="{{route('shopify.label', ['order_id' => $shipment['id'], 'tracking_code' => $tracking_codes[0]])}}?{{$shipment['hmac_print_url']}}" target="_blank">{{trans('app.print_labels.get_label_link')}}</a>
                                    @endif
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <form id="print-labels-form" method="post" action="{{route('shopify.get_labels')}}?{{$print_all_params}}" target="_blank">
                        <input type="hidden" name="_token" id="csrf-token" value="{{ Session::token() }}" />
                        @if(isset($tracking_codes) && !empty($tracking_codes))
                            @foreach($tracking_codes as $tracking_code)
                                <input type="hidden" name="tracking_codes[]" value="{{ $tracking_code }}">
                            @endforeach
                        @endif
                        <button name="submit">{{trans('app.print_labels.fetch_all')}}</button>
                    </form>
                    <p><a href="{{$orders_url}}" target="_top">{{trans('app.print_labels.back_to_orders')}}</a></p>
                </div>
            </div>
        </div>
    </section>
    <script type='text/javascript'>
        saveBtnDisabled(true);
    </script>
@endsection