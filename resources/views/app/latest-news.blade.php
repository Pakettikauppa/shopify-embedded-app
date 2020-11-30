@extends('layouts.default')

@section('card-content')

<section>
    <div class="column">
        @foreach($feed->item as $_item)
            @if(in_array($_item->category,['Shopify', 'Yleinen']))
        <div class="card" style="margin-top: 1em">
                <div class="row">
                    <h2>{{$_item->title}}</h2>
                        {!! $_item->children("http://purl.org/rss/1.0/modules/content/")->encoded !!}
                    <p>
                        <small>
                            Julkaistu: {{date('d.m.Y', strtotime($_item->pubDate))}}
                        </small>
                    </p>
                </div>
        </div>
            @endif
        @endforeach
    </div>
</section>

@endsection

