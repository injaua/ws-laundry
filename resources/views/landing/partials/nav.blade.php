{{-- Shared landing navbar. Pass $navOnDark = true (default) when the hero behind it is dark. --}}
<nav class="lp-nav {{ ($navOnDark ?? true) ? "on-dark" : "" }}" id="navbar">
    <div class="container">
        <a href="{{ route("home") }}" class="lp-brand">
            <img src="{{ \App\Support\SystemSettings::logoUrl() }}" alt="{{ config("app.name") }}">
            <span>{{ \App\Support\SystemSettings::companyName() }}</span>
        </a>
        <button class="lp-nav-toggler" id="navToggler" aria-label="{{ __('app.menu') }}" aria-controls="navLinks" aria-expanded="false"><i class="bi bi-list"></i></button>
        <div class="nav-links" id="navLinks">
            <a href="{{ route("orders.check-status") }}"><i class="bi bi-search me-1"></i>{{ __('app.nav_check_status') }}</a>
            @if(($showSectionLinks ?? false) && $services->isNotEmpty())
                <a href="#services">{{ __('app.nav_services') }}</a>
                @if(($packages ?? collect())->isNotEmpty())<a href="#packages">{{ __('app.packages') }}</a>@endif
                <a href="#how">{{ __('app.how_section') }}</a>
                <a href="#about">{{ __('app.nav_about') }}</a>
                @if($testimonials->isNotEmpty())<a href="#testimonial">{{ __('app.nav_testimonials') }}</a>@endif
                @if($galleries->isNotEmpty())<a href="#gallery">{{ __('app.nav_gallery') }}</a>@endif
                @if(($zones ?? collect())->isNotEmpty())<a href="#pickup">{{ __('app.nav_pickup') }}</a>@endif
                <a href="#contact">{{ __('app.nav_contact') }}</a>
                @if(\App\Services\MobileMoney\MobileMoneySettings::enabledAnywhere())<a href="{{ route('pay.lookup') }}">{{ __('app.nav_pay') }}</a>@endif
            @endif
            <div class="dropdown">
                <a href="#" class="dropdown-toggle text-decoration-none" data-bs-toggle="dropdown">
                    <i class="bi bi-translate"></i> {{ strtoupper(app()->getLocale()) }}
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    @foreach(\App\Support\Languages::active() as $lang)
                        <li><a class="dropdown-item {{ app()->getLocale() == $lang->code ? 'active' : '' }}" href="{{ route('language.switch', $lang->code) }}">{{ $lang->label() }}</a></li>
                    @endforeach
                </ul>
            </div>
            <a href="{{ route("login") }}" class="btn-pill btn-login-sm"><i class="bi bi-box-arrow-in-right"></i> {{ __('app.login') }}</a>
        </div>
    </div>
</nav>
