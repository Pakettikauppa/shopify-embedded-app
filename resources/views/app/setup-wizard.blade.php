@extends('layouts.card')

@section('card-content')
<div class="wizard-titles">
    <h2 class="wizard-title is-new-user">{{trans('app.settings.wizard.is_new_user')}}</h2>
    <h2 class="hidden wizard-title register">{{trans('app.settings.wizard.register')}}</h2>
    <h2 class="hidden wizard-title sign-contract">{{trans('app.settings.wizard.sign_contract')}}</h2>
    <h2 class="hidden wizard-title enter-api-credentials">{{trans('app.settings.wizard.enter_api_credentials')}}</h2>
    <h2 class="hidden wizard-title everything-is-ready">{{trans('app.messages.ready')}} <i class="icon-checkmark"></i></h2>
</div>

<div class="row wizard-content">

    <div class="wizard-page is-new-user inline-labels">
        <label><input type="radio" name="is_new_user" checked="checked" value="0">{{trans('app.settings.wizard.yes')}}</label>
        <label><input type="radio" name="is_new_user" value="1">{{trans('app.settings.wizard.no_new')}}</label>
    </div>

    <div class="hidden wizard-page register">
        <iframe width="100%" src="{{env('HALLINTA_URL')}}/register" frameborder="0" style="height: 250px;" id="register"></iframe>
    </div>
    <div class="hidden wizard-page sign-contract">
        <a id="sign-contract-btn" class="button hidden">{{trans('app.settings.wizard.sign_contract_now')}}</a>
    </div>
    <div class="hidden wizard-page enter-api-credentials">
        <form id="api-credentials-form">
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

        </form>
    </div>
    {{--<div class="hidden wizard-page everything-is-ready"></div>--}}
</div>

<div class="row wizard-navigation">
    <a href="{{route('shopify.settings')}}" class="button secondary"><i class="icon icon-gear"></i><span style="padding-left: 15px;">{{trans('app.settings.wizard.back_to_settings')}}</span></a>
    <button id="next-btn" class="secondary"><span style="padding-right: 15px;">{{trans('app.settings.wizard.next')}}</span><i class="icon icon-next"></i></button>
</div>

@endsection

@section('after-scripts-end')

<script type='text/javascript'>
    var registered = false; // should be false

    ShopifyApp.ready(function(){

        ShopifyApp.Bar.initialize({
            title: '{{trans('app.settings.setup_wizard')}}',
            icon: '{{url('/img/favicon-96x96.png')}}'
        });

    });

    document.addEventListener('DOMContentLoaded', ready);
    function ready(){
        var $spinner = $('.loading');

        var wizard_config = {
            current_page: -1,
            new_user: [
                "register",
                "sign-contract",
                "everything-is-ready"
            ],
            old_user: [
                "enter-api-credentials",
                "everything-is-ready"
            ]
        };

//        setTimeout(function(){
//            showPage(wizard_config);
//        }, 2000);

        $('#next-btn').click(function(){

            if(wizard_config.current_page > -1){
                execute(wizard_config);
            }else{
                wizard_config.current_page++;
                showPage(wizard_config);
            }

        });

        function execute(config){
            route = getCurrentRoute(config);
            if(route == 'enter-api-credentials'){
                url = '{{route('shopify.set-api-credentials')}}?' + $("#api-credentials-form").serialize();
                $spinner.show();
                $.get(url).success(function(resp){
                    $spinner.hide();
                    if(resp.status == 'error'){
                        ShopifyApp.flashError(resp.message);
                        return;
                    }

                    wizard_config.current_page++;
                    showPage(wizard_config);
                }).fail(function(){
                    $spinner.hide();
                    ShopifyApp.flashError('{{trans('app.messages.fail')}}');
                });
            }
            if(route == 'register'){
                if(!registered){
                    ShopifyApp.flashError('{{trans('app.messages.register_first')}}');
                    return;
                }
                wizard_config.current_page++;
                showPage(wizard_config);
            }
            if(route == 'sign-contract'){
                wizard_config.current_page++;
                showPage(wizard_config);
            }
        }

        function getCurrentRoute(config){
            is_new_user = $('input:radio[name=is_new_user]:checked').val() == true;
//            is_new_user = true;
            branch = is_new_user ? config.new_user : config.old_user;
            return branch[config.current_page];
        }


        function showPage(config){
            class_name = getCurrentRoute(config);
            $('.wizard-content .wizard-page').hide();
            $('.wizard-titles .wizard-title').hide();
            $('.wizard-page.' + class_name).show();
            $('.wizard-title.' + class_name).show();
            if(class_name == 'everything-is-ready'){
                $('#next-btn').hide();
            }

            if(class_name == 'sign-contract') {
                composeContractLink();
            }
        }

        function composeContractLink(){
            $.get('{{route('shopify.sign-contract-link')}}')
                .success(function(resp){
                    $('#sign-contract-btn').attr('href', resp).attr('target', '_blank').show();
                }).fail(function(){
                    ShopifyApp.flashError('{{trans('app.messages.fail')}}');
                });
        }

    }

    // postMessage
    if (window.addEventListener) {
        window.addEventListener ("message", receive, false);
    }
    else {
        if (window.attachEvent) {
            window.attachEvent("onmessage",receive, false);
        }
    }

    function receive(event){
        var data = event.data;
        if(typeof(window[data.func]) == "function"){
            window[data.func].call(null, data.params);
        }
    }

    function saveCredentials(params){
        $spinner = $('.loading');
        if(typeof params === 'undefined'){
            ShopifyApp.flashError('{{trans('app.messages.wait_for_email')}}');
            $('#next-btn').hide();
            return;
        }

        if(typeof params.api_key !== 'undefined'){
            $spinner.show();
            $.ajax({
                url: '{{route('shopify.set-api-credentials')}}',
                data: params
            }).success(function(resp){
                $spinner.hide();
                if(resp.status == 'error'){
                    ShopifyApp.flashError(resp.message);
                    return;
                }

                registered = true;
                ShopifyApp.flashNotice('{{trans('app.messages.api_credentials_saved')}}');
            }).fail(function(){
                $spinner.hide();
                ShopifyApp.flashError('{{trans('app.messages.fail')}}');
            });
        }
    }

</script>

@endsection