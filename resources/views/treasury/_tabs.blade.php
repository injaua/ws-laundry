{{-- Treasury navigation --}}
<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link {{ request()->routeIs('treasury.cheques') ? 'active' : '' }}" href="{{ route('treasury.cheques') }}"><i class="bi bi-journal-check"></i> {{ __('app.cheques') }}</a></li>
    <li class="nav-item"><a class="nav-link {{ request()->routeIs('treasury.deposits.*') ? 'active' : '' }}" href="{{ route('treasury.deposits.index') }}"><i class="bi bi-bank"></i> {{ __('app.bank_deposits') }}</a></li>
    <li class="nav-item"><a class="nav-link" href="{{ route('wallet-reconciliation.index') }}"><i class="bi bi-check2-all"></i> {{ __('app.wallet_reconciliation') }}</a></li>
</ul>
