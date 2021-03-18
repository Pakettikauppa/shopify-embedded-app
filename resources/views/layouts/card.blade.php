@extends('layouts.app')

@section('content')

    {{--<header class="card-header">--}}
        {{--<img src="{{url('/img/logo-1.png')}}">--}}
    {{--</header>--}}
    <section>
        <div class="column">
            <article>
                <div class="card" style="margin-top: 0.5em">
                    @yield('card-content')
                </div>
            </article>
        </div>
    </section>
    <footer>
        <article class="help">
            <span></span>
            <p>{!! trans('app.settings.instructions', ['company_name' => trans('app.settings.company_name'_'.$type), 'instruction_url' => '#']) !!}</p>
        </article>
    </footer>

@endsection
