{{--
    Is the store ready to take payments and talk to customers? $readiness comes from
    App\Support\StoreReadiness::for(); links go to the settings pages.
--}}
@php($stateBadge = ["ok" => ["success", "check-circle", __('app.readiness_ok')], "warning" => ["warning", "exclamation-triangle", __('app.readiness_warning')], "problem" => ["danger", "x-octagon", __('app.readiness_problem')]])
@php($waLabel = fn ($c) => match ($c) { "cloud" => __('app.wa_channel_cloud'), "web" => __('app.wa_channel_web'), "log" => __('app.wa_mode_log'), default => __('app.wa_channel_off') })
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="bi bi-plug me-1"></i> {{ __('app.readiness_title') }}</span>
        @php([$color, $icon, $label] = $stateBadge[$readiness['state']])
        <span class="badge bg-{{ $color }} {{ $color === 'warning' ? 'text-dark' : '' }}"><i class="bi bi-{{ $icon }}"></i> {{ $label }}</span>
    </div>
    <div class="card-body">
        <div class="row g-3">
            @foreach(["payments" => ["phone", "admin.mobile-money.edit", __('app.mm_settings')], "whatsapp" => ["whatsapp", "admin.notifications.index", __('app.wa_notifications')]] as $part => [$partIcon, $route, $title])
                @php($r = $readiness[$part])
                @php([$color, $icon] = $stateBadge[$r['state']])
                <div class="col-md-6">
                    <div class="border rounded p-2 h-100">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <span class="fw-semibold"><i class="bi bi-{{ $partIcon }}"></i> {{ $title }}</span>
                            <i class="bi bi-{{ $icon }} text-{{ $color }}"></i>
                        </div>
                        <div class="small text-muted mb-1">{{ $r['own'] ? __('app.readiness_own_config') : __('app.readiness_company_config') }}</div>
                        @if($part === 'payments')
                            <div class="small">
                                @foreach($r['providers'] as $provider => $p)
                                    <span class="me-2">{{ __('app.' . $provider) }}: <span class="fw-semibold">{{ __('app.mm_mode_' . $p['mode']) }}</span></span>
                                @endforeach
                            </div>
                        @else
                            <div class="small">
                                {{ __('app.wa_conversations_channel') }}: <span class="fw-semibold">{{ $waLabel($r['conversation']) }}</span><br>
                                {{ __('app.wa_notifications_channel') }}: <span class="fw-semibold">{{ $waLabel($r['notifications']) }}</span>
                            </div>
                        @endif
                        @if($r['issues'])
                            <ul class="small mb-1 mt-2 ps-3">
                                @foreach($r['issues'] as [$key, $params])
                                    <li class="text-{{ $r['state'] === 'problem' ? 'danger' : 'body' }}">{{ __('app.' . $key, $params) }}</li>
                                @endforeach
                            </ul>
                        @endif
                        <a href="{{ route($route) }}" class="small">{{ __('app.readiness_configure') }} <i class="bi bi-arrow-right"></i></a>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
