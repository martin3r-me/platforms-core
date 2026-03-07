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

        /* Header/Footer (Browsershot injects these separately) */
        .document-header { margin-bottom: 18pt; }
        .document-footer { margin-top: 18pt; font-size: 8pt; color: #666; }

        @yield('styles')
    </style>
</head>
<body>
    <div class="page">
        @hasSection('header')
            <div class="document-header">
                @yield('header')
            </div>
        @endif

        @yield('content')

        @hasSection('footer')
            <div class="document-footer">
                @yield('footer')
            </div>
        @endif
    </div>
</body>
</html>
