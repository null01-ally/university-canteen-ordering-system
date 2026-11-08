<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Daily sales log · {{ $stall->name }} · {{ $summaryDate->format('Y-m-d') }}</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="shift-print-page"><main><h1>ZPPSU · Daily sales log</h1>@include('staff.summary-content')<button type="button" class="shift-print-link" data-print-report>Print / Save PDF</button><p class="shift-print-help">Use your browser's print dialog to print or save a PDF.</p></main></body></html>

