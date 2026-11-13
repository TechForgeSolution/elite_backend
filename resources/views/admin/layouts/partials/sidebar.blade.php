<aside class="sidebar">
    <div class="sidebar-header">
        <a href="{{ route('admin.dashboard') }}" class="sidebar-logo">
            <svg class="menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
            </svg>
            <span>Elite Admin</span>
        </a>
    </div>

    <nav class="sidebar-menu">
        <div class="menu-section">
            <div class="menu-section-title">Principal</div>
            <a href="{{ route('admin.dashboard') }}" class="menu-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                <svg class="menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                </svg>
                <span>Tableau de bord</span>
            </a>
        </div>

        <div class="menu-section">
            <div class="menu-section-title">Utilisateurs</div>
            <a href="{{ route('admin.users.index') }}" class="menu-item {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                <svg class="menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
                <span>Utilisateurs</span>
            </a>
        </div>

        <div class="menu-section">
            <div class="menu-section-title">Contenu</div>
            <a href="{{ route('admin.profiles.index') }}" class="menu-item {{ request()->routeIs('admin.profiles.*') ? 'active' : '' }}">
                <svg class="menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                </svg>
                <span>Profils métiers</span>
            </a>
            <a href="{{ route('admin.categories.index') }}" class="menu-item {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}">
                <svg class="menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                </svg>
                <span>Catégories</span>
            </a>
            <a href="{{ route('admin.packs.index') }}" class="menu-item {{ request()->routeIs('admin.packs.*') ? 'active' : '' }}">
                <svg class="menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                </svg>
                <span>Packs formation</span>
            </a>
            <a href="{{ route('admin.advertisements.index') }}" class="menu-item {{ request()->routeIs('admin.advertisements.*') ? 'active' : '' }}">
                <svg class="menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.182 1.038l-4.6-6.138A2 2 0 013 12.94V10a2 2 0 012-2h1.764a2 2 0 001.61-.82l1.626-2.172A1 1 0 0111 5.882zM15 9l4 4m0-4l-4 4" />
                </svg>
                <span>Publicités mobile</span>
            </a>
        </div>

        <div class="menu-section">
            <div class="menu-section-title">Partenaires</div>
            <a href="{{ route('admin.partners.index') }}" class="menu-item {{ request()->routeIs('admin.partners.*') ? 'active' : '' }}">
                <svg class="menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-1a4 4 0 00-4-4h-1m-2 5H4v-1a4 4 0 014-4h4a4 4 0 014 4v1zm-4-9a4 4 0 100-8 4 4 0 000 8zm6 1a3 3 0 100-6 3 3 0 000 6z"></path>
                </svg>
                <span>Gérer les partenaires</span>
            </a>
            <a href="{{ url('/partenaire/login') }}" class="menu-item" target="_blank" rel="noopener">
                <svg class="menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4m-2-8h6v6m-7 1L20 4"></path>
                </svg>
                <span>Portail partenaire</span>
            </a>
        </div>

        <div class="menu-section">
            <div class="menu-section-title">Finance & Comptabilité</div>
            <a href="{{ route('admin.comptabilite.index') }}" class="menu-item {{ request()->routeIs('admin.comptabilite.*') ? 'active' : '' }}">
                <svg class="menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                </svg>
                <span>Comptabilité & Rapports</span>
            </a>
            <a href="{{ route('admin.transactions.index') }}" class="menu-item {{ request()->routeIs('admin.transactions.index') ? 'active' : '' }}">
                <svg class="menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
                <span>Transactions</span>
            </a>
            <a href="{{ route('admin.cash-codes.index') }}" class="menu-item {{ request()->routeIs('admin.cash-codes.*') ? 'active' : '' }}">
                <svg class="menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span>Codes caisse</span>
            </a>
            <a href="{{ route('admin.transactions.settings') }}" class="menu-item {{ request()->routeIs('admin.transactions.settings') ? 'active' : '' }}">
                <svg class="menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                <span>Paramètres</span>
            </a>
        </div>
    </nav>
</aside>