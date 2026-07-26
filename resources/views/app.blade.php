<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        @inertiaHead
        @vite(['resources/css/app.css', 'resources/js/app.ts'])
        @routes
    </head>
    <body class="font-sans antialiased">
        @inertia
        <div id="toaster"></div>
    </body>
</html>
