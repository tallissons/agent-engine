<?php

namespace BuildAgents\AgentEngine\Data;

class RunOptions
{
    /**
     * @param  array  $files  Arquivos repassados ao serviço como estão.
     * @param  string  $externalReference  Referência devolvida nas respostas assíncronas.
     * @param  bool  $sync  Força resposta síncrona mesmo com webhook_url (ex.: chat).
     * @param  array  $extra  Campos adicionais mesclados ao payload enviado.
     */
    public function __construct(
        public array $files = [],
        public string $externalReference = '',
        public bool $sync = false,
        public array $extra = [],
    ) {
    }
}
