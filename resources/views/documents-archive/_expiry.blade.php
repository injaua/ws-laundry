{{-- Expiry of a document: red when past, amber when within the next days, plain otherwise. --}}
@php($badge = $badge ?? false)
@if($document->expires_at)
    @if($document->isExpired())
        <span class="badge bg-danger"><i class="bi bi-exclamation-octagon"></i> {{ __('app.doc_expired_on', ['date' => $document->expires_at->format(date_fmt())]) }}</span>
    @elseif($document->isExpiringSoon())
        <span class="badge text-bg-warning"><i class="bi bi-clock"></i> {{ __('app.doc_expires_on', ['date' => $document->expires_at->format(date_fmt())]) }}</span>
    @elseif(!$badge)
        {{ $document->expires_at->format(date_fmt()) }}
    @endif
@elseif(!$badge)
    <span class="text-muted">—</span>
@endif
