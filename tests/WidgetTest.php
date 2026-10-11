<?php

namespace BuildAgents\AgentEngine\Tests;

use BuildAgents\AgentEngine\View\Components\Chat;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;

class WidgetTest extends TestCase
{
    public function test_component_renders_token_and_assets_once(): void
    {
        $agent = $this->makeAgent(['name' => 'Suporte']);

        $html = Blade::render(
            '<x-agent-engine::chat :agent="$agent" floating /><x-agent-engine::chat :agent="$agent" title="Outro" />',
            ['agent' => $agent]
        );

        $this->assertSame(1, substr_count($html, 'agent-engine.css'));
        $this->assertSame(1, substr_count($html, 'alpinejs'));
        $this->assertSame(1, substr_count($html, 'function (config)'));
        $this->assertStringContainsString('ae-floating', $html);
        $this->assertStringContainsString('Suporte', $html);
        $this->assertStringNotContainsString($agent->uuid, $html, 'o uuid só deve ir dentro do token criptografado');
    }

    public function test_alpine_can_be_disabled(): void
    {
        config(['agent-engine.assets.load_alpine' => false]);

        $html = Blade::render('<x-agent-engine::chat agent="abc" />');

        $this->assertStringNotContainsString('alpinejs', $html);
    }

    public function test_send_with_valid_token(): void
    {
        $agent = $this->makeAgent();

        Http::fake(['engine.test/*' => Http::response(['session_id' => 's', 'content' => 'Olá!', 'token_usage' => ['total_token_count' => 3]])]);

        $token = Chat::encodeToken($agent->uuid, 'pagina-contato');

        $this->postJson('/agent-engine/chat/send', ['token' => $token, 'message' => 'Oi'])
            ->assertOk()
            ->assertJson(['content' => 'Olá!', 'token_usage' => null]);

        $this->postJson('/agent-engine/chat/send', ['token' => $token, 'message' => 'De novo'])->assertOk();

        $sessions = collect(Http::recorded())->map(fn ($pair) => $pair[0]['session_id'])->unique();
        $this->assertCount(1, $sessions, 'mesma sessão de conversa entre mensagens');

        Http::assertSent(fn (Request $request) => $request['external_reference'] === 'pagina-contato'
            && $request['response_mode'] === 'sync');
    }

    public function test_reset_starts_new_session(): void
    {
        $agent = $this->makeAgent();
        Http::fake(['engine.test/*' => Http::response(['content' => 'ok'])]);
        $token = Chat::encodeToken($agent->uuid);

        $this->postJson('/agent-engine/chat/send', ['token' => $token, 'message' => 'Oi']);
        $this->postJson('/agent-engine/chat/reset', ['token' => $token])->assertOk();
        $this->postJson('/agent-engine/chat/send', ['token' => $token, 'message' => 'Oi']);

        $sessions = collect(Http::recorded())->map(fn ($pair) => $pair[0]['session_id'])->unique();
        $this->assertCount(2, $sessions);
    }

    public function test_rejects_tampered_token(): void
    {
        $agent = $this->makeAgent();
        Http::fake();

        $this->postJson('/agent-engine/chat/send', ['token' => $agent->uuid, 'message' => 'Oi'])->assertForbidden();
        $this->postJson('/agent-engine/chat/send', ['message' => 'Oi'])->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_engine_error_returns_422_with_message(): void
    {
        $agent = $this->makeAgent();
        Http::fake(['engine.test/*' => Http::response(['detail' => 'The model is overloaded'], 503)]);

        $this->postJson('/agent-engine/chat/send', ['token' => Chat::encodeToken($agent->uuid), 'message' => 'Oi'])
            ->assertUnprocessable()
            ->assertJson(['error' => trans('agent-engine::errors.model_overloaded')]);
    }

    public function test_inactive_agent_is_forbidden(): void
    {
        $agent = $this->makeAgent(['is_active' => false]);
        Http::fake();

        $this->postJson('/agent-engine/chat/send', ['token' => Chat::encodeToken($agent->uuid), 'message' => 'Oi'])
            ->assertForbidden();
    }

    public function test_throttle(): void
    {
        config(['agent-engine.widget.throttle' => 2]);
        $agent = $this->makeAgent();
        Http::fake(['engine.test/*' => Http::response(['content' => 'ok'])]);
        $token = Chat::encodeToken($agent->uuid);

        $this->postJson('/agent-engine/chat/send', ['token' => $token, 'message' => '1'])->assertOk();
        $this->postJson('/agent-engine/chat/send', ['token' => $token, 'message' => '2'])->assertOk();
        $this->postJson('/agent-engine/chat/send', ['token' => $token, 'message' => '3'])->assertTooManyRequests();
    }

    public function test_css_asset(): void
    {
        $this->get(route('agent-engine.assets.css'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/css; charset=UTF-8');
    }
}
