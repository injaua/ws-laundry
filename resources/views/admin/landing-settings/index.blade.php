@extends("layouts.admin")
@section("title", __('app.landing_page_settings'))
@section("page_title", __('app.landing_page_settings'))

@section("content")
<form method="POST">
    @csrf

    <div class="row">
        {{-- Hero Section --}}
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header"><i class="bi bi-house me-1"></i> Hero Section</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Título Hero</label>
                        <input type="text" name="hero_title" class="form-control"
                            value="{{ $settings["hero_title"] ?? "The trusted laundry in your city" }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subtítulo Hero</label>
                        <textarea name="hero_subtitle" class="form-control" rows="2">{{ $settings["hero_subtitle"] ?? "We keep your clothes clean, fresh, and tidy." }}</textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Texto Botão CTA</label>
                            <input type="text" name="hero_cta_text" class="form-control"
                                value="{{ $settings["hero_cta_text"] ?? "Order Now" }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">URL Botão CTA</label>
                            <input type="text" name="hero_cta_url" class="form-control"
                                value="{{ $settings["hero_cta_url"] ?? "https://wa.me/62" }}">
                        </div>
                    </div>
                </div>
            </div>

            {{-- About Section --}}
            <div class="card mb-4">
                <div class="card-header"><i class="bi bi-info-circle me-1"></i> {{ __('app.about_section') }}</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Título</label>
                        <input type="text" name="about_title" class="form-control"
                            value="{{ $settings["about_title"] ?? "About WS Clean & Laundry Serviços, Lda" }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Conteúdo</label>
                        <textarea name="about_content" class="form-control" rows="4">{{ $settings["about_content"] ?? "WS Clean & Laundry Serviços, Lda is a professional laundry service offering dry cleaning, ironing, and express wash." }}</textarea>
                    </div>
                </div>
            </div>

            {{-- Contact --}}
            <div class="card mb-4">
                <div class="card-header"><i class="bi bi-telephone me-1"></i> {{ __('app.nav_contact') }}</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">WhatsApp</label>
                        <input type="text" name="whatsapp_number" class="form-control"
                            value="{{ $settings["whatsapp_number"] ?? "6281234567890" }}"
                            placeholder="628xxx">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('app.address') }}</label>
                        <textarea name="address" class="form-control" rows="2">{{ $settings["address"] ?? "" }}</textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Google Maps Embed URL</label>
                        <input type="text" name="map_embed_url" class="form-control"
                            value="{{ $settings["map_embed_url"] ?? "" }}"
                            placeholder="https://www.google.com/maps/embed?pb=...">
                    </div>
                </div>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header"><i class="bi bi-link-45deg me-1"></i> Redes Sociais</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Facebook URL</label>
                        <input type="text" name="facebook_url" class="form-control"
                            value="{{ $settings["facebook_url"] ?? "" }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Instagram URL</label>
                        <input type="text" name="instagram_url" class="form-control"
                            value="{{ $settings["instagram_url"] ?? "" }}">
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><i class="bi bi-brush me-1"></i> Rodapé</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Texto Rodapé</label>
                        <input type="text" name="footer_text" class="form-control"
                            value="{{ $settings["footer_text"] ?? "© " . date("Y") . " WS Clean & Laundry Serviços, Lda. All rights reserved." }}">
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 mb-3">
                <i class="bi bi-save me-1"></i> {{ __('app.save') }}
            </button>
            <a href="{{ url("/") }}" class="btn btn-outline-secondary w-100" target="_blank">
                <i class="bi bi-eye me-1"></i> {{ __('app.view') }}
            </a>
        </div>
    </div>
</form>
@endsection
