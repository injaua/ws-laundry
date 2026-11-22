{{-- Shared landing footer (settings-driven) --}}
<footer class="lp-footer">
    <div class="container">
        <div class="footer-grid">
            <div>
                <div class="footer-brand">
                    <img src="{{ \App\Support\SystemSettings::logoUrl() }}" alt="{{ \App\Support\SystemSettings::companyName() }}">
                    <span>{{ \App\Support\SystemSettings::companyName() }}</span>
                </div>
                <p class="mw-320px">{{ __('app.footer_tagline') }}</p>
                <div class="footer-social">
                    @if($settings["facebook_url"] ?? null) <a href="{{ $settings["facebook_url"] }}" target="_blank" rel="noopener" aria-label="Facebook"><i class="bi bi-facebook"></i></a> @endif
                    @if($settings["instagram_url"] ?? null) <a href="{{ $settings["instagram_url"] }}" target="_blank" rel="noopener" aria-label="Instagram"><i class="bi bi-instagram"></i></a> @endif
                    @if($settings["whatsapp_number"] ?? null) <a href="https://wa.me/{{ $settings["whatsapp_number"] }}" target="_blank" rel="noopener" aria-label="WhatsApp"><i class="bi bi-whatsapp"></i></a> @endif
                </div>
            </div>
            <div>
                <h6>{{ __('app.footer_quick_links') }}</h6>
                <ul class="footer-links">
                    <li><a href="{{ route("home") }}#services">{{ __('app.nav_services') }}</a></li>
                    <li><a href="{{ route("home") }}#how">{{ __('app.how_section') }}</a></li>
                    <li><a href="{{ route("home") }}#about">{{ __('app.nav_about') }}</a></li>
                    <li><a href="{{ route("orders.check-status") }}"><i class="bi bi-search me-1"></i>{{ __('app.nav_check_status') }}</a></li>
                </ul>
            </div>
            <div>
                <h6>{{ __('app.footer_contact') }}</h6>
                <div class="footer-contact">
                    @if($settings["address"] ?? null)
                        <span><i class="bi bi-geo-alt"></i>{{ $settings["address"] }}</span>
                    @endif
                    @if($settings["whatsapp_number"] ?? null)
                        <a href="https://wa.me/{{ $settings["whatsapp_number"] }}" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i>+{{ $settings["whatsapp_number"] }}</a>
                    @endif
                    @if($settings["map_embed_url"] ?? null)
                        <a href="#map"><i class="bi bi-map"></i>{{ __('app.nav_contact') }}</a>
                    @endif
                </div>
            </div>
            <div>
                <h6>{{ __('app.cta_title') }}</h6>
                <p class="mb-3">{{ __('app.cta_desc') }}</p>
                @if ($settings["whatsapp_number"] ?? null)
                    <a href="https://wa.me/{{ $settings["whatsapp_number"] }}" class="btn-pill btn-wa-sm" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> {{ $settings["hero_cta_text"] ?? __('app.contact_us') }}</a>
                @elseif ($settings["hero_cta_url"] ?? null)
                    <a href="{{ $settings["hero_cta_url"] }}" class="btn-pill btn-wa-sm" target="_blank" rel="noopener"><i class="bi bi-chat-dots"></i> {{ $settings["hero_cta_text"] ?? __('app.contact_us') }}</a>
                @endif
            </div>
        </div>
        <div class="footer-bottom">
            <span>{{ $settings["footer_text"] ?? __('app.landing_footer_default', ['year' => date('Y'), 'company' => \App\Support\SystemSettings::legalName()]) }}</span>
            <span>{{ \App\Support\SystemSettings::legalName() }}</span>
        </div>
    </div>
</footer>
