<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Shopify</title>

<!-- JavaScripts -->
    {{-- <script src="https://unpkg.com/axios/dist/axios.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script> --}}

    <script src="https://unpkg.com/@shopify/app-bridge@1.23.0/umd/index.js"></script>
    <script src="https://unpkg.com/@shopify/app-bridge-utils@1.23.0/umd/index.js"></script>

<script>
  var AppBridge = window['app-bridge'];
var createApp = AppBridge.createApp;
var actions = AppBridge.actions;
var Redirect = actions.Redirect;

// var apiKey = 'API key from Shopify Partner Dashboard';
// var redirectUri = 'allowed redirect URI from Shopify Partner Dashboard';

var permissionUrl = '{{$install_url}}';

// If the current window is the 'parent', change the URL by setting location.href
if (window.top == window.self) {
  window.location.assign(permissionUrl);

  // If the current window is the 'child', change the parent's URL with Shopify App Bridge's Redirect action
} else {
  var app = createApp({
    apiKey: '{{$api_key}}',
    shopOrigin: '{{$shopOrigin}}'
  });

  Redirect.create(app).dispatch(Redirect.Action.REMOTE, permissionUrl);
}
</script>
</head>
<body>
</body>
</html>