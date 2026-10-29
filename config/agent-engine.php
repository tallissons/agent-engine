<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Serviço de agentes
    |--------------------------------------------------------------------------
    */

    'url' => env('AGENT_ENGINE_URL'),

    'access_token' => env('AGENT_ENGINE_TOKEN'),

    // Endpoint chamado para executar o agente (concatenado a 'url').
    'endpoint' => env('AGENT_ENGINE_ENDPOINT', '/agente'),

    'timeout' => (int) env('AGENT_ENGINE_TIMEOUT', 120),

    /*
    |--------------------------------------------------------------------------
    | Provider padrão da plataforma
    |--------------------------------------------------------------------------
    | Usado quando o agente não possui uma api key própria (agent_api_keys).
    */

    'default_provider' => env('AGENT_ENGINE_PROVIDER'),

    'default_api_key' => env('AGENT_ENGINE_PROVIDER_KEY'),

    'default_model' => env('AGENT_ENGINE_PROVIDER_MODEL'),

    // Texto anexado à instrução quando o agente usa a chave da plataforma.
    // Deixe null para não anexar nada.
    'instruction_suffix' => env('AGENT_ENGINE_INSTRUCTION_SUFFIX'),

    /*
    |--------------------------------------------------------------------------
    | Banco de dados
    |--------------------------------------------------------------------------
    */

    'tables' => [
        'agents' => 'agents',
        'api_keys' => 'agent_api_keys',
        'tools' => 'agent_tools',
        'subagents' => 'agent_subagents',
    ],

    // Troque por models próprios (que estendam os do pacote) se precisar.
    'models' => [
        'agent' => BuildAgents\AgentEngine\Models\Agent::class,
        'api_key' => BuildAgents\AgentEngine\Models\AgentApiKey::class,
        'tool' => BuildAgents\AgentEngine\Models\AgentTool::class,
    ],

    // Defina false se for publicar e customizar as migrations.
    'run_migrations' => true,

    /*
    |--------------------------------------------------------------------------
    | Subagentes
    |--------------------------------------------------------------------------
    */

    'subagents' => [
        // 1 = só os subagentes diretos (igual ao comportamento original).
        // Valores maiores enviam 'sub_agents' aninhados dentro de cada subagente.
        'max_depth' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook (respostas assíncronas do serviço)
    |--------------------------------------------------------------------------
    */

    'webhook' => [
        'enabled' => env('AGENT_ENGINE_WEBHOOK_ENABLED', true),
        'secret' => env('AGENT_ENGINE_WEBHOOK_SECRET'),
        'prefix' => 'agent-engine',
        'middleware' => ['api'],
        'route_name' => 'agent-engine.webhook',
        // Repassa automaticamente o resultado ao webhook_url do agente.
        'forward' => env('AGENT_ENGINE_WEBHOOK_FORWARD', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Fila usada para repassar respostas ao webhook_url do agente
    |--------------------------------------------------------------------------
    */

    'queue' => [
        'connection' => env('AGENT_ENGINE_QUEUE_CONNECTION'),
        'name' => env('AGENT_ENGINE_QUEUE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Painel (CRUD de agentes, api keys e playground)
    |--------------------------------------------------------------------------
    | Acesso: Gate 'viewAgentEngine'. Sem o Gate definido, só no ambiente local.
    | owner_resolver: classe que implementa PanelOwnerResolver para restringir
    | o painel aos agentes de um dono (tenant, usuário...).
    */

    'panel' => [
        'enabled' => env('AGENT_ENGINE_PANEL_ENABLED', true),
        'path' => 'agent-engine/panel',
        'middleware' => ['web'],
        'owner_resolver' => BuildAgents\AgentEngine\Support\NullOwnerResolver::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Widget de chat (<x-agent-engine::chat />)
    |--------------------------------------------------------------------------
    */

    'widget' => [
        'enabled' => env('AGENT_ENGINE_WIDGET_ENABLED', true),
        'path' => 'agent-engine/chat',
        'middleware' => ['web'],
        // mensagens por minuto, por IP
        'throttle' => 30,
        'max_length' => 4000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Arquivos anexados no chat (playground e widget com a prop "files")
    |--------------------------------------------------------------------------
    | Só valem para agentes com "Aceita arquivos" marcado. São enviados ao
    | serviço em base64: [{filename, mime_type, file}].
    */

    'files' => [
        'max_files' => 5,
        // por arquivo
        'max_size_kb' => 10240,
        'mimes' => ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'txt', 'csv', 'docx', 'xlsx'],
        // Usado quando RunOptions(rasterizePdf: true). Requer poppler-utils (pdftoppm).
        'rasterize' => [
            'binary' => env('AGENT_ENGINE_PDFTOPPM', 'pdftoppm'),
            'dpi' => 200,
            // PDFs com mais páginas que isso vão sem conversão (registrado em log).
            'max_pages' => 5,
        ],
    ],

    'assets' => [
        // false se o projeto já carrega o Alpine.js
        'load_alpine' => true,
        'alpine_url' => 'https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sugestões exibidas no formulário do painel
    |--------------------------------------------------------------------------
    | tools: catálogo slug => rótulo. Vazio = campo livre.
    */

    'providers' => ['gemini', 'openai', 'anthropic'],

    'tools' => [],

];
