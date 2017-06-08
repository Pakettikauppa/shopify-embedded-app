@extends('layouts.app')

@section('content')

{{--<div class="section">--}}
    {{--<div class="section-summary">--}}
        {{--<h1>Setup Wizard</h1>--}}
        {{--<p>Initial settings</p>--}}
    {{--</div>--}}
    {{--<div class="section-content">--}}
        {{--<div class="section-row">--}}
            {{--<div class="section-row">--}}
                {{--<div class="section-cell">--}}
                    {{--<div class="cell-container">--}}
                        {{--<div class="cell-column">--}}
                            {{--<span id="wizard-new-user" class="btn default wizard-btn"><span class="btn-content">I'm a new user</span></span>--}}
                        {{--</div>--}}
                        {{--<div class="cell-column">--}}
                            {{--<span id="wizard-old-user" class="btn default wizard-btn"><span class="btn-content">I have API credentials</span></span>--}}
                        {{--</div>--}}
                    {{--</div>--}}
                {{--</div>--}}
            {{--</div>--}}
        {{--</div>--}}
    {{--</div>--}}
{{--</div>--}}

<header>
    <img src="/img/logo-1.png">
</header>
{{--<section>--}}
    {{--<iframe width="100%" src="https://hallinta.pakettikauppa.fi/register" frameborder="0" style="height: 250px;" id="register"></iframe>--}}
{{--</section>--}}
<section>
    <div class="column">
        <article>
            <div class="card">
                <h1>{{trans('app.settings.settings')}}</h1>

                <form method="GET" action="{{route('shopify.update-settings')}}">

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
                            <span class="append">{{trans('app.settings.api_key')}}</span>
                            <input type="text" name="api_key" value="{{$shop->api_key}}">
                        </div>
                    </div>

                    <div class="row">
                        <div class="input-group">
                            <span class="append">{{trans('app.settings.api_secret')}}</span>
                            <input type="text" name="api_secret" value="{{$shop->api_secret}}">
                        </div>
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

                    <div class="row">
                        <button type="submit">{{trans('app.settings.save_settings')}}</button>
                    </div>
                </form>
            </div>
        </article>
    </div>
</section>
<footer>
    <article class="help">
        <span></span>
        <p>{!! trans('app.settings.instructions', ['company_name' => trans('app.settings.company_name'), 'instruction_url' => '#']) !!}</p>
    </article>
</footer>

@endsection

@section('after-style-end')
    <style>
        header{
            background: url('/img/bg-PK2.jpg');
            background-repeat: no-repeat;
            background-size: 100%;
            color: white;
            min-height: 150px;
            padding-top: 35px;
        }
    </style>
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