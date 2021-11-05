<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Shopify</title>

    <!-- JavaScripts -->
    <script src="https://unpkg.com/@shopify/app-bridge@2.0.5"></script>
    <script src="https://unpkg.com/@shopify/app-bridge-utils@2.0.5"></script>

    <script>
        var AppBridge = window['app-bridge'];
        var createApp = AppBridge.createApp;
        //var actions = AppBridge.actions;
        var Redirect = AppBridge.actions.Redirect;

        var permissionUrl = '{{ $install_url }}';

        // If the current window is the 'parent', change the URL by setting location.href
        if (window.top == window.self) {
            window.location.assign(permissionUrl);
        } else {
            // If the current window is the 'child', change the parent's URL with Shopify App Bridge's Redirect action
            var app = createApp({
                apiKey: '{{ $api_key }}',
                shopOrigin: '{{ $shopOrigin }}',
                host: '{{$shopOrigin}}'
            });

            Redirect.create(app).dispatch(Redirect.Action.REMOTE, permissionUrl);
        }

    </script>
</head>

<body>
</body>

</html>
