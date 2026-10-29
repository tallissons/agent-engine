# buildagents/agent-engine

Pacote Laravel (10, 11, 12 e 13 · PHP 8.1+; o Laravel 13 exige PHP 8.3+) que integra qualquer projeto ao **serviço de agentes**. Ele traz as tabelas, os models, a montagem do payload, a chamada HTTP e o webhook de respostas assíncronas, então você não precisa recriar a tabela `agents` em cada projeto.

## Instalação

O pacote fica num repositório git privado. No `composer.json` do projeto:

```json
"repositories": [
    { "type": "vcs", "url": "git@github.com:SUA-ORG/agent-engine.git" }
]
```

Durante o desenvolvimento local, use um repositório do tipo `path`:

```json
"repositories": [
    { "type": "path", "url": "../agent-engine", "options": { "symlink": true } }
]
```

Depois:

```bash
composer require buildagents/agent-engine
php artisan vendor:publish --tag=agent-engine-config   # opcional
php artisan migrate
```

### .env

```dotenv
AGENT_ENGINE_URL=https://seu-servico-de-agentes
AGENT_ENGINE_TOKEN=token-do-header-X-Token

# usados quando o agente não tem api key própria
AGENT_ENGINE_PROVIDER=gemini
AGENT_ENGINE_PROVIDER_KEY=...
AGENT_ENGINE_PROVIDER_MODEL=gemini-2.5-flash
AGENT_ENGINE_INSTRUCTION_SUFFIX=   # texto anexado à instrução (opcional)

# respostas assíncronas (modo webhook)
AGENT_ENGINE_WEBHOOK_SECRET=um-segredo-longo
AGENT_ENGINE_WEBHOOK_FORWARD=false # true repassa o resultado ao webhook_url do agente
```

## Tabelas

| Tabela | Conteúdo |
|---|---|
| `agents` | uuid, `owner` (polimórfico), name, description, instruction, `api_key_id`, model_name, temperature, max_tokens, top_p, top_k, response_schema (json), webhook_url, is_active |
| `agent_api_keys` | `owner` (polimórfico), provider, name, api_key (**criptografada**) |
| `agent_tools` | agent_id, tool_slug, name, config (json) |
| `agent_subagents` | agent_id, subagent_id, order |

Para mudar os nomes das tabelas, use `config('agent-engine.tables')`. Para customizar as migrations, publique com `--tag=agent-engine-migrations` e defina `run_migrations => false`.

Para trocar os models, estenda os do pacote e aponte para eles em `config('agent-engine.models')`.

## Uso

```php
use BuildAgents\AgentEngine\Concerns\HasAgents;

class Tenant extends Model
{
    use HasAgents; // $tenant->agents(), $tenant->agentApiKeys()
}
```

```php
use BuildAgents\AgentEngine\Data\RunOptions;
use BuildAgents\AgentEngine\Facades\AgentEngine;

$agent = $tenant->agents()->create([
    'name' => 'Atendimento',
    'instruction' => 'Responda de forma educada.',
]);

$agent->tools()->create(['tool_slug' => 'http_request', 'name' => 'consulta_pedido', 'config' => [...]]);
$agent->subagents()->attach($outroAgente->id);

$result = AgentEngine::run($agent->uuid, $prompt, $sessionId, new RunOptions(
    files: $files,
    externalReference: 'pedido-123',
    sync: true, // força resposta síncrona (ex.: chat), mesmo com webhook_url
    // async: true, // força resposta via webhook, mesmo sem webhook_url
));

if ($result->successful()) {
    $result->content;
    $result->totalTokens();
} elseif ($result->isProcessing()) {
    // modo webhook: o resultado chega depois em AgentCompleted
} else {
    $result->error; // mensagem amigável traduzida
}

return $result->toArray(); // mesmo formato do antigo AgentService::generate()
```

### Modo de resposta

- **sync**: usado quando `sync: true` ou, sem `async`, quando o agente não tem `webhook_url` ou o webhook está desativado.
- **webhook**: usado quando `async: true` ou quando o agente tem `webhook_url`. Exige o webhook ativo e `AGENT_ENGINE_WEBHOOK_SECRET` definido; com `async: true` e o webhook indisponível, o `run()` lança `AgentEngineException` (o mesmo vale para `sync` e `async` juntos). O serviço chama `POST /agent-engine/webhook/{secret}` e o pacote dispara `AgentWebhookReceived` e depois `AgentCompleted` ou `AgentFailed`. Trate o resultado num listener desses eventos.

