<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('agent-engine::ui.panel_title')) · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ route('agent-engine.assets.css', ['v' => \BuildAgents\AgentEngine\Http\Controllers\AssetController::version()]) }}">
    <script defer src="{{ config('agent-engine.assets.alpine_url') }}"></script>
</head>
<body class="ae-root">
    <header class="ae-header">
        <div class="ae-header-inner">
            <a class="ae-brand" href="{{ route('agent-engine.panel.home') }}">{{ __('agent-engine::ui.panel_title') }}</a>
            <nav class="ae-nav">
                <a href="{{ route('agent-engine.panel.agents.index') }}"
                   @class(['is-active' => request()->routeIs('agent-engine.panel.agents.*', 'agent-engine.panel.playground')])>
                    {{ __('agent-engine::ui.nav_agents') }}
                </a>
                <a href="{{ route('agent-engine.panel.api-keys.index') }}"
                   @class(['is-active' => request()->routeIs('agent-engine.panel.api-keys.*')])>
                    {{ __('agent-engine::ui.nav_api_keys') }}
                </a>
            </nav>
        </div>
    </header>

    <main class="ae-main">
        @if (session('agent-engine.status'))
            <div class="ae-alert ae-alert-success" role="status">{{ session('agent-engine.status') }}</div>
        @endif

        @if ($errors->any())
            <div class="ae-alert ae-alert-error" role="alert">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>
