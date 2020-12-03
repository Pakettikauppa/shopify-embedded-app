@extends('layouts.app')

@section('content')

    <section id="custom-page">
        <div class="column">
            <div class="card" style="margin-top: 1em">
                {{--<h1>{{!!trans('app.tracking_info.title')!!}}</h1>--}}
                <div class="row">
                    {{trans('app.print_labels.order_id')}}: <a href="{{$order_url}}" target="_blank">{{$current_shipment->order_id}}</a>
                </div>
                <div class="row">
                    {{trans('app.print_labels.tracking_code')}}: {{$current_shipment->tracking_code}}
                </div>
                <div class="row">
                    <table>
                        <thead>
                            <tr>
                                <th>{{trans('app.tracking_info.status')}}</th>
                                <th>{{trans('app.tracking_info.postcode')}}</th>
                                <th>{{trans('app.tracking_info.post_office')}}</th>
                                <th>{{trans('app.tracking_info.timestamp')}}</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($statuses as $status)
                            <tr>
                                <td>{{translateStatusCode($status->status_code)}}</td>
                                <td>{{$status->postcode}}</td>
                                <td>{{$status->post_office}}</td>
                                <td>{{$status->event_timestamp}}</td>
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

    function customPageInit() {
        titleBar.set({
            title: '{{trans('app.tracking_info.title')}}'
        });
    }

</script>

@endsection