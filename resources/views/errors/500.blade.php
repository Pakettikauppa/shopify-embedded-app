<!DOCTYPE html>
<html>
<head>
    <title>{{ trans('http.500.title') }}</title>

    <link href="https://fonts.googleapis.com/css?family=Lato:100" rel="stylesheet" type="text/css">

    <style>
        html, body {
            height: 100%;
        }

        body {
            margin: 0;
            padding: 0;
            width: 100%;
            color: #717a7f;
            display: table;
            font-weight: 600;
            font-family: 'Lato';
        }

        .container {
            text-align: center;
            display: table-cell;
            vertical-align: middle;
        }

        .content {
            text-align: center;
            display: inline-block;
        }

        .title {
            font-size: 72px;
            margin-bottom: 40px;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="content">
        <div class="title">{{ trans('http.500.title') }}</div>
        @php
            $shopifyType = Config::get('shopify.type');
            $description = trans('http.500.description_'.$shopifyType);
        @endphp
        <p>{!! $description !!}</p>
    </div>
</div>
</body>
</html>
