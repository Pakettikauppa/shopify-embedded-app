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
                                @endif
                            </td>
                            <td>
                                @if(isset($order['tracking_code']))
                                <a href="{{route('shopify.track-shipment', ['id' => $order['id']])}}">{{$order['tracking_code']}}</a>
                                @endif
                            </td>
                            <td>
                                @if($order['status'] != 'need_shipping_address')
                                    <a href="{{route('shopify.label', ['order_id' => $order['id']])}}" target="_blank">{{trans('app.print_labels.get_label_link')}}</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

@endsection

@section('after-style-end')

@endsection

@section('after-scripts-end')

<script type='text/javascript'>

    ShopifyApp.init({
        apiKey: '{{ENV('SHOPIFY_API_KEY')}}',
        shopOrigin: 'https://{{session()->get('shop')}}',
        debug: true,
        forceRedirect: true
    });

</script>

@endsection