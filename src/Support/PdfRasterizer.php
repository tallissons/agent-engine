<?php

namespace BuildAgents\AgentEngine\Support;

use BuildAgents\AgentEngine\Exceptions\AgentEngineException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Throwable;

/**
 * Converte PDFs em PNG (uma imagem por página) com o pdftoppm (poppler-utils).
 *
 * Útil quando a camada de texto do PDF atrapalha a extração (ex.: documentos
 * de identidade com elementos de segurança).
 */
class PdfRasterizer
{
    /**
     * Troca cada PDF da lista pelas imagens de suas páginas. O resto passa intacto.
     *
     * @throws AgentEngineException quando a conversão falha.
     */
    public function rasterize(array $files): array
    {
        $result = [];

        foreach ($files as $file) {
            if (! $this->isPdf($file)) {
                $result[] = $file;

                continue;
            }

            array_push($result, ...$this->pages($file));
        }

        return $result;
    }

    protected function isPdf(mixed $file): bool
    {
        return is_array($file) && isset($file['file'])
            && (($file['mime_type'] ?? null) === 'application/pdf'
                || str_ends_with(strtolower((string) ($file['filename'] ?? '')), '.pdf'));
    }

    /**
     * Imagens das páginas do PDF. Acima de max_pages, devolve o PDF original.
     *
     * @return array<int, array{filename: string, mime_type: string, file: string}>
     */
    protected function pages(array $file): array
    {
        $name = pathinfo((string) ($file['filename'] ?? 'documento.pdf'), PATHINFO_FILENAME);
        $maxPages = max(1, (int) config('agent-engine.files.rasterize.max_pages', 5));

        // Uma página a mais que o limite só para saber se ele foi ultrapassado.
        $images = $this->render((string) base64_decode((string) $file['file'], true), $maxPages + 1, $name);

        if (count($images) > $maxPages) {
            Log::info("agent-engine: PDF \"{$name}\" tem mais de {$maxPages} páginas; enviado sem conversão.");

            return [$file];
        }

        $pages = [];

        foreach ($images as $i => $image) {
            $pages[] = [
                'filename' => "{$name}-p".($i + 1).'.png',
                'mime_type' => 'image/png',
                'file' => base64_encode($image),
            ];
        }

        return $pages;
    }

    /**
     * Roda o pdftoppm até a página $lastPage e devolve o conteúdo de cada PNG, em ordem.
     *
     * @return array<int, string>
     *
     * @throws AgentEngineException quando o pdftoppm falha ou não gera nenhuma página.
     */
    protected function render(string $pdf, int $lastPage, string $name): array
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'agent-engine-'.Str::random(16);

        File::ensureDirectoryExists($dir);

        try {
            File::put($dir.'/in.pdf', $pdf);

            try {
                $process = Process::path($dir)->run([
                    config('agent-engine.files.rasterize.binary', 'pdftoppm'),
                    '-png',
                    '-r', (string) (int) config('agent-engine.files.rasterize.dpi', 200),
                    '-l', (string) $lastPage,
                    'in.pdf',
                    'out',
                ]);
            } catch (Throwable $e) {
                throw new AgentEngineException("Falha ao converter o PDF \"{$name}\": {$e->getMessage()}", 0, $e);
            }

            if (! $process->successful()) {
                throw new AgentEngineException("Falha ao converter o PDF \"{$name}\" (pdftoppm instalado?): ".trim($process->errorOutput()));
            }

            $images = glob($dir.'/out-*.png') ?: [];
            natsort($images);

            if ($images === []) {
                throw new AgentEngineException("O PDF \"{$name}\" não gerou nenhuma página.");
            }

            return array_map(fn (string $path) => (string) file_get_contents($path), array_values($images));
        } finally {
            File::deleteDirectory($dir);
        }
    }
}
