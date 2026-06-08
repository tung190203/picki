<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}" />
    @include('meta._og-meta')
</head>
<body>
    <p>Đang tải...</p>
    <script>window.location.href = "{{ $url }}";</script>
</body>
</html>
