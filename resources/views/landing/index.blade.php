@php
    $heroPhoto = $galleries->first();
    $aboutPhoto = $galleries->count() > 1 ? $galleries->slice(1)->first() : null;
    $cheapest = $services->sortBy("price")->first();
    $avgRating = $testimonials->isNotEmpty() ? round($testimonials->avg("rating"), 1) : null;
    $showSectionLinks = true;
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ is_rtl() ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $settings["hero_title"] ?? "WS Clean & Laundry Serviços, Lda" }} · Laundry Profesional</title>
    <meta name="description" content="{{ Str::limit(strip_tags($settings["hero_subtitle"] ?? "Jasa laundry profesional"), 160) }}">
    @include("landing.partials.head")
</head>
<body>

@include("landing.partials.nav")

{{-- Floating WA --}}
@if ($settings["whatsapp_number"] ?? null)
<a href="https://wa.me/{{ $settings["whatsapp_number"] }}" class="floating-wa" id="floatingWa" target="_blank" rel="noopener" aria-label="WhatsApp">
    <i class="bi bi-whatsapp"></i>
</a>
@endif

{{-- Hero --}}
<section class="lp-hero">
    <div class="container">
        <div class="hero-grid">
            <div>
                <span class="hero-badge"><i class="bi bi-check-circle-fill"></i> {{ __('app.hero_badge') }}</span>
                <h1>{{ $settings["hero_title"] ?? "The trusted laundry in your city" }}</h1>
                <p class="hero-sub">{{ $settings["hero_subtitle"] ?? "We keep your clothes clean, fresh, and tidy." }}</p>
                <div class="hero-ctas">
                    @if ($settings["whatsapp_number"] ?? null)
                        <a href="https://wa.me/{{ $settings["whatsapp_number"] }}" class="btn-pill btn-wa" target="_blank" rel="noopener">
                            <i class="bi bi-whatsapp"></i> {{ $settings["hero_cta_text"] ?? __('app.contact_us') }}
                        </a>
                    @elseif ($settings["hero_cta_url"] ?? null)
                        <a href="{{ $settings["hero_cta_url"] }}" class="btn-pill btn-wa" target="_blank" rel="noopener">
                            <i class="bi bi-chat-dots"></i> {{ $settings["hero_cta_text"] ?? __('app.contact_us') }}
                        </a>
                    @endif
                    <a href="{{ route("orders.check-status") }}" class="btn-pill btn-ghost">
                        <i class="bi bi-search"></i> {{ __('app.nav_check_status') }}
                    </a>
                </div>
                <div class="hero-stats">
                    <div class="hero-stat"><strong>{{ $services->count() }}</strong> {{ __('app.nav_services') }}</div>
                    @if($avgRating !== null)
                        <div class="hero-stat"><strong><i class="bi bi-star-fill"></i> {{ number_format($avgRating, 1) }}</strong> {{ __('app.avg_rating') }}</div>
                    @endif
                    <div class="hero-stat"><strong>{{ $testimonials->count() }}</strong> {{ __('app.nav_testimonials') }}</div>
                    <div class="hero-stat"><strong>{{ $galleries->count() }}</strong> {{ __('app.nav_gallery') }}</div>
                </div>
            </div>
            <div class="hero-visual">
                @if($heroPhoto)
                    <div class="hero-photo">
                        <img src="{{ Storage::url($heroPhoto->photo) }}" alt="{{ $heroPhoto->caption ?? __('app.hero_badge') }}">
                    </div>
                @else
                    <div class="hero-photo icon-fallback"><i class="bi bi-droplet-half"></i></div>
                @endif
                @if($avgRating !== null)
                    <span class="hero-orbit"><i class="bi bi-star-fill"></i> {{ number_format($avgRating, 1) }} · {{ __('app.avg_rating') }}</span>
                @endif
                @if($cheapest)
                    <div class="hero-float">
                        <div class="label">{{ __('app.from_price') }}</div>
                        <div class="value">{{ money($cheapest->price) }}</div>
                        <div class="svc"><i class="bi bi-tag me-1 text-accent"></i>{{ $cheapest->name }}</div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>

