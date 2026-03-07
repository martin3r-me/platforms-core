@extends('platform::documents._layout')

@section('styles')
    .subtitle { font-size: 12pt; color: #555; margin-bottom: 12pt; }
    .meta-block { font-size: 9pt; color: #666; margin-bottom: 18pt; }
    .meta-block span { margin-right: 12pt; }
    .content-body h1 { font-size: 16pt; margin-top: 16pt; margin-bottom: 8pt; }
    .content-body h2 { font-size: 13pt; margin-top: 12pt; margin-bottom: 6pt; }
    .content-body h3 { font-size: 11pt; margin-top: 10pt; margin-bottom: 4pt; }
    .content-body ul, .content-body ol { margin-left: 16pt; margin-bottom: 8pt; }
    .content-body li { margin-bottom: 3pt; }
    .content-body blockquote { border-left: 2pt solid #ddd; padding-left: 8pt; color: #555; margin: 8pt 0; }
    .content-body hr { border: none; border-top: 0.5pt solid #ddd; margin: 12pt 0; }
    .content-body img { max-width: 100%; height: auto; }
@endsection

@section('header')
    @if(!empty($subtitle))
        <p class="subtitle">{{ $subtitle }}</p>
    @endif
    @if(!empty($date) || !empty($author))
        <div class="meta-block">
            @if(!empty($date))<span>{{ $date }}</span>@endif
            @if(!empty($author))<span>{{ $author }}</span>@endif
        </div>
    @endif
@endsection

@section('content')
    <div class="content-body">
        {!! $html_content !!}
    </div>
@endsection
