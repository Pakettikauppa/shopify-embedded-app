@extends('layouts.default')

@section('card-content')

<form id="setting-form" method="POST" action="{{route('shopify.update-locale')}}">

    <article>

        <div class="card" id="generic-card">
            <h2>{{trans('app.settings.settings')}}</h2>
            <div class="row">
                <div class="input-group">
                    <span class="append">{{trans('app.settings.language')}}</span>
                    <select name="language">
                        @foreach(getLanguageList() as $code => $language)
                            <option value="{{$code}}"  @if($shop->locale == $code) selected @endif>
                                {{$language}}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </article>
</form>

@endsection
