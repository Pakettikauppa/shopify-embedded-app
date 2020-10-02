<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Shopify</title>

    <!-- Fonts -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.4.0/css/font-awesome.min.css" rel='stylesheet'
          type='text/css'>
    <link href="https://fonts.googleapis.com/css?family=Lato:100,300,400,700" rel='stylesheet' type='text/css'>

    <!-- Styles -->
    <link href="{{url('css/uptown.css')}}" rel='stylesheet' type='text/css'>
    <link href="{{url('css/style.css')}}" rel='stylesheet' type='text/css'>
@yield('after-style-end')

    <!-- JavaScripts -->
    <script src="https://unpkg.com/axios/dist/axios.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script>
    <script src="https://unpkg.com/@shopify/app-bridge@1.23.0/umd/index.js"></script>
    <script src="https://unpkg.com/@shopify/app-bridge-utils@1.23.0/umd/index.js"></script>
    <script>

        var AppBridge = window['app-bridge'];
        var Actions = AppBridge.actions;
        var createApp = AppBridge.default;
        var ShopifyApp = createApp({
            apiKey: '{{config('shopify.api_key')}}',
            shopOrigin: '{{$shop->shop_origin}}',
            debug: true,
            forceRedirect: true
        });
        console.log(AppBridge);

        var AppUtils = window['app-bridge-utils'];
        var getSessionToken = AppUtils.getSessionToken;
        var appDiv = null;

      var instance = axios.create();
        // intercept all requests on this axios instance
        instance.interceptors.request.use(  
            function (config) {  
                console.log('Intercepted', config);
                return getSessionToken(window.ShopifyApp)  // requires an App Bridge instance
                .then((token) => {  
                    // append your request headers with an authenticated token
                    config.headers['Authorization'] = `Bearer ${token}`;  
                    return config;  
                });
            }
        );

        console.log(window);

        @if(!empty($shop))
            console.log('Building panel');
            const loading = Actions.Loading.create(ShopifyApp);
            loading.dispatch(Actions.Loading.Action.START);
            const toast = Actions.Toast.create(ShopifyApp, { duration: 2000,});
            function showToast(options) {
                toast.set(options);
                toast.dispatch(Actions.Toast.Action.SHOW);
            }
            const redirect = Actions.Redirect.create(ShopifyApp);
            const saveButton = Actions.Button.create(ShopifyApp, {
                label: '{{trans('app.settings.save_settings')}}',
            });
            saveButton.set({disabled: true});
            const newsBtn = Actions.Button.create(ShopifyApp, {
                label: '{{trans('app.settings.latest-news')}}',
            });

            redirect.subscribe( Actions.Redirect.Action.REMOTE, (payload) => {
                // Do something with the redirect
                console.log(`Navigated to ${payload.path}`, payload);
                return true;
            });

            function loadView(viewUrl, callback = null) {
                instance.get(viewUrl)
                    .then(response=>{
                        appDiv.innerHTML = response.data;
                        if (callback) {
                            callback();
                        }
                    });
            }

            newsBtn.subscribe('click', () => {
                //showToast({message: 'No good', isError: true});
                loadView('{{route('shopify.latest-news')}}', ()=>{
                    saveButton.set({disabled: true});
                });
            });

            function getTestModeBtnText()
            {
                return test_mode ? test_mode_disable_text : test_mode_enable_text;
            }

            function getTestModeBtnStyle()
            { //return Actions.Button.Style.Danger;
                return test_mode ? Actions.Button.Style.Danger : undefined;
            }
            
            function switchMode() {
                let data = {'test_mode': !test_mode};
                instance({
                    method: 'POST',
                    url: '{{route('shopify.update-test-mode')}}',
                    data: data,
                    //headers: {'Content-Type': 'multipart/form-data' }
                }).then(response=>{
                    console.log(response);
                    if (response.status === 200 && response.data.status !== 'error') {
                        showToast({message: response.data.message, isError: false});
                        test_mode = !test_mode;
                        button_test_mode.set({label: getTestModeBtnText(), style: getTestModeBtnStyle()});
                    } else if (response.status === 200 && response.data.status === 'error') {
                        showToast({message: response.data.message, isError: true});
                    } else {
                        showToast({message: 'something went wrong', isError: true});
                    }
                }).catch(error=>{
                    showToast({message: 'something went wrong', isError: true});
                    console.log('API ERROR', error);
                });
                console.log("{{route('shopify.update-test-mode')}}", data);
            }

            function updateInnerHtml(target, data) {
                target.innerHTML = data;
                let scripts = target.getElementsByTagName('script');
                Object.keys(scripts).forEach(key => {
                    eval(scripts[key].innerText);
                });
            }
            
            let test_mode_enable_text = '{{trans('app.settings.testmode_on')}}';
            let test_mode_disable_text = '{{trans('app.settings.testmode_off')}}';
            let test_mode = @if($shop->test_mode) true @else false @endif;
            
            const button_test_mode = Actions.Button.create(ShopifyApp, { label: getTestModeBtnText(), style: getTestModeBtnStyle() });
            button_test_mode.subscribe('click', () => {
                switchMode();
            });

            console.log(JSON.stringify(Actions.Button.create));

            const button1 = Actions.Button.create(ShopifyApp, { label: "{{trans('app.settings.shipment_settings')}}" });
            const button2 = Actions.Button.create(ShopifyApp, { label: "{{trans('app.settings.pickuppoints-settings')}}" });
            const button3 = Actions.Button.create(ShopifyApp, { label: "{{trans('app.settings.company_info')}}" });
            const button4 = Actions.Button.create(ShopifyApp, { label: "{{trans('app.settings.api-settings')}}" });
            const button5 = Actions.Button.create(ShopifyApp, { label: "{{trans('app.settings.generic-settings')}}" });

            button1.subscribe('click', () => {
                loadView('{{route('shopify.settings.shipping-link')}}', ()=>{
                    saveButton.set({disabled: false});
                });
            });
            button2.subscribe('click', () => {
                loadView('{{route('shopify.settings.pickuppoints-link')}}', ()=>{
                    saveButton.set({disabled: false});
                });
            });
            button3.subscribe('click', () => {
                loadView('{{route('shopify.settings.sender-link')}}', ()=>{
                    saveButton.set({disabled: false});
                });
            });

            button4.subscribe('click', () => {
                loadView('{{route('shopify.settings.api-link')}}', ()=>{
                    saveButton.set({disabled: false});
                });
            });

            function loadLocalizationView() {
                loadView('{{route('shopify.settings.generic-link')}}', ()=>{
                    saveButton.set({disabled: false});
                });
            }

            button5.subscribe('click', () => {
                loadLocalizationView();
            });

        function saveFormNew(data) {
            var formData = new FormData(data);
            instance({
                method: 'post',
                url: data.action,
                data: formData,
                headers: {'Content-Type': 'multipart/form-data' }
                }).then(response=>{
                        //appDiv.innerHTML = response.data;
                        console.log(response);
                        if (response.status === 200) {
                            showToast({message: response.data.message, isError: response.data.status === 'error'});
                            if (typeof response.data.html !== 'undefined') {
                                updateInnerHtml(appDiv, response.data.html);
                            }
                        } else {
                            showToast({message: 'Something went wrong', isError: true});
                        }
                    });
            console.log("{{route('shopify.update-settings')}}", data);
            return;
            }

            saveButton.subscribe('click', () => {
                var settingsForm = document.getElementById('setting-form');
                var spinner = $('#spinner');

                saveFormNew(settingsForm);
            });

            const myGroupButton = Actions.ButtonGroup.create(ShopifyApp, {label: '{{trans('app.settings.settings')}}', buttons: [button1, button2, button3, button4, button5]});
            Actions.TitleBar.create(ShopifyApp, {
                title: 'My page title',
                buttons: {
                    primary: saveButton,
                    secondary: [button_test_mode, newsBtn, myGroupButton],
                },
            });
            loading.dispatch(Actions.Loading.Action.STOP);
        @else
            console.log('NO BUTTONS :(');
        @endif

        function saveForm() {
            var settingsForm = $('#setting-form');
            var spinner = $('#spinner');

            updateSettings(settingsForm.serialize());
        }

        function toggleTesting() {
            toggleTestMode(true);
        }
