@extends('layouts.app-no-menu')

@section('content')

    <section id="custom-page">
        <div class="column">
            <div class="card" style="margin-top: 1em">
                <div class="row">
                    <table>
                        <thead>
                        <tr>
                            <th>{{trans('app.print_labels.date')}}</th>
                            <th>{{trans('app.print_labels.time')}}</th>
                            <th>{{trans('app.print_labels.tracking_code')}}</th>
                            <th>{{trans('app.print_labels.fulfilled_products')}}</th>
                            <th>{{trans('app.print_labels.get_the_label')}}</th>
                        </tr>
                        </thead>
                        <tbody>
                            @foreach($shipments as $shipment)
                                <tr>
                                    <td>{{ $shipment['created_at']->format("Y-m-d") }}</td>
                                    <td>{{ $shipment['created_at']->format("H:i") }}</td>
                                    <td>
                                        @if(strpos($shipment['tracking_code'], ',') !== false)
                                            @php
                                                $tracking_codes = explode(',', $shipment['tracking_code'])
                                            @endphp
                                        @else
                                            @php
                                                $tracking_codes = [$shipment['tracking_code']]
                                            @endphp
                                        @endif
                                        @foreach($tracking_codes as $tracking_code)
                                            {{$tracking_code}}<br>
                                        @endforeach
                                    </td>
                                    <td>
                                        @php
                                            $anyFulflliment = false;
                                        @endphp
                                        @foreach($tracking_codes as $tracking_code)
                                            @if(isset($fulfillments[$tracking_code]))
                                                @php
                                                    $anyFulflliment = true;
                                                @endphp
                                                @foreach($fulfillments[$tracking_code]['line_items'] as $item)
                                                    {{ $item['quantity'] }} x {{ $item['title'] }}<br>
                                                @endforeach
                                            @endif
                                        @endforeach
                                        @if(!$anyFulflliment)
                                            {{-- Check for package fullfilment (several tracking numbers) --}}
                                            @if(isset($fulfillments[$shipment['tracking_code']]))
                                            @foreach($fulfillments[$shipment['tracking_code']]['line_items'] as $item)
                                                {{ $item['quantity'] }} x {{ $item['title'] }}<br>
                                                @endforeach
                                            @else
                                                ------
                                            @endif
                                        @endif
                                    </td>
                                    <td>
                                        <a class="print-label" href="{{route('shopify.label', ['order_id' => $shipment['id'], 'tracking_code' => $tracking_codes[0]])}}?{{$hmac_print_url}}" target="_blank">{{trans('app.print_labels.get_label_link')}}</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
    <script type='text/javascript'>
        saveBtnDisabled(true);
    </script>
@endsection