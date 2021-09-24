@extends('layouts.app')

@section('content')

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
                    @foreach($orders as $order)
                        <tr>
                            <td><a href="{{$order['admin_order_url']}}" target="_top">{{$order['id']}}</a></td>
                            <td>
                                @if($order['status'] == 'created')
                                    <span class="tag green">{{trans('app.print_labels.statuses.created')}}</span>
                                @elseif($order['status'] == 'sent')
                                    <span class="tag yellow">{{trans('app.print_labels.statuses.sent')}}</span>
                                @elseif($order['status'] == 'need_shipping_address')
                                    <span class="tag red">{{trans('app.print_labels.statuses.need_shipping_address')}}</span>
                                @elseif($order['status'] == 'no_shipping_service')
                                    <span class="tag red">{{trans('app.print_labels.statuses.no_shipping_service')}}</span>
                                @elseif($order['status'] == 'not_in_inventory')
                                    <span class="tag red">{{trans('app.print_labels.statuses.not_in_inventory')}}</span>
                                @elseif($order['status'] == 'custom_error')
                                    <span class="tag red">{{$order['error_message']}}</span>
                                @endif
                            </td>
                            <td>
                                @if(isset($order['tracking_code']))
                                <a href="{{$tracking_url.$order['tracking_code']}}" target="pakettikauppa-seuranta">{{$order['tracking_code']}}</a>
                                @endif
                            </td>
                            <td>
                                @if($order['status'] == 'created' || $order['status'] == 'sent')
                                    <a class="print-label" href="{{route('shopify.label', ['order_id' => $order['id']])}}?{{$order['hmac_print_url']}}" target="_blank">{{trans('app.print_labels.get_label_link')}}</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                {{-- TODO: remove csrf tokens, as it is no longer needed and instead hmac should be attached to request url --}}
            <form id="print-labels-form" method="post" action="{{route('shopify.get_labels')}}?{{$print_all_params}}" target="_blank">
                    <input type="hidden" name="_token" id="csrf-token" value="{{ Session::token() }}" />
                    @foreach($orders as $order)
                        @if(isset($order['tracking_code']) && $order['tracking_code'])
                        <input type="hidden" name="tracking_codes[]" value="{{$order['tracking_code']}}">
                        @endif
                    @endforeach
                    <button name="submit">{{trans('app.print_labels.fetch_all')}}</button>
                </form>
                <p><a href="{{$orders_url}}" target="_top">{{trans('app.print_labels.back_to_orders')}}</a></p>
            </div>
        </div>
    </div>
</section>

@endsection

@section('after-style-end')

@endsection

@section('after-scripts-end')

<script type='text/javascript'>

    function customPageInit() {
        titleBar.set({
            title: '{{trans('app.print_labels.' . $page_title)}}'
        });

        // Collect all links to print label
        let printLinks = document.getElementsByClassName('print-label');

        Object.keys(printLinks).forEach( key => {
            printLinks[key].addEventListener('click', function(e){
                e.preventDefault();
                // Use Shopify redirect to safely open link in new tab
                redirect.dispatch(Actions.Redirect.Action.REMOTE, {
                    url: e.target.href,
                    newContext: true,
                });
            });
        });
    }
</script>

@endsection