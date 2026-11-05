<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#ffffff">
    <title>{{ $title ?? 'CafePOS' }}</title>
    @vite('resources/css/pos.css')
</head>
<body class="bg-wash font-sans text-ink antialiased">
    {{ $slot }}
</body>
</html>
