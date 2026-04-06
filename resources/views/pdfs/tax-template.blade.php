<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        @page {
            size: {{ $orientation === 'landscape' ? 'A4 landscape' : 'A4' }};
            margin: 6mm 8mm;
        }
        *, th, td, thead th, thead td, tbody th, tbody td, div, span, p, strong, em, a {
            font-family: DejaVu Sans, Arial, sans-serif !important;
        }
        body {
            margin: 0;
            padding: 0;
            background: #fff;
            color: #000;
        }
    </style>
</head>
<body>
    {!! $content !!}
</body>
</html>
