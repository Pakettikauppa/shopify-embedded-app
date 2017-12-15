@extends('layouts.app')

@section('content')

<section>
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
                    @foreach($orders as $order)
                        <tr>
                            <td><a href="{{$order['admin_order_url']}}" target="_blank">{{$order['id']}}</a></td>
                            <td>
                                @if($order['status'] == 'created')
                                    <span class="tag green">{{trans('app.print_labels.statuses.created')}}</span>
                                @elseif($order['status'] == 'sent')
                                    <span class="tag yellow">{{trans('app.print_labels.statuses.sent')}}</span>
                                @elseif($order['status'] == 'need_shipping_address')
                                    <span class="tag red">{{trans('app.print_labels.statuses.need_shipping_address')}}</span>
                                @elseif($order['status'] == 'no_shipping_service')
                                    <span class="tag red">{{trans('app.print_labels.statuses.no_shipping_service')}}</span>
                                @elseif($order['status'] == 'custom_error')
                                    <span class="tag red">{{$order['error_message']}}</span>
                                @endif
                            </td>
                            <td>
                                @if(isset($order['tracking_code']))
                                <a href="https://www.pakettikauppa.fi/seuranta/?{{$order['tracking_code']}}" target="pakettikauppa-seuranta">{{$order['tracking_code']}}</a>
                                @endif
                            </td>
                            <td>
                                @if($order['status'] == 'created' || $order['status'] == 'sent')
                                    <a href="{{route('shopify.label', ['order_id' => $order['id']])}}" target="_blank">{{trans('app.print_labels.get_label_link')}}</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                <td><a href="{{$order['admin_orders_url']}}" target="_blank">{{trans('app.print_labels.back_to_orders')}}</a></td>
            </div>
        </div>
    </div>
</section>

@endsection

@section('after-style-end')

@endsection

@section('after-scripts-end')

<script type='text/javascript'>

    ShopifyApp.ready(function(){

        ShopifyApp.Bar.initialize({
            title: '{{trans('app.print_labels.' . $page_title)}}',
            icon: '{{url('/img/favicon-96x96.png')}}'
        });

    });

</script>

@endsection