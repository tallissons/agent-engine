<?php

namespace BuildAgents\AgentEngine\Support;

use Illuminate\Http\UploadedFile;

/**
 * Converte uploads no formato de arquivos esperado pelo serviço de agentes.
 */
class FileEncoder
{
    /**
     * @param  array<int, UploadedFile>  $uploads
     * @return array<int, array{filename: string, mime_type: string, file: string}>
     */
    public static function encode(array $uploads): array
    {
        return array_values(array_map(fn (UploadedFile $upload) => [
            'filename' => $upload->getClientOriginalName(),
            'mime_type' => $upload->getMimeType() ?: ($upload->getClientMimeType() ?: 'application/octet-stream'),
            'file' => base64_encode((string) file_get_contents($upload->getRealPath())),
        ], $uploads));
    }
}