Por padrão o pacote **não** envia nada ao `webhook_url` do agente. Com `AGENT_ENGINE_WEBHOOK_FORWARD=true`, o job `ForwardToAgentWebhook` envia ao `webhook_url`, pela fila configurada, todos os retornos do webhook e as respostas síncronas com conteúdo.

### Enviando o resultado você mesmo

Com o repasse automático desligado, escute `AgentCompleted` e `AgentFailed`, monte os dados como quiser e envie:

```php
namespace App\Listeners;

use BuildAgents\AgentEngine\Events\AgentCompleted;
use BuildAgents\AgentEngine\Events\AgentFailed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;

class SendAgentResult implements ShouldQueue
{
    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    public function handle(AgentCompleted|AgentFailed $event): void
    {
        // só os retornos assíncronos; respostas sync já vêm no retorno do run()
        if (! $event->viaWebhook || ! $event->agent->webhook_url) {
            return;
        }

        $result = $event->result;

        Http::timeout(30)
            ->withToken(config('services.cliente.token'))
            ->post($event->agent->webhook_url, [
                'pedido' => $result->externalReference,
                'sessao' => $result->sessionId,
                'status' => $result->status, // completed | failed
                'resposta' => $result->content,
                'erro' => $result->error,
                'tokens' => $result->totalTokens(),
            ])
            ->throw();
    }
}
```

No Laravel 11 em diante, listeners em `app/Listeners` são registrados automaticamente. No Laravel 10, registre no `AppServiceProvider::boot()`:

```php
Event::listen(AgentCompleted::class, SendAgentResult::class);
Event::listen(AgentFailed::class, SendAgentResult::class);
```

O `$event->result` (`AgentResult`) traz:

| Campo | Conteúdo |
|---|---|
| `status` | `completed` ou `failed` |
| `content` | resposta do agente (texto ou o JSON do `response_schema`) |
| `sessionId` | id da sessão, já sem o sufixo de tenant |
| `externalReference` | valor passado em `RunOptions(externalReference: ...)` |
| `tokenUsage` / `totalTokens()` | consumo de tokens |
| `error` / `errorCode` | mensagem traduzida e código, em caso de falha |
| `raw` | corpo original enviado pelo serviço |
| `toArray()` | formato padrão do pacote, o mesmo usado no repasse automático |

Observações:

- **Use `ShouldQueue`.** O listener roda dentro da requisição que o serviço faz ao webhook. Sem fila, um destino lento ou fora do ar atrasa ou quebra a resposta ao serviço.
- **`viaWebhook`** é `false` quando o resultado veio de um `run()` síncrono, que você já recebe como retorno. Remova esse filtro se quiser enviar nos dois casos.
- **O destino não precisa ser o `webhook_url`.** Use o `externalReference` para saber a quem o resultado pertence e envie para onde precisar. O `webhook_url` do agente continua necessário para ativar o modo assíncrono.
- **Para aproveitar o job do pacote**, chame `ForwardToAgentWebhook::dispatch($url, $dados)` no listener.

## Painel e widget

O pacote traz telas prontas em Blade + Alpine.js. Não precisa de build, nem de Tailwind ou Vite no projeto.

### Painel: `/agent-engine/panel`

- **Agentes:** lista com busca, criação e edição (dados, modelo, parâmetros, tools, subagentes com ordem, webhook e response schema) e exclusão.
- **API keys:** cadastro por provider. A chave fica criptografada e aparece mascarada; deixar o campo em branco na edição mantém a chave atual.
- **Testar:** playground para conversar com o agente em modo sync, mostrando os tokens de cada resposta.

O acesso usa o Gate `viewAgentEngine`. Sem ele definido, o painel só abre em `APP_ENV=local`:

```php
// AppServiceProvider::boot()
Gate::define('viewAgentEngine', fn ($user) => $user->is_admin);
```

Para restringir o painel aos agentes de um tenant ou usuário, implemente `PanelOwnerResolver`. Os agentes e API keys criados pelo painel recebem esse dono automaticamente:

