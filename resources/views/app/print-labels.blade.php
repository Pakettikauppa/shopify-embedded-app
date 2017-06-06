@extends('layouts.app')

@section('content')

<div class="section">
    <div class="section-summary">
        <h1>{{trans('app.print_labels.title')}}</h1>
        <p>{{trans('app.print_labels.description')}}</p>
    </div>
    <div class="section-content">
        <div class="section-row">
            <div class="section-cell">
                <table class="table-section">
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
                            <td><a href="{{$order['admin_order_url']}}">{{$order['id']}}</a></td>
                            <td>
                                @if($order['status'] == 'created')
                                    <span class="tag green">{{trans('app.print_labels.statuses.created')}}</span>
                                @elseif($order['status'] == 'sent')
                                    <span class="tag yellow">{{trans('app.print_labels.statuses.sent')}}</span>
                                @elseif($order['status'] == 'need_shipping_address')
                                    <span class="tag pink">{{trans('app.print_labels.statuses.need_shipping_address')}}</span>
                                @endif
                            </td>
                            <td>
                                {{$order['tracking_code'] or ''}}
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
</div>

@endsection

@section('after-style-end')

@endsection

@section('after-scripts-end')

<script type='text/javascript'>

    ShopifyApp.init({
        apiKey: '{{ENV('SHOPIFY_API_KEY')}}',
        shopOrigin: 'https://{{session()->get('shop')}}',
        debug: true,
        forceRedirect: false
    });

</script>

@endsection