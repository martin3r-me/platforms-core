@extends('platform::documents._layout')

@section('styles')
    .report-header { margin-bottom: 18pt; }
    .report-header .subtitle { font-size: 11pt; color: #555; }
    .report-header .meta { font-size: 8pt; color: #888; margin-top: 4pt; }
    .content-body table { font-size: 9pt; }
    .content-body table th {
        background: #2d3748;
        color: #fff;
        font-weight: 600;
        font-size: 8pt;
        text-transform: uppercase;
        letter-spacing: 0.5pt;
        padding: 6pt 8pt;
    }
    .content-body table td { padding: 5pt 8pt; }
    .content-body table tr:nth-child(even) td { background: #f8f9fa; }
    .content-body table tr:hover td { background: #edf2f7; }
    .content-body table .total-row td {
        font-weight: 700;
        border-top: 1.5pt solid #2d3748;
        background: transparent;
    }
    .content-body .kpi-grid { display: flex; flex-wrap: wrap; gap: 12pt; margin-bottom: 18pt; }
    .content-body .kpi-card {
        flex: 1 1 120pt;
        border: 0.5pt solid #ddd;
        border-radius: 4pt;
        padding: 10pt 12pt;
        text-align: center;
    }
    .content-body .kpi-value { font-size: 18pt; font-weight: 700; color: #2d3748; }
    .content-body .kpi-label { font-size: 8pt; color: #888; text-transform: uppercase; letter-spacing: 0.5pt; }
    .content-body h2 { margin-top: 16pt; margin-bottom: 8pt; border-bottom: 0.5pt solid #ddd; padding-bottom: 4pt; }
    .content-body ul, .content-body ol { margin-left: 14pt; margin-bottom: 8pt; }
    .content-body li { margin-bottom: 2pt; font-size: 9pt; }
@endsection

@section('header')
    <div class="report-header">
        @if(!empty($subtitle))
            <div class="subtitle">{{ $subtitle }}</div>
        @endif
        @if(!empty($date) || !empty($author))
            <div class="meta">
                @if(!empty($date)){{ $date }}@endif
                @if(!empty($date) && !empty($author)) &middot; @endif
                @if(!empty($author)){{ $author }}@endif
            </div>
        @endif
    </div>
@endsection

@section('content')
    <div class="content-body">
        {!! $html_content !!}
    </div>
@endsection