@php
$url=Request::url();
$url=str_replace("http:","https:",$url);
@endphp
        function toggleTestMode(mode) {
            updateSettings({'test_mode': mode}, function (mesg) {
                ShopifyApp.Modal.alert(mesg, function () {
                    $(location).attr('href', "{{$url}}");
                });
            });
        }

        function updateSettings(data, callback) {
            var settingsForm = $('#setting-form');
            var spinner = $('#spinner');

            $.ajax({
                type: settingsForm.attr('method'),
                url: "{{route('shopify.update-settings', ['shop'=>$shop->shop_origin])}}",
                data: data,
                dataType: 'json'
            }).done(function (resp) {
                if (resp.status == 'ok' || resp.status == 'ok-reload') {
                    if (typeof callback === 'undefined') {
                        showSuccessMessage(resp.message);
                    } else {
                        callback(resp.message);
                    }
                    if(resp.status == 'ok-reload') {
                        $(location).attr('href', "{{$url}}");
                    }

                } else if (resp.result == 'validation_error') {
                    showDangerMessage(resp.message);
                } else {
                    showDangerMessage(resp.message);
                }
            }).fail(function () {
                showDangerMessage('{{trans('app.messages.fail')}}');
            });
        }

        function toggleProduction() {
            toggleTestMode(false);
        }

        function showSuccessMessage(mesg) {
            ShopifyApp.flashNotice(mesg);
        }

        function showDangerMessage(mesg) {
            ShopifyApp.flashError(mesg);
        }

        document.addEventListener('DOMContentLoaded', e=>{
            appDiv = document.getElementById('app-page');

            instance.get('{{route('shopify.latest-news')}}')
                    .then(response=>{
                        appDiv.innerHTML = response.data;
                        saveButton.set({disabled: true});
                    });
        });

    </script>
    @yield('after-scripts-end')
</head>
<body id="app-layout">
    <div id="app-page">
        @yield('content')
    </div>
<div class="loading hidden">
    <img class="spinner" src="{{url('/img/ajax-loader.gif')}}">
</div>
</body>
</html>
