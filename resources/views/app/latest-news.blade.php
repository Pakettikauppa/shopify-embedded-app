@extends('layouts.app')

@section('content')

<section>
    <div class="column">
        @foreach($feed->item as $_item)
        <div class="card" style="margin-top: 1em">
                <div class="row">
<h2>{{$_item->title}}</h2>
                        {!! $_item->children("http://purl.org/rss/1.0/modules/content/")->encoded !!}
                </div>
        </div>
        @endforeach
    </div>
</section>

@endsection