{{-- Services --}}
<section id="services">
    <div class="container">
        <div class="mb-4" data-reveal>
            <div class="section-label">{{ __('app.services_section') }}</div>
            <h2 class="section-title">{{ __('app.prices_services') }}</h2>
            <p class="section-desc">{{ __('app.services_desc') }}</p>
        </div>
        <div class="svc-grid">
            @forelse($services as $s)
                <div class="svc-card" data-reveal>
                    @if($s->photo)
                        <div class="svc-thumb">
                            <img src="{{ Storage::url($s->photo) }}" alt="{{ $s->name }}" loading="lazy">
                            <span class="svc-badge {{ $s->type == "by_weight" ? "weight" : "piece" }}">{{ $s->type == "by_weight" ? __('app.by_weight') : __('app.by_piece') }}</span>
                        </div>
                    @else
                        <div class="svc-top">
                            <div class="svc-icon"><i class="bi bi-{{ $s->type == "by_weight" ? "boxes" : "tshirt" }}"></i></div>
                            <span class="svc-badge {{ $s->type == "by_weight" ? "weight" : "piece" }}">{{ $s->type == "by_weight" ? __('app.by_weight') : __('app.by_piece') }}</span>
                        </div>
                    @endif
                    <h5>{{ $s->name }}</h5>
                    <div class="svc-price">{{ num($s->price) }} <small>{{ currency_symbol() }} / {{ $s->type == "by_weight" ? "kg" : __('app.pieces') }}</small></div>
                    <div class="svc-days"><i class="bi bi-clock"></i>{{ $s->estimated_days }} {{ __('app.working_days') }}</div>
                </div>
            @empty
                <div class="os-panel text-center text-muted grid-full">
                    <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                    <p class="mb-0">{{ __('app.no_services_available') }}</p>
                </div>
            @endforelse
        </div>
    </div>
</section>

@if($packages->isNotEmpty())
{{-- Packages --}}
<section class="bg-surface" id="packages">
    <div class="container">
        <div class="mb-4" data-reveal>
            <div class="section-label">{{ __('app.packages_section') }}</div>
            <h2 class="section-title">{{ __('app.packages_title') }}</h2>
            <p class="section-desc">{{ __('app.packages_desc') }}</p>
        </div>
        <div class="svc-grid">
            @foreach($packages as $p)
                @php($kg = rtrim(rtrim(number_format((float) $p->weight_kg, 2, ".", ""), "0"), "."))
                <div class="svc-card" data-reveal>
                    <div class="svc-top">
                        <div class="svc-icon"><i class="bi bi-box-seam"></i></div>
                        <span class="svc-badge package">{{ __('app.by_package') }}</span>
                    </div>
                    <h5>{{ $p->name }}</h5>
                    @if($p->description)
                        <p class="svc-desc">{{ Str::limit($p->description, 90) }}</p>
                    @endif
                    <div class="svc-price">{{ num($p->price) }} <small>{{ currency_symbol() }} / {{ __('app.by_package') }}</small></div>
                    <div class="svc-days"><i class="bi bi-boxes"></i>{{ __('app.package_includes', ['kg' => $kg]) }}</div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- How it works --}}
<section class="lp-how bg-surface" id="how">
    <div class="container">
        <div class="text-center mb-5" data-reveal>
            <div class="section-label">{{ __('app.how_section') }}</div>
            <h2 class="section-title">{{ __('app.how_title') }}</h2>
            <p class="section-desc mx-auto">{{ __('app.how_desc') }}</p>
        </div>
        <div class="steps">
            @foreach([
                ["icon" => "bi-shop", "t" => __('app.step_1_title'), "d" => __('app.step_1_desc')],
                ["icon" => "bi-droplet-half", "t" => __('app.step_2_title'), "d" => __('app.step_2_desc')],
                ["icon" => "bi-wind", "t" => __('app.step_3_title'), "d" => __('app.step_3_desc')],
                ["icon" => "bi-truck", "t" => __('app.step_4_title'), "d" => __('app.step_4_desc')],
            ] as $i => $step)
                <div class="step" data-reveal>
                    <div class="dot"><i class="bi {{ $step["icon"] }}"></i><span class="num">{{ $i + 1 }}</span></div>
                    <h5>{{ $step["t"] }}</h5>
                    <p>{{ $step["d"] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- About --}}
