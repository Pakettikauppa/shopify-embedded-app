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
                            <a href="#" class="btn default wizard-btn"><span class="btn-content">I'm a new user</span></a>
                        </div>
                        <div class="cell-column">
                            <a href="#" class="btn default wizard-btn"><span class="btn-content">I have API credentials</span></a>
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
                    <label class="bold">Shipping method</label>
                    <select name="select1">
                        <option value="opt1">Option 1</option>
                        <option value="opt2">Option 2</option>
                        <option value="opt3">Option 3</option>
                        <option value="opt4">Option 4</option>
                    </select>

                    <label class="bold">Return label type</label>
                    <select name="select1">
                        <option value="opt1">Option 1</option>
                        <option value="opt2">Option 2</option>
                        <option value="opt3">Option 3</option>
                        <option value="opt4">Option 4</option>
                    </select>

                    <label class="bold">API key</label>
                    <input type="text" placeholder="" value="00000000-0000-0000-0000-000000000000">

                    <label class="bold">API secret</label>
                    <input type="text" placeholder="" value="1234567890ABCDEF">

                    <div class="input-wrapper inline">
                        <div class="checkbox-wrapper"><input class="checkbox pointer" type="checkbox" id="autoprint" name="autoprint" value=""><span class="checkbox-styled"></span></div>
                        <label for="autoprint" class="pointer">Print return labels automaticly during printing the shipping label</label>
                    </div>

                    <div class="input-wrapper inline">
                        <div class="checkbox-wrapper"><input class="checkbox pointer" type="checkbox" id="test_mode" name="test_mode" value=""><span class="checkbox-styled"></span></div>
                        <label for="test_mode" class="pointer">Test mode</label>
                    </div>

                    <div>
                        <a href="#" class="btn primary">Save settings</a>
                        <a href="#" class="btn default">View instructions</a>
                    </div>

                    <div>

                    </div>

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
            margin-bottom: 15px;
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
        forceRedirect: true
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

</script>

@endsection