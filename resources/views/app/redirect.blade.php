<html>
<script src="https://unpkg.com/@shopify/app-bridge@1.6.7/umd/index.js"></script>
<script type='text/javascript'>
    function setCookie(cname, cvalue) {
        var d = new Date();
        d.setTime(d.getTime() + (24*60*60*1000));
        var expires = "expires="+ d.toUTCString();
        document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
    }

    function getCookie(cname) {
        var name = cname + "=";
        var decodedCookie = decodeURIComponent(document.cookie);
        var ca = decodedCookie.split(';');
        for(var i = 0; i <ca.length; i++) {
            var c = ca[i];
            while (c.charAt(0) == ' ') {
                c = c.substring(1);
            }
            if (c.indexOf(name) == 0) {
                return c.substring(name.length, c.length);
            }
        }
        return "";
    }
</script>
<script type='text/javascript'>
    var ShopifyTestCookie = getCookie("shopify.testCookie");
    var ShopifyTopLevelOAuthCookie = getCookie("shopify.topLevelOAuth");

    // ShopifyTestCookie = 'yes';
    // ShopifyTopLevelOAuthCookie = 'yes';

    var AppBridge = window['app-bridge'];
    var createApp = AppBridge.default;
    var actions = AppBridge.actions;
    var Redirect  = actions.Redirect;

    //get host parameter
    const queryString = window.location.search;
    const urlParams = new URLSearchParams(queryString);
    var host = urlParams.get('host');

    if(!host || host == ''){
        host = '{!! $shop_origin !!}';
    }

    var app = createApp({
        apiKey: '{!! env('SHOPIFY_API_KEY') !!}',
        shopOrigin: '{!! $shop_origin !!}',
        host: host 
    });

    const redirect = Redirect.create(app);

    if (ShopifyTestCookie == "yes") {
        if (ShopifyTopLevelOAuthCookie == "yes") {
            console.log("HEP 1");
            window.location.assign('{!! $redirect_url !!}');
        } else {
            console.log("HEP 2");
            setCookie('shopify.topLevelOAuth', 'yes');

            redirect.dispatch(Redirect.Action.REMOTE, '{!! $redirect_url !!}');
        }
    } else {
        console.log("HEP 3");

        redirect.dispatch(Redirect.Action.REMOTE, '{!! $enable_cookies_url !!}');
    }
</script>
<body></body>
</html>
