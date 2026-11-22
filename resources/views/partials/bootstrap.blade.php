{{-- Bootstrap + Bootstrap Icons, served by Vite from npm (no CDN). Include before the layout's own stylesheet. --}}
@vite(is_rtl() ? "resources/css/vendor/bootstrap-rtl.css" : "resources/css/vendor/bootstrap.css")
