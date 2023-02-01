<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Shopify</title>

    <!-- JavaScripts -->
    <script src="https://unpkg.com/@shopify/app-bridge@3.0.1"></script>
    <script src="https://unpkg.com/@shopify/app-bridge-utils@3.4.3"></script>

    <script>
        @if (isset($redirect_back_url) && $redirect_back_url)
            var date = new Date();
            date.setTime(date.getTime()+(5*60*1000));
            var expires = "; expires="+date.toGMTString();
            document.cookie = "redirect_back_url={{$redirect_back_url}}"+expires+"; path=/;SameSite=None; Secure";
        @endif
        var AppBridge = window['app-bridge'];
//        var createApp = AppBridge.createApp;
        var createApp = AppBridge.default;
        //var actions = AppBridge.actions;
        var Actions = AppBridge.actions;
        var Redirect = AppBridge.actions.Redirect;

        var permissionUrl = '{{ $install_url }}';

        //get host parameter
        const queryString = window.location.search;
        const urlParams = new URLSearchParams(queryString);
        var host = urlParams.get('host');

        if(!host || host == ''){
            host = '{{$shopOrigin}}';
        }

        // If the current window is the 'parent', change the URL by setting location.href       
        if (window.top == window.self) {
            window.location.assign(permissionUrl);
        } else {
            // If the current window is the 'child', change the parent's URL with Shopify App Bridge's Redirect action
            var app = createApp({
                apiKey: '{{ $api_key }}',
                shopOrigin: '{{ $shopOrigin }}',
                host: host,
                debug: true,
                forceRedirect: true
            });

//            Redirect.create(app).dispatch(Redirect.Action.REMOTE, permissionUrl);
            const redirect = Actions.Redirect.create(app);
            redirect.subscribe(
                Actions.Redirect.Action.REMOTE,
                (payload) => {
                    // Do something with the redirect
                    return true;
                }
            );

        }

    </script>
</head>

<body>
</body>

</html>
