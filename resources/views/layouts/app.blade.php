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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script>
    <script src="https://cdn.shopify.com/s/assets/external/app.js"></script>
    <script>
        ShopifyApp.init({
            apiKey: '{{ENV('SHOPIFY_API_KEY')}}',
            shopOrigin: 'https://{{session()->get('shop')}}',
            debug: true,
            forceRedirect: true
        });

        ShopifyApp.ready(function () {
            ShopifyApp.Bar.initialize({
                title: '{{trans('app.settings.settings')}}',
                icon: '{{url('/img/favicon-96x96.png')}}',
                buttons: {
                    @if(Route::currentRouteName() != 'shopify.latest-news')
                    primary: {
                        label: "{{trans('app.settings.save_settings')}}",
                        callback: function () {
                            saveForm();
                        }
                    },
                    @endif
                    secondary: [
                            @if($shop->test_mode)
                        {
                            label: "Testitila on päällä",
                            callback: function () {
                                toggleProduction();
                            }
                        },
                            @else
                        {
                            label: "Testitila on pois päällä",
                            callback: function () {
                                toggleTesting();
                            }
                        },

                            @endif
                        {
                            label: "Ajankohtaista",
                            href: "{{route('shopify.latest-news')}}",
                            target: "app"
                        },
                        {
                            label: "Asetukset",
                            type: "dropdown",
                            links: [
                                {
                                    label: "Lähetysasetukset",
                                    href: "{{route('shopify.settings.shipping-link')}}",
                                    target: "app"
                                },
                                {
                                    label: "Noutopisteasetukset",
                                    href: "{{route('shopify.settings.pickuppoints-link')}}",
                                    target: "app"
                                },
                                {
                                    label: "Lähettäjän tiedot",
                                    href: "{{route('shopify.settings.sender-link')}}",
                                    target: "app"
                                },
                                {
                                    label: "API asetukset",
                                    href: "{{route('shopify.settings.api-link')}}",
                                    target: "app"
                                },
                                {
                                    label: "Muut",
                                    href: "{{route('shopify.settings.generic-link')}}",
                                    target: "app"
                                },
                            ]
                        }
                    ]
                },
                icon: 'https://www.pakettikauppa.fi/apple-icon-57x57.png'
            });
        });

        function saveForm() {
            var settingsForm = $('#setting-form');
            var spinner = $('#spinner');

            updateSettings(settingsForm.serialize());
        }

        function toggleTesting() {
            toggleTestMode(true);
        }

        function toggleTestMode(mode) {
            updateSettings({'test_mode': mode}, function (mesg) {
                ShopifyApp.Modal.alert(mesg, function () {
                    $(location).attr('href', "{{Request::url()}}");
                });
            });
        }

        function updateSettings(data, callback) {
            var settingsForm = $('#setting-form');
            var spinner = $('#spinner');

            $.ajax({
                type: settingsForm.attr('method'),
                url: "{{route('shopify.update-settings')}}",
                data: data,
                dataType: 'json'
            }).done(function (resp) {
                if (resp.status == 'ok') {
                    if (typeof callback === 'undefined') {
                        showSuccessMessage(resp.message);
                    } else {
                        callback(resp.message);
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

    </script>
    @yield('after-scripts-end')
</head>
<body id="app-layout">

@yield('content')

<div class="loading hidden">
    <img class="spinner" src="{{url('/img/ajax-loader.gif')}}">
</div>
</body>
</html>
