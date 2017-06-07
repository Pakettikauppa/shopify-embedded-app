@extends('layouts.app')

@section('content')

<div class="section">
    <div class="section-summary">
        <h1>Setup Wizard</h1>
        <p>Initial settings</p>
    </div>
    <div class="section-content">
        <div class="section-row">
            <div class="section-row">
                <div class="section-cell">
                    <div class="cell-container">
                        <div class="cell-column">
                            <span id="wizard-new-user" class="btn default wizard-btn"><span class="btn-content">I'm a new user</span></span>
                        </div>
                        <div class="cell-column">
                            <span id="wizard-old-user" class="btn default wizard-btn"><span class="btn-content">I have API credentials</span></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="section">
    <div class="section-summary">
        <h1>Settings</h1>
        {{--<p>Initial settings</p>--}}
    </div>
    <div class="section-content">
        <div class="section-row">
                <div class="section-cell">
                    <form method="GET" action="{{route('shopify.update-preferences')}}">

                    @if(isset($shop->api_key) && isset($shop->api_secret))
                        <span class="tag green" style="color: white; margin-bottom: 15px;">{{trans('app.messages.ready')}}</span>

                        <div class="section-row">
                        <label class="bold">API key</label>: {{$shop->api_key}}
                        </div>

                        <div class="section-row">
                            <label class="bold">API secret</label>: {{$shop->api_secret}}
                        </div>
                    @else

                        <div class="box warning" style="margin-bottom: 20px;"><i class="ico-warning"></i>{{trans('app.messages.no_api')}}</div>

                    @endif

                    <label class="bold">Shipping method</label>
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


                    {{--<label class="bold">API key</label>--}}
                    {{--<input type="text" placeholder="" value="00000000-0000-0000-0000-000000000000">--}}

                    {{--<label class="bold">API secret</label>--}}
                    {{--<input type="text" placeholder="" value="1234567890ABCDEF">--}}

                    <div class="input-wrapper inline">
                        <div class="checkbox-wrapper">
                            <input class="checkbox pointer" type="checkbox" id="test_mode" name="test_mode"
                                 @if($shop->test_mode) checked @endif>
                            <span class="checkbox-styled"></span></div>
                        <label for="test_mode" class="pointer">Test mode</label>
                    </div>

                    <div>
                        <button type="submit" class="btn primary">Save settings</button>
                        <a href="#" class="btn default">View instructions</a>
                    </div>

                    </form>

                </div>
        </div>
    </div>
</div>

@endsection

@section('after-style-end')
    <style>
        .bold{
            font-weight: bold;
        }
        .btn-content{
            line-height: 100px;
        }
        .wizard-btn{
            width: 200px;
            height: 100px;
            text-align: center;
        }
        select{
            width: 100%;
            padding-left: 10px;
            height: 1.8em;
        }
        .pointer{
            cursor: pointer;
        }
    </style>
@endsection

@section('after-scripts-end')

<script type='text/javascript'>

    ShopifyApp.init({
        apiKey: '{{ENV('SHOPIFY_API_KEY')}}',
        shopOrigin: 'https://{{session()->get('shop')}}',
        debug: true,
        forceRedirect: false
    });

    btn_href = "{{route('shopify.preferences', request()->all())}}";
    btn_href = btn_href.replace(/&amp;/g, '&');
    console.log(btn_href);

    ShopifyApp.ready(function(){
        ShopifyApp.Bar.initialize({
            icon: "http://me.com/app.png",
            buttons: {
                primary: {
                    label: 'Settings',
                    href: btn_href,
                    target: "app"
                }
            },
            title: "Settings"
        });
    });

    $('#wizard-new-user').click(function(){
        ShopifyApp.Modal.open({
            src: 'https://hallinta.pakettikauppa.fi/register',
            title: 'Enter API credentials',
            width: 'small',
            height: 300,
            buttons: {
                primary: { label: "Submit" },
                secondary: [
                    { label: "Cancel", callback: function (label) { ShopifyApp.Modal.close(); } }
                ]
            }
        }, function(result, data){
            console.log("result: " + result + "   data: " + data);
        });
    });


</script>

@endsection