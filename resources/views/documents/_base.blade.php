<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Dokument' }}</title>
    <style>
        /* Reset & Base */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { font-size: 10pt; }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.5;
            color: #1a1a1a;
        }

        /* Print-specific */
        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }

        /* Page layout */
        .page { width: 100%; }

        /* Typography */
        h1 { font-size: 18pt; font-weight: 700; margin-bottom: 8pt; }
        h2 { font-size: 14pt; font-weight: 600; margin-bottom: 6pt; }
        h3 { font-size: 12pt; font-weight: 600; margin-bottom: 4pt; }
        p { margin-bottom: 6pt; }
        small { font-size: 8pt; color: #666; }

        /* Table */
        table { width: 100%; border-collapse: collapse; margin-bottom: 12pt; }
        th, td { padding: 4pt 6pt; text-align: left; border-bottom: 0.5pt solid #ddd; }
        th { font-weight: 600; background: #f5f5f5; }
        td.number, th.number { text-align: right; }

        /* Utility */
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-muted { color: #666; }
        .mb-1 { margin-bottom: 6pt; }
        .mb-2 { margin-bottom: 12pt; }
        .mb-3 { margin-bottom: 18pt; }
        .mt-2 { margin-top: 12pt; }
        .bold { font-weight: 700; }
        .border-top { border-top: 1pt solid #1a1a1a; padding-top: 6pt; }
        .page-break { page-break-after: always; }

        /* Document header/footer */
        .document-header { margin-bottom: 18pt; }
        .document-footer { margin-top: 18pt; font-size: 8pt; color: #666; }

        /* Content body */
        .content-body h1 { font-size: 16pt; margin-top: 16pt; margin-bottom: 8pt; }
        .content-body h2 { font-size: 13pt; margin-top: 12pt; margin-bottom: 6pt; }
        .content-body h3 { font-size: 11pt; margin-top: 10pt; margin-bottom: 4pt; }
        .content-body ul, .content-body ol { margin-left: 16pt; margin-bottom: 8pt; }
        .content-body li { margin-bottom: 3pt; }
        .content-body blockquote { border-left: 2pt solid #ddd; padding-left: 8pt; color: #555; margin: 8pt 0; }
        .content-body hr { border: none; border-top: 0.5pt solid #ddd; margin: 12pt 0; }
        .content-body img { max-width: 100%; height: auto; }

        /* Letter-specific */
        .sender-block { font-size: 9pt; margin-bottom: 24pt; }
        .recipient-block { margin-bottom: 24pt; line-height: 1.6; }
        .subject-line { font-weight: 700; font-size: 11pt; margin-bottom: 12pt; }
        .closing-block { margin-top: 24pt; }
        .signature-block { margin-top: 36pt; }

        /* Table report / KPI */
        .kpi-grid { display: flex; flex-wrap: wrap; gap: 12pt; margin-bottom: 18pt; }
        .kpi-card {
            flex: 1 1 120pt;
            background: #f8f9fa;
            border: 0.5pt solid #e9ecef;
            border-radius: 4pt;
            padding: 10pt 12pt;
            text-align: center;
        }
        .kpi-value { font-size: 20pt; font-weight: 700; color: #1a1a1a; }
        .kpi-label { font-size: 8pt; color: #666; margin-top: 2pt; }
        .total-row { font-weight: 700; background: #f0f0f0; }
        table th { background: #2d3748; color: #fff; }
        table tr:nth-child(even) { background: #f8f9fa; }

        /* Custom styles from template */
        {!! $customStyles ?? '' !!}
    </style>
</head>
<body>
    <div class="page">
        {!! $bodyHtml !!}
    </div>
</body>
</html>
