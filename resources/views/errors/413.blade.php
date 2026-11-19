@php($max = number_format(\Symfony\Component\HttpFoundation\File\UploadedFile::getMaxFilesize() / 1048576, 1) . " MB")
<!DOCTYPE html>
<html lang="{{ str_replace("_", "-", app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('app.upload_too_large_title') }}</title>
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
               font-family: system-ui, -apple-system, "Segoe UI", sans-serif; background: #f5f6f8; color: #212529; padding: 16px; }
        .box { max-width: 460px; background: #fff; border-radius: 12px; padding: 32px; box-shadow: 0 2px 12px rgba(0,0,0,.08); text-align: center; }
        h1 { font-size: 1.35rem; margin: 0 0 12px; }
        p { color: #6c757d; line-height: 1.5; margin: 0 0 24px; }
        a { display: inline-block; padding: 10px 20px; border-radius: 8px; background: #0d6efd; color: #fff; text-decoration: none; }
    </style>
</head>
<body>
    <div class="box">
        <h1>{{ __('app.upload_too_large_title') }}</h1>
        <p>{{ __('app.upload_too_large_body', ["max" => $max]) }}</p>
        <a href="{{ url()->previous() }}" onclick="if (history.length > 1) { history.back(); return false; }">{{ __('app.back') }}</a>
    </div>
</body>
</html>
