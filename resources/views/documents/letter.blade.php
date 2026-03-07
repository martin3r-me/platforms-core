@extends('platform::documents._layout')

@section('styles')
    .letter-sender { font-size: 9pt; margin-bottom: 24pt; }
    .letter-recipient { margin-bottom: 24pt; line-height: 1.6; }
    .letter-date { text-align: right; margin-bottom: 24pt; color: #555; }
    .letter-subject { font-size: 12pt; font-weight: 700; margin-bottom: 18pt; }
    .letter-body { line-height: 1.6; }
    .letter-body p { margin-bottom: 8pt; }
    .letter-closing { margin-top: 24pt; }
    .letter-signature { margin-top: 36pt; }
@endsection

@section('content')
    @if(!empty($sender))
        <div class="letter-sender">{!! $sender !!}</div>
    @endif

    @if(!empty($recipient))
        <div class="letter-recipient">{!! $recipient !!}</div>
    @endif

    @if(!empty($date))
        <div class="letter-date">{{ $date }}</div>
    @endif

    @if(!empty($subject))
        <div class="letter-subject">{{ $subject }}</div>
    @endif

    <div class="letter-body">
        {!! $html_content !!}
    </div>

    @if(!empty($closing))
        <div class="letter-closing">{{ $closing }}</div>
    @endif

    @if(!empty($signature))
        <div class="letter-signature">{!! $signature !!}</div>
    @endif
@endsection
