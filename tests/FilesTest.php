<?php

namespace BuildAgents\AgentEngine\Tests;

use BuildAgents\AgentEngine\View\Components\Chat;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;

class FilesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Gate::define('viewAgentEngine', fn ($user = null) => true);
        Http::fake(['engine.test/*' => Http::response(['session_id' => 's', 'content' => 'Li o arquivo.'])]);
    }

    protected function playground(string $uuid, array $data)
    {
        return $this->post("/agent-engine/panel/agents/{$uuid}/playground", $data, ['Accept' => 'application/json']);
    }

    public function test_playground_sends_files_as_base64(): void
    {
        $agent = $this->makeAgent(['allow_files' => true]);

        $this->playground($agent->uuid, [
            'message' => 'Resuma',
            'files' => [UploadedFile::fake()->createWithContent('nota.txt', 'conteudo da nota')],
        ])->assertOk()->assertJson(['content' => 'Li o arquivo.']);

        Http::assertSent(fn (Request $request) => $request['prompt'] === 'Resuma'
            && $request['files'] === [[
                'filename' => 'nota.txt',
                'mime_type' => 'text/plain',
                'file' => base64_encode('conteudo da nota'),
            ]]);
    }

    public function test_file_without_message(): void
    {
        $agent = $this->makeAgent(['allow_files' => true]);

        $this->playground($agent->uuid, [
            'files' => [UploadedFile::fake()->createWithContent('dados.csv', "a,b\n1,2")],
        ])->assertOk();

        Http::assertSent(fn (Request $request) => $request['prompt'] === '' && count($request['files']) === 1);
    }

    public function test_agent_without_allow_files_is_rejected(): void
    {
        $agent = $this->makeAgent();

        $this->playground($agent->uuid, [
            'message' => 'Oi',
            'files' => [UploadedFile::fake()->createWithContent('nota.txt', 'x')],
        ])->assertUnprocessable()->assertJson(['error' => __('agent-engine::ui.files_not_allowed')]);

        Http::assertNothingSent();
    }

    public function test_validates_count_and_extension(): void
    {
        config(['agent-engine.files.max_files' => 1]);
        $agent = $this->makeAgent(['allow_files' => true]);

        $this->playground($agent->uuid, [
            'files' => [
                UploadedFile::fake()->createWithContent('a.txt', 'a'),
                UploadedFile::fake()->createWithContent('b.txt', 'b'),
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('files');

        $this->playground($agent->uuid, [
            'files' => [UploadedFile::fake()->createWithContent('script.exe', 'MZ')],
        ])->assertUnprocessable()->assertJsonValidationErrors('files.0');

        $this->playground($agent->uuid, [])->assertUnprocessable()->assertJsonValidationErrors('message');

        Http::assertNothingSent();
    }

    public function test_widget_needs_files_in_token(): void
    {
        $agent = $this->makeAgent(['allow_files' => true]);
        $file = fn () => [UploadedFile::fake()->createWithContent('nota.txt', 'x')];

        $this->post('/agent-engine/chat/send', [
            'token' => Chat::encodeToken($agent->uuid),
            'files' => $file(),
        ], ['Accept' => 'application/json'])->assertUnprocessable();

        Http::assertNothingSent();

        $this->post('/agent-engine/chat/send', [
            'token' => Chat::encodeToken($agent->uuid, files: true),
            'files' => $file(),
        ], ['Accept' => 'application/json'])->assertOk();

        Http::assertSentCount(1);
    }

    public function test_component_shows_attach_only_when_enabled(): void
    {
        $enabled = $this->makeAgent(['allow_files' => true]);
        $disabled = $this->makeAgent();

        $render = fn ($agent, string $props) => Blade::render('<x-agent-engine::chat :agent="$agent" '.$props.' />', ['agent' => $agent]);

        $this->assertStringContainsString('ae-attach', $render($enabled, 'files'));
        $this->assertStringContainsString('ae-attach', $render($enabled->uuid, 'files'));
        $this->assertStringNotContainsString('ae-attach', $render($enabled, ''));
        $this->assertStringNotContainsString('ae-attach', $render($disabled, 'files'));

        $html = $render($enabled, 'files');
        preg_match('/&quot;token&quot;:&quot;([^&]+)&quot;|\\\\u0022token\\\\u0022:\\\\u0022([^\\\\]+)\\\\u0022/', $html, $m);
        $token = Chat::decodeToken(stripslashes($m[1] ?: $m[2]));
        $this->assertTrue($token['files']);
    }

    public function test_panel_saves_allow_files(): void
    {
        $agent = $this->makeAgent();

        $this->put("/agent-engine/panel/agents/{$agent->uuid}", ['name' => 'X', 'allow_files' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertTrue($agent->fresh()->allow_files);

        $this->get("/agent-engine/panel/agents/{$agent->uuid}/playground")->assertSee('ae-attach', false);

        $this->put("/agent-engine/panel/agents/{$agent->uuid}", ['name' => 'X'])->assertSessionHasNoErrors();
        $this->assertFalse($agent->fresh()->allow_files);
    }
}
