<?php

namespace BuildAgents\AgentEngine\Http\Controllers;

use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AssetController extends Controller
{
    public const CSS = __DIR__.'/../../../resources/dist/agent-engine.css';

    public function css(): BinaryFileResponse
    {
        return response()->file(self::CSS, [
            'Content-Type' => 'text/css; charset=UTF-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    /**
     * Versão para cache-busting (?v=...).
     */
    public static function version(): string
    {
        return substr(md5_file(self::CSS) ?: '', 0, 10);
    }
}
