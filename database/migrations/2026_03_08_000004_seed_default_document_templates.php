<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('document_templates')->insert([
            [
                'team_id' => null,
                'key' => 'report',
                'name' => 'Bericht',
                'description' => 'Generischer Bericht — flexibles Layout für beliebige Inhalte. '
                    . 'Die LLM generiert den HTML-Body (Überschriften, Absätze, Listen, Tabellen) in data.html_content. '
                    . 'Optional: subtitle, date, author.',
                'blade_view' => 'platform::documents.report',
                'content' => null,
                'schema' => json_encode([
                    'type' => 'object',
                    'properties' => [
                        'html_content' => ['type' => 'string', 'description' => 'HTML-Body des Berichts. Kann Überschriften (h1-h3), Absätze (p), Listen (ul/ol), Tabellen (table), Trennlinien (hr) und Bilder (img) enthalten.'],
                        'subtitle' => ['type' => 'string', 'description' => 'Optional: Untertitel'],
                        'date' => ['type' => 'string', 'description' => 'Optional: Datum (z.B. "07.03.2026")'],
                        'author' => ['type' => 'string', 'description' => 'Optional: Autor'],
                    ],
                    'required' => ['html_content'],
                ]),
                'default_data' => null,
                'meta' => null,
                'is_active' => true,
                'created_by_user_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'team_id' => null,
                'key' => 'letter',
                'name' => 'Brief',
                'description' => 'Brief-Format mit Absender, Empfänger, Betreff und Grußformel. '
                    . 'Die LLM generiert den Brieftext als HTML in data.html_content. '
                    . 'Strukturfelder: sender, recipient, date, subject, closing, signature.',
                'blade_view' => 'platform::documents.letter',
                'content' => null,
                'schema' => json_encode([
                    'type' => 'object',
                    'properties' => [
                        'html_content' => ['type' => 'string', 'description' => 'HTML-Body des Brieftexts.'],
                        'sender' => ['type' => 'string', 'description' => 'Absender (HTML, z.B. Name + Adresse mit <br>)'],
                        'recipient' => ['type' => 'string', 'description' => 'Empfänger (HTML, z.B. Name + Adresse mit <br>)'],
                        'date' => ['type' => 'string', 'description' => 'Datum (z.B. "München, 07.03.2026")'],
                        'subject' => ['type' => 'string', 'description' => 'Betreffzeile'],
                        'closing' => ['type' => 'string', 'description' => 'Grußformel (z.B. "Mit freundlichen Grüßen")'],
                        'signature' => ['type' => 'string', 'description' => 'Unterschrift (HTML, z.B. Name + Titel)'],
                    ],
                    'required' => ['html_content'],
                ]),
                'default_data' => json_encode([
                    'closing' => 'Mit freundlichen Grüßen',
                ]),
                'meta' => null,
                'is_active' => true,
                'created_by_user_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'team_id' => null,
                'key' => 'table-report',
                'name' => 'Daten-Report',
                'description' => 'Optimiert für datenintensive Inhalte: Tabellen, KPI-Karten, Listen. '
                    . 'Die LLM generiert den HTML-Body in data.html_content. '
                    . 'Verfügbare CSS-Klassen: table (auto-styled), .kpi-grid + .kpi-card (.kpi-value + .kpi-label), .total-row. '
                    . 'Optional: subtitle, date, author.',
                'blade_view' => 'platform::documents.table-report',
                'content' => null,
                'schema' => json_encode([
                    'type' => 'object',
                    'properties' => [
                        'html_content' => ['type' => 'string', 'description' => 'HTML-Body des Reports. Nutze <table> für Datentabellen (th/td werden auto-styled), <div class="kpi-grid"><div class="kpi-card"><div class="kpi-value">42</div><div class="kpi-label">Tasks</div></div></div> für KPI-Karten, und .total-row für Summenzeilen in Tabellen.'],
                        'subtitle' => ['type' => 'string', 'description' => 'Optional: Untertitel'],
                        'date' => ['type' => 'string', 'description' => 'Optional: Datum'],
                        'author' => ['type' => 'string', 'description' => 'Optional: Autor'],
                    ],
                    'required' => ['html_content'],
                ]),
                'default_data' => null,
                'meta' => null,
                'is_active' => true,
                'created_by_user_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('document_templates')
            ->whereNull('team_id')
            ->whereIn('key', ['report', 'letter', 'table-report'])
            ->delete();
    }
};