<section id="about">
    <div class="container">
        <div class="about-grid">
            <div class="about-text" data-reveal>
                <div class="section-label">{{ __('app.about_section') }}</div>
                <h2 class="section-title">{{ $settings["about_title"] ?? "About WS Clean & Laundry Serviços, Lda" }}</h2>
                <p>{{ $settings["about_content"] ?? "WS Clean & Laundry Serviços, Lda is a professional laundry service offering dry cleaning, ironing, and express wash." }}</p>
                <div class="about-chips">
                    <span class="chip"><i class="bi bi-boxes"></i>{{ $services->filter(fn($s) => $s->type == "by_weight")->count() }} {{ __('app.by_weight_services') }}</span>
                    <span class="chip"><i class="bi bi-tshirt"></i>{{ $services->filter(fn($s) => $s->type == "by_piece")->count() }} {{ __('app.by_piece_services') }}</span>
                    <span class="chip"><i class="bi bi-clock"></i>{{ $services->min("estimated_days") ?: "-" }} {{ __('app.working_days') }}</span>
                </div>
            </div>
            <div class="about-visual {{ $aboutPhoto ? "" : "icon-fallback" }}" data-reveal>
                @if($aboutPhoto)
                    <img src="{{ Storage::url($aboutPhoto->photo) }}" alt="{{ $aboutPhoto->caption ?? __('app.nav_about') }}" loading="lazy">
                @else
                    <i class="bi bi-droplet"></i>
                @endif
            </div>
        </div>
    </div>
</section>

{{-- Testimonials --}}
@if($testimonials->isNotEmpty())
<section class="lp-testi" id="testimonial">
    <div class="container">
        <div class="mb-4" data-reveal>
            <div class="section-label">{{ __('app.testimonials_section') }}</div>
            <h2 class="section-title">{{ __('app.what_customers_say') }}</h2>
            <p class="section-desc">{{ __('app.customer_trust') }}</p>
        </div>
        <div class="row g-4">
            @foreach($testimonials as $t)
                <div class="col-md-6 col-lg-4">
                    <div class="testi-card" data-reveal>
                        <span class="quote-mark">&ldquo;</span>
                        <div class="stars" aria-label="{{ $t->rating }}/5">
                            @for($i=1;$i<=5;$i++)
                                <i class="bi bi-star{{ $i<=$t->rating ? "-fill" : "" }}"></i>
                            @endfor
                        </div>
                        <blockquote>{{ $t->content }}</blockquote>
                        <div class="author">
                            <div class="avatar">{{ strtoupper(substr($t->customer_name, 0, 1)) }}</div>
                            <div class="name">{{ $t->customer_name }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Gallery --}}
@if($galleries->isNotEmpty())
<section id="gallery">
    <div class="container">
        <div class="mb-4" data-reveal>
            <div class="section-label">{{ __('app.gallery_section') }}</div>
            <h2 class="section-title">{{ __('app.documentation') }}</h2>
            <p class="section-desc">{{ __('app.gallery_desc') }}</p>
        </div>
        <div class="gallery-grid">
            @foreach($galleries as $gi => $g)
                <div class="gallery-item {{ $gi === 0 && $galleries->count() > 2 ? "featured" : "" }}" data-reveal>
                    <img src="{{ Storage::url($g->photo) }}" alt="{{ $g->caption ?? "WS Clean & Laundry Serviços, Lda" }}" loading="lazy">
                    @if($g->caption)
                        <div class="caption">{{ $g->caption }}</div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

@if($zones->isNotEmpty())
@include("landing.partials.pickup")
@endif

{{-- CTA --}}
<section class="lp-cta" id="contact">
    <div class="container">
        <div class="cta-band" data-reveal>
            <h2>{{ __('app.cta_title') }}</h2>
            <p>{{ __('app.cta_desc') }}</p>
            @if ($settings["whatsapp_number"] ?? null)
                <a href="https://wa.me/{{ $settings["whatsapp_number"] }}" class="btn-pill btn-wa" target="_blank" rel="noopener">
                    <i class="bi bi-whatsapp fs-5"></i> {{ $settings["hero_cta_text"] ?? __('app.contact_us') }}
                </a>
            @elseif ($settings["hero_cta_url"] ?? null)
                <a href="{{ $settings["hero_cta_url"] }}" class="btn-pill btn-wa" target="_blank" rel="noopener">
                    <i class="bi bi-chat-dots fs-5"></i> {{ $settings["hero_cta_text"] ?? __('app.contact_us') }}
                </a>
            @else
                <a href="{{ route("orders.check-status") }}" class="btn-pill btn-wa">
                    <i class="bi bi-search"></i> {{ __('app.nav_check_status') }}
                </a>
            @endif
        </div>
    </div>
</section>

{{-- Map --}}
@if($settings["map_embed_url"] ?? null)
<section class="lp-map p-0" id="map">
    <iframe src="{{ $settings["map_embed_url"] }}" height="360" loading="lazy" title="{{ __('app.nav_contact') }}"></iframe>
</section>
@endif

@include("landing.partials.footer")
@include("landing.partials.scripts")
</body>
</html>
