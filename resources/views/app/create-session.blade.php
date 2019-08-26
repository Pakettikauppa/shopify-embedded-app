<html>
<script src="https://cdn.jsdelivr.net/npm/js-cookie@2/src/js.cookie.min.js"></script>

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
    function redirect() {
        console.log("FFFFFF 2");
        Cookies.set('testCookie', 'yes');

        console.log("FFFF: " + Cookies.get('testCookie'));
        window.location.assign('{!! $redirect_url !!}');
    }

    if (!document.hasStorageAccess) {
        console.log("FFFFFF 1");
        redirect();
    }
</script>
<body>
    <button type="button" onclick="redirect();">{{trans('app.messages.activate_app_to_browser')}}</button>
</body>
</html>