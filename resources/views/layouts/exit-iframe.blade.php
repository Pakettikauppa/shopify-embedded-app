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
    <script src="https://unpkg.com/axios@0.26.1/dist/axios.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script>
    <script src="https://unpkg.com/@shopify/app-bridge@3"></script>
    <script src="https://unpkg.com/@shopify/app-bridge-utils@3.4.3"></script>
    <script>
        //check if we have any redirect backs
        const redirect_url = document.cookie.match('(^|;)\\s*redirect_back_url\\s*=\\s*([^;]+)')?.pop() || false;
        
        var AppBridge = window['app-bridge'];

        //get host parameter
        const queryString = window.location.search;
        const urlParams = new URLSearchParams(queryString);
        var host = urlParams.get('host');

        if(!host || host == ''){
            host = '{{$domain}}';
        }

        var Actions = AppBridge.actions;
        var createApp = AppBridge.default;
        var ShopifyApp = createApp({
            apiKey: '{{config('shopify.api_key')}}',
            shopOrigin: '{{$domain}}',
            host: host,
            debug: true,
            forceRedirect: true
        });

        var AppUtils = window['app-bridge-utils'];
        var getSessionToken = AppUtils.getSessionToken;
        var appDiv = null;

        const redirect = Actions.Redirect.create(ShopifyApp);
        redirect.dispatch(
            Actions.Redirect.Action.REMOTE,
            '{{$redirectUrl}}'
        );

        
    </script>
</head>
<body id="app-layout">
    <div id="app-page"></div>
</body>
</html>
