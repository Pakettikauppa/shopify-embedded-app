@extends('layouts.card')

@section('card-content')
    <form id="setting-form" method="GET" action="{{route('shopify.update-settings')}}">

        <div class="info">
            @if(isset($shop->api_key) && isset($shop->api_secret))
                <span class="tag green" style="margin-bottom: 15px;">{{trans('app.messages.ready')}}</span>
            @else
                <div class="alert notification">
                    <dl>
                        <dt>{{trans('app.messages.no_api')}}</dt>
                        <dd>{{trans('app.messages.only_test_mode')}}</dd>
                    </dl>
                </div>
            @endif

            @if(session()->has('error'))
                <div class="alert error">
                    <dl>
                        <dt>{{session()->get('error')}}</dt>
                    </dl>
                </div>
            @endif
        </div>

        <div class="row">
            <div class="input-group">
                <span class="append">{{trans('app.settings.shipping_method')}}</span>
                <select name="shipping_method">
                    {{--<option value="" disabled selected hidden>{{ trans('app.shipping_method.choose_sending_method') }}</option>--}}
                    @foreach($shipping_methods as $key => $service_provider)
                        @if(count($service_provider) > 0)
                            <optgroup label="{{$key}}">
                                @foreach($service_provider as $product)
                                    <option value="{{ $product['shipping_method_code'] }}"
                                            @if($shop->shipping_method_code == $product['shipping_method_code']) selected @endif>
                                        {{ $product['name'] }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endif
                    @endforeach
                </select>
            </div>
        </div>
        <div class="row">
            <label><input type="checkbox" name="test_mode" @if($shop->test_mode) checked @endif>{{trans('app.settings.test_mode')}}</label>
        </div>
    </form>

@endsection

@section('after-style-end')

@endsection

@section('after-scripts-end')

<script type='text/javascript'>

    ShopifyApp.ready(function(){
        ShopifyApp.Bar.initialize({
            title: '{{trans('app.settings.settings')}}',
            icon: '{{url('/img/favicon-96x96.png')}}',
            buttons: {
                primary: {
                    label: "{{trans('app.settings.save_settings')}}",
                    callback: function(){
                        $('#setting-form').submit();
                    }
                },
                secondary: [{
                    label: "{{trans('app.settings.setup_wizard')}}",
                    href: '{{route('shopify.setup-wizard')}}'
                }]
            }
        });
    });

</script>

@endsection