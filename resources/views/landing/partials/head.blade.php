{{-- Landing design system: Bootstrap, fonts, tokens and all component CSS (shared by index + order-status) --}}
<link rel="icon" href="{{ \App\Support\SystemSettings::faviconUrl() }}">
@include("partials.bootstrap")
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&display=swap" rel="stylesheet">

@vite(["resources/css/landing.css", "resources/js/landing.js"])
