<html>
<script src="https://cdn.shopify.com/s/assets/external/app.js"></script>

<script type='text/javascript'>
    ShopifyApp.init({
        apiKey: '{!! env('SHOPIFY_API_KEY') !!}',
        shopOrigin: 'https://{!! $url['domain'] !!}'
    });

    // If the current window is the 'parent', change the URL by setting location.href
    if (window.top == window.self) {
        window.location.assign('https://{!! $url['domain'] !!}/admin{!! $url['path'] !!}');

        // If the current window is the 'child', change the parent's URL with ShopifyApp.redirect
    } else {
        ShopifyApp.redirect('{!! $url['path'] !!}');
    }
</script>
<body></body>
</html>
