@extends('layouts.app')

@section('content')

<section id="custom-page">
    <div class="row" style="padding-top: 1em; width: 100%;">
        <div class="alert {{$message_type??'error'}}">
            <dl>
                <dt>{{$title}}</dt>
                <dd>{!!$message!!}</dd>
            </dl>
        </div>
    </div>
</section>



@endsection

@section('after-style-end')

@endsection

@section('after-scripts-end')

<script type='text/javascript'>
    function customPageInit() {
        titleBar.set({
            title: '{{trans('app.messages.error')}}'
        });
    }
</script>

@endsection