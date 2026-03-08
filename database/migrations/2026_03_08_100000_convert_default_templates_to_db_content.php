<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Converts system-default templates from blade_view to DB content.
 * This makes templates fully self-contained — LLM can create/edit templates
 * without needing Blade files on disk. The base layout (_base.blade.php) auto-wraps
 * all DB content templates with CSS, print styles, and document structure.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Report template — body content only (layout auto-wrapped)
        DB::table('document_templates')
            ->whereNull('team_id')
            ->where('key', 'report')
            ->update([
                'blade_view' => null,
                'content' => $this->reportContent(),
                'meta' => json_encode(['styles' => $this->reportStyles()]),
                'updated_at' => now(),
            ]);

        // Letter template
        DB::table('document_templates')
            ->whereNull('team_id')
            ->where('key', 'letter')
            ->update([
                'blade_view' => null,
                'content' => $this->letterContent(),
                'meta' => json_encode(['styles' => $this->letterStyles()]),
                'updated_at' => now(),
            ]);

        // Table-report template
        DB::table('document_templates')
            ->whereNull('team_id')
            ->where('key', 'table-report')
            ->update([
                'blade_view' => null,
                'content' => $this->tableReportContent(),
                'meta' => json_encode(['styles' => $this->tableReportStyles()]),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('document_templates')
            ->whereNull('team_id')
            ->where('key', 'report')
            ->update([
                'blade_view' => 'platform::documents.report',
                'content' => null,
                'meta' => null,
                'updated_at' => now(),
            ]);

        DB::table('document_templates')
            ->whereNull('team_id')
            ->where('key', 'letter')
            ->update([
                'blade_view' => 'platform::documents.letter',
                'content' => null,
                'meta' => null,
                'updated_at' => now(),
            ]);

        DB::table('document_templates')
            ->whereNull('team_id')
            ->where('key', 'table-report')
            ->update([
                'blade_view' => 'platform::documents.table-report',
                'content' => null,
                'meta' => null,
                'updated_at' => now(),
            ]);
    }

    private function reportContent(): string
    {
        return <<<'BLADE'
@if(!empty($subtitle))
    <p class="subtitle">{{ $subtitle }}</p>
@endif
@if(!empty($date) || !empty($author))
    <div class="meta-block">
        @if(!empty($date))<span>{{ $date }}</span>@endif
        @if(!empty($author))<span>{{ $author }}</span>@endif
    </div>
@endif

<div class="content-body">
    {!! $html_content !!}
</div>
BLADE;
    }

    private function reportStyles(): string
    {
        return <<<'CSS'
.subtitle { font-size: 12pt; color: #555; margin-bottom: 12pt; }
.meta-block { font-size: 9pt; color: #666; margin-bottom: 18pt; }
.meta-block span { margin-right: 12pt; }
CSS;
    }

    private function letterContent(): string
    {
        return <<<'BLADE'
@if(!empty($sender))
    <div class="sender-block">{!! $sender !!}</div>
@endif

@if(!empty($recipient))
    <div class="recipient-block">{!! $recipient !!}</div>
@endif

@if(!empty($date))
    <div style="text-align: right; margin-bottom: 24pt; color: #555;">{{ $date }}</div>
@endif

@if(!empty($subject))
    <div class="subject-line">{{ $subject }}</div>
@endif

<div class="content-body" style="line-height: 1.6;">
    {!! $html_content !!}
</div>

@if(!empty($closing))
    <div class="closing-block">{{ $closing }}</div>
@endif

@if(!empty($signature))
    <div class="signature-block">{!! $signature !!}</div>
@endif
BLADE;
    }

    private function letterStyles(): string
    {
        return '';
    }

    private function tableReportContent(): string
    {
        return <<<'BLADE'
@if(!empty($subtitle) || !empty($date) || !empty($author))
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
@endif

<div class="content-body">
    {!! $html_content !!}
</div>
BLADE;
    }

    private function tableReportStyles(): string
    {
        return <<<'CSS'
.report-header { margin-bottom: 18pt; }
.report-header .subtitle { font-size: 11pt; color: #555; }
.report-header .meta { font-size: 8pt; color: #888; margin-top: 4pt; }
.content-body table { font-size: 9pt; }
.content-body table th {
    font-weight: 600;
    font-size: 8pt;
    text-transform: uppercase;
    letter-spacing: 0.5pt;
    padding: 6pt 8pt;
}
.content-body table td { padding: 5pt 8pt; }
.content-body table tr:hover td { background: #edf2f7; }
.content-body table .total-row td {
    font-weight: 700;
    border-top: 1.5pt solid #2d3748;
    background: transparent;
}
CSS;
    }
};