```php
use BuildAgents\AgentEngine\Contracts\PanelOwnerResolver;

class CurrentTenantResolver implements PanelOwnerResolver
{
    public function resolve(Request $request): ?Model
    {
        return $request->user()?->tenant; // null = mostra todos
    }
}

// config/agent-engine.php → 'panel' => ['owner_resolver' => CurrentTenantResolver::class, ...]
```

Para sugerir as tools no formulário, defina o catálogo em `config('agent-engine.tools')`, por exemplo `['http_request' => 'Requisição HTTP']`. Vazio, o campo fica livre.

### Widget de chat

Coloque o componente em qualquer view Blade:

```blade
{{-- flutuante no canto da tela --}}
<x-agent-engine::chat :agent="$agent" title="Suporte" floating />

{{-- embutido na página --}}
<x-agent-engine::chat agent="uuid-do-agente" height="520px" reference="pagina-contato" />
```

| Atributo | Padrão | Descrição |
|---|---|---|
| `agent` | — | model `Agent` ou uuid |
| `title` | nome do agente | título do chat |
| `placeholder` | "Digite sua mensagem..." | |
| `floating` | `false` | botão flutuante que abre o chat |
| `height` | `480px` | altura do chat |
| `reference` | `null` | enviado como `external_reference` |
| `files` | `false` | permite anexar arquivos (o agente também precisa de "Aceita arquivos") |

### Arquivos no chat

Marque **Aceita arquivos** no cadastro do agente. A partir daí:
- **Chat de teste:** o botão de anexar aparece no playground do painel.
- **Widget:** o botão só aparece se o componente também tiver a opção `files`, por exemplo `<x-agent-engine::chat :agent="$agent" files />`. A permissão viaja dentro do token criptografado, então alterar o HTML não libera o envio.

Os arquivos vão ao serviço em base64, no campo `files`:

```json
"files": [{ "filename": "contrato.pdf", "mime_type": "application/pdf", "file": "<base64>" }]
```

Os limites ficam em `config('agent-engine.files')`: `max_files` (padrão 5), `max_size_kb` por arquivo (padrão 10 MB) e `mimes` (extensões aceitas). Dá para enviar só o arquivo, sem texto.

Pelo backend, use o mesmo formato com `FileEncoder`:

```php
use BuildAgents\AgentEngine\Support\FileEncoder;

AgentEngine::run($agent, 'Resuma', $sessionId, new RunOptions(files: FileEncoder::encode($request->file('files', []))));
```

Lembre de ajustar `upload_max_filesize` e `post_max_size` no PHP se os arquivos forem grandes.

#### Converter PDF em imagem

Quando um PDF vai direto ao modelo, o provedor manda a camada de texto do PDF junto com a imagem da página, e o modelo tende a confiar no texto. Em documentos de identidade (CNH, RG) e PDFs com elementos de segurança, essa camada pode vir suja e o valor extraído sai errado. Nesses casos, peça a conversão na chamada:

```php
AgentEngine::run($agent, 'Extraia os dados', $sessionId, new RunOptions(
    files: FileEncoder::encode([$pdf]),
    rasterizePdf: true,
));
```

Cada PDF vira um PNG por página (`cnh-p1.png`, `cnh-p2.png`...), e os outros arquivos passam intactos. A opção vem desligada por padrão e não vale para o playground e o widget.

- Requer o `pdftoppm` (poppler) no servidor: `apt install poppler-utils`; no Windows, `scoop install poppler` ou `choco install poppler`.
- Se a conversão falhar (por exemplo, sem o `pdftoppm`), `run` lança `AgentEngineException` e nada é enviado.
- PDFs com mais de `max_pages` páginas vão **sem conversão**, como PDF, e o caso fica registrado em `Log::info`. Documentos longos costumam ser contratos e relatórios, em que a camada de texto ajuda.
- Ajustes em `config('agent-engine.files.rasterize')`: `binary` (ou `AGENT_ENGINE_PDFTOPPM`), `dpi` (padrão 200) e `max_pages` (padrão 5).

Como o widget funciona:
- **Segurança:** o componente gera um token criptografado com o uuid do agente, e o endpoint (`POST /agent-engine/chat/send`) só aceita esse token. Ninguém consegue chamar outros agentes pelo uuid.
- **Execução:** sempre em modo sync, e a conversa é mantida na sessão Laravel. "Nova conversa" começa uma sessão nova.
- **Limite:** por padrão, 30 mensagens por minuto por IP (`widget.throttle`).
- **Eventos:** os mesmos eventos valem aqui, então os limites de plano e o registro de uso também se aplicam ao widget.
- **Alpine.js:** se o projeto já carrega o Alpine, use `'assets' => ['load_alpine' => false]` para não carregar duas vezes.

### Customização

- `php artisan vendor:publish --tag=agent-engine-views` copia as views para `resources/views/vendor/agent-engine`.
- O visual usa CSS próprio, com todas as classes prefixadas por `ae-`, para não interferir no projeto. Ele acompanha o modo escuro do sistema; use `data-theme="light"` ou `data-theme="dark"` no elemento para fixar um tema.
- Os caminhos ficam em `panel.path` e `widget.path`. Para desativar o painel ou o widget, use `AGENT_ENGINE_PANEL_ENABLED=false` ou `AGENT_ENGINE_WIDGET_ENABLED=false`.

## Pontos de extensão

### Eventos

| Evento | Quando | Uso típico |
|---|---|---|
| `AgentRunning` | antes da chamada | validar limites de plano/créditos (lance `AgentRunBlocked`) ou alterar `$event->payload` |
| `AgentCompleted` | resposta com conteúdo (sync ou webhook) | registrar consumo de tokens |
| `AgentFailed` | erro do serviço | log, alerta |
| `AgentWebhookReceived` | ao receber o retorno assíncrono | auditoria |

`AgentRunBlocked` é renderizada como HTTP 403 `{"error": "..."}`.

Exemplo de como reproduzir os limites de plano da aplicação original:

```php
Event::listen(AgentRunning::class, function (AgentRunning $event) {
    $tenant = $event->agent->owner;
    $usage = $tenant->agentsUsage()->where('month', date('Y-m'));

    if ($usage->sum('request_count') >= $tenant->plan->max_requests) {
        throw new AgentRunBlocked('Limite de interações com agentes atingido para o plano atual');
    }
});

Event::listen(AgentCompleted::class, function (AgentCompleted $event) {
    (new RegisterAgentUsage())->execute(
        $event->agent,
        $event->result->promptTokens(),
        $event->result->candidatesTokens(),
        $event->result->totalTokens(),
    );
});
```

### Contratos

Sobrescreva no `AppServiceProvider` de cada projeto:

- `SessionResolver`: como o `session_id` é enviado e devolvido. O padrão adiciona `-{dono}_{id}`, por exemplo `abc-tenant_5`, e remove esse sufixo na resposta.
- `InstructionDecorator`: altera a instrução enviada. O padrão anexa `instruction_suffix` ao agente principal quando ele usa a chave da plataforma.

```php
$this->app->bind(InstructionDecorator::class, BuildAgentsInstructionDecorator::class);
```

### Subagentes

`config('agent-engine.subagents.max_depth')` define a profundidade de envio:

- **1** (padrão): só os subagentes diretos, como no código original.
- **Valores maiores**: também enviam `sub_agents` aninhados.

Em qualquer profundidade, ciclos são ignorados.

## Migração do antigo `AgentService`

| Antes | Agora |
|---|---|
| `$service->generate($uuid, $session, $prompt, $ref, $files, $isChat)` | `AgentEngine::run($uuid, $prompt, $session, new RunOptions($files, $ref, $isChat))->toArray()` |
| `config('services.api_agents.*')` | `config('agent-engine.*')` |
| `route('api.webhook', secret)` | rota do pacote `agent-engine.webhook` |
| limites de plano dentro do service | listener de `AgentRunning` |
| `RegisterAgentUsage` dentro do service | listener de `AgentCompleted` (também no modo webhook) |
| `tool->slug` (tabela `tools`) | coluna `tool_slug` em `agent_tools` |
| `apiKey->provider->slug` | coluna `provider` em `agent_api_keys` |
| `token_usage` apenas para admin | sempre em `$result->tokenUsage`; use `toArray(withTokenUsage: true)` quando quiser expor |

## Testes

```bash
composer install
composer test
```
