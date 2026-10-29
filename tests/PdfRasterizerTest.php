<?php

namespace BuildAgents\AgentEngine\Tests;

use BuildAgents\AgentEngine\Data\RunOptions;
use BuildAgents\AgentEngine\Exceptions\AgentEngineException;
use BuildAgents\AgentEngine\Facades\AgentEngine;
use BuildAgents\AgentEngine\Support\PdfRasterizer;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Throwable;

class PdfRasterizerTest extends TestCase
{
    protected function pdf(string $filename = 'cnh.pdf'): array
    {
        return ['filename' => $filename, 'mime_type' => 'application/pdf', 'file' => base64_encode($this->minimalPdf())];
    }

    /**
     * PDF de uma página em branco, com xref calculado.
     */
    protected function minimalPdf(): string
    {
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 72 72] >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$object}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }

    /**
     * Rasterizer sem pdftoppm: todo PDF "tem" $pageCount páginas.
     */
    protected function fakeRasterizer(int $pageCount = 2): void
    {
        $this->app->instance(PdfRasterizer::class, new class($pageCount) extends PdfRasterizer
        {
            public function __construct(public int $pageCount)
            {
            }

            protected function render(string $pdf, int $lastPage, string $name): array
            {
                return array_map(fn (int $page) => "p{$page}", range(1, min($this->pageCount, $lastPage)));
            }
        });
    }

    public function test_run_rasterizes_only_pdfs_when_requested(): void
    {
        $this->fakeRasterizer();
        Http::fake(['engine.test/*' => Http::response(['session_id' => 's', 'content' => 'ok'])]);
        $agent = $this->makeAgent();

        $txt = ['filename' => 'nota.txt', 'mime_type' => 'text/plain', 'file' => base64_encode('x')];
        $png = ['filename' => 'foto.png', 'mime_type' => 'image/png', 'file' => 'img'];

        AgentEngine::run($agent, 'Extraia', 'abc', new RunOptions(files: [$txt, $this->pdf(), $png], rasterizePdf: true));

        Http::assertSent(fn (Request $request) => $request['files'] === [
            $txt,
            ['filename' => 'cnh-p1.png', 'mime_type' => 'image/png', 'file' => base64_encode('p1')],
            ['filename' => 'cnh-p2.png', 'mime_type' => 'image/png', 'file' => base64_encode('p2')],
            $png,
        ]);
    }

    public function test_pdf_above_max_pages_is_sent_without_conversion(): void
    {
        config(['agent-engine.files.rasterize.max_pages' => 2]);
        Log::spy();
        $pdf = $this->pdf('contrato.pdf');

        $this->fakeRasterizer(pageCount: 2);
        $this->assertCount(2, app(PdfRasterizer::class)->rasterize([$pdf]));

        $this->fakeRasterizer(pageCount: 3);
        $this->assertSame([$pdf], app(PdfRasterizer::class)->rasterize([$pdf]));

        Log::shouldHaveReceived('info')->once()->withArgs(fn (string $message) => str_contains($message, 'contrato'));
    }

    public function test_run_keeps_pdf_by_default(): void
    {
        $this->fakeRasterizer();
        Http::fake(['engine.test/*' => Http::response(['session_id' => 's', 'content' => 'ok'])]);
        $agent = $this->makeAgent();

        AgentEngine::run($agent, 'Extraia', 'abc', new RunOptions(files: [$this->pdf()]));

        Http::assertSent(fn (Request $request) => $request['files'] === [$this->pdf()]);
    }

    public function test_detects_pdf_by_extension_and_ignores_non_array_items(): void
    {
        $this->fakeRasterizer();

        $files = app(PdfRasterizer::class)->rasterize([
            'a.pdf',
            ['filename' => 'DOC.PDF', 'mime_type' => 'application/octet-stream', 'file' => 'x'],
        ]);

        $this->assertSame(['a.pdf', 'DOC-p1.png', 'DOC-p2.png'], array_map(fn ($f) => is_array($f) ? $f['filename'] : $f, $files));
    }

    public function test_missing_binary_throws_and_sends_nothing(): void
    {
        config(['agent-engine.files.rasterize.binary' => 'pdftoppm-inexistente-'.uniqid()]);
        Http::fake();
        $agent = $this->makeAgent();

        $this->expectException(AgentEngineException::class);

        try {
            AgentEngine::run($agent, 'Extraia', 'abc', new RunOptions(files: [$this->pdf()], rasterizePdf: true));
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_real_conversion_with_pdftoppm(): void
    {
        try {
            $available = Process::run(['pdftoppm', '-v'])->successful();
        } catch (Throwable) {
            $available = false;
        }

        if (! $available) {
            $this->markTestSkipped('pdftoppm não está instalado.');
        }

        $files = app(PdfRasterizer::class)->rasterize([$this->pdf()]);

        $this->assertCount(1, $files);
        $this->assertSame('cnh-p1.png', $files[0]['filename']);
        $this->assertSame('image/png', $files[0]['mime_type']);
        $this->assertStringStartsWith("\x89PNG", base64_decode($files[0]['file']));
    }
}
