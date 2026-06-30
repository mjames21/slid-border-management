<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <!-- Styles -->
        @livewireStyles
    </head>
    <body class="font-sans antialiased">
        <x-banner />

        <div x-data="{ sidebarOpen: false }" class="min-h-screen bg-slate-100">
            <div x-show="sidebarOpen" x-cloak class="fixed inset-0 z-40 bg-slate-900/50 lg:hidden" @click="sidebarOpen = false"></div>

            <aside
                class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col border-r border-slate-200 bg-white transition-transform duration-200 lg:translate-x-0"
                :class="{ 'translate-x-0': sidebarOpen }"
            >
                <div class="flex h-20 items-center gap-3 border-b border-slate-200 px-5">
                    <a href="{{ route(auth()->user()?->is_admin ? 'admin.dashboard.index' : 'dashboard') }}" class="flex items-center gap-3">
                        <x-application-mark class="h-12 w-12" />
                        <span class="text-base font-bold leading-tight text-slate-900">BorderReach<br>Workspace</span>
                    </a>
                </div>

                <nav class="flex-1 space-y-1 overflow-y-auto px-4 py-5">
                    @if(auth()->user()?->is_admin)
                        <div class="px-3 pb-2 text-xs font-bold uppercase tracking-wide text-slate-400">Operations</div>
                        <a href="{{ route('admin.projects.index') }}" class="block rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.projects.*') || request()->routeIs('admin.forms.*') ? 'bg-emerald-50 text-emerald-800' : 'text-slate-700 hover:bg-slate-100' }}">Projects</a>
                        <a href="{{ route('admin.submissions.index') }}" class="block rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.submissions.*') ? 'bg-emerald-50 text-emerald-800' : 'text-slate-700 hover:bg-slate-100' }}">Data</a>
                        <a href="{{ route('admin.dashboard.index') }}" class="block rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.dashboard.*') ? 'bg-emerald-50 text-emerald-800' : 'text-slate-700 hover:bg-slate-100' }}">Analysis</a>
                        <div class="px-3 pb-2 pt-5 text-xs font-bold uppercase tracking-wide text-slate-400">Configuration</div>
                        <a href="{{ route('admin.countries.index') }}" class="block rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.countries.*') ? 'bg-emerald-50 text-emerald-800' : 'text-slate-700 hover:bg-slate-100' }}">Countries</a>
                        <a href="{{ route('admin.border-posts.index') }}" class="block rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.border-posts.*') ? 'bg-emerald-50 text-emerald-800' : 'text-slate-700 hover:bg-slate-100' }}">Border Posts</a>
                        <a href="{{ route('admin.locations.index') }}" class="block rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.locations.*') ? 'bg-emerald-50 text-emerald-800' : 'text-slate-700 hover:bg-slate-100' }}">Locations</a>
                        <div class="px-3 pb-2 pt-5 text-xs font-bold uppercase tracking-wide text-slate-400">Administration</div>
                        <a href="{{ route('admin.deployment-requests.index') }}" class="block rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.deployment-requests.*') ? 'bg-emerald-50 text-emerald-800' : 'text-slate-700 hover:bg-slate-100' }}">Deployment Requests</a>
                        <a href="{{ route('admin.users.index') }}" class="block rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.users.*') ? 'bg-emerald-50 text-emerald-800' : 'text-slate-700 hover:bg-slate-100' }}">Users</a>
                    @else
                        <div class="px-3 pb-2 text-xs font-bold uppercase tracking-wide text-slate-400">Operations</div>
                        <a href="{{ route('dashboard') }}" class="block rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('dashboard') ? 'bg-emerald-50 text-emerald-800' : 'text-slate-700 hover:bg-slate-100' }}">Dashboard</a>
                    @endif

                    <div class="my-4 border-t border-slate-200"></div>

                    <div class="px-3 pb-2 text-xs font-bold uppercase tracking-wide text-slate-400">Account</div>
                    <a href="{{ route('profile.show') }}" class="block rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('profile.show') ? 'bg-emerald-50 text-emerald-800' : 'text-slate-700 hover:bg-slate-100' }}">Profile</a>

                    @if (Laravel\Jetstream\Jetstream::hasApiFeatures())
                        <a href="{{ route('api-tokens.index') }}" class="block rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('api-tokens.index') ? 'bg-emerald-50 text-emerald-800' : 'text-slate-700 hover:bg-slate-100' }}">API Tokens</a>
                    @endif

                    @if (Laravel\Jetstream\Jetstream::hasTeamFeatures() && auth()->user()?->currentTeam)
                        <a href="{{ route('teams.show', auth()->user()->currentTeam->id) }}" class="block rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('teams.show') ? 'bg-emerald-50 text-emerald-800' : 'text-slate-700 hover:bg-slate-100' }}">Team Settings</a>
                        @can('create', Laravel\Jetstream\Jetstream::newTeamModel())
                            <a href="{{ route('teams.create') }}" class="block rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('teams.create') ? 'bg-emerald-50 text-emerald-800' : 'text-slate-700 hover:bg-slate-100' }}">Create Team</a>
                        @endcan
                    @endif
                </nav>

                <div class="border-t border-slate-200 p-4">
                    <div class="mb-3 text-sm">
                        <div class="font-semibold text-slate-900">{{ auth()->user()?->name }}</div>
                        <div class="truncate text-slate-500">{{ auth()->user()?->email }}</div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full rounded-lg bg-slate-800 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-700">
                            Sign out
                        </button>
                    </form>
                </div>
            </aside>

            <div class="lg:pl-72">
                <div class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-slate-200 bg-white px-4 lg:hidden">
                    <button type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700" @click="sidebarOpen = true">
                        Menu
                    </button>
                    <div class="flex items-center gap-2">
                        <x-application-mark class="h-9 w-9" />
                        <span class="font-semibold text-slate-900">BorderReach Workspace</span>
                    </div>
                </div>

                @if (isset($header))
                    <header class="border-b border-slate-200 bg-white">
                        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endif

                <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    {{ $slot }}
                </main>
            </div>
        </div>

        @stack('modals')

        @livewireScripts
    </body>
</html>
