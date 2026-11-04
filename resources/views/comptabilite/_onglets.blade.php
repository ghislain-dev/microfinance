<ul class="nav nav-tabs mb-3">
    @foreach (['comptabilite.balance' => 'Balance', 'comptabilite.journal' => 'Journal', 'comptabilite.plan' => 'Plan SYSCOHADA', 'comptabilite.clotures' => 'Clôtures'] as $route => $titre)
        <li class="nav-item"><a href="{{ route($route) }}" @class(['nav-link', 'active' => request()->routeIs($route)])>{{ $titre }}</a></li>
    @endforeach
</ul>
