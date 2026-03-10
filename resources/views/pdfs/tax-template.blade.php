<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        @page {
            size: {{ $orientation === 'landscape' ? 'A4 landscape' : 'A4' }};
            margin: 10mm 12mm;
        }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
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
