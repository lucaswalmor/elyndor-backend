<?php

namespace App\Exceptions;

use Exception;

class VersaoClienteDesatualizadaException extends Exception
{
    public function __construct(
        public readonly ?string $versaoCliente,
        public readonly string $versaoExigida,
        public readonly string $urlDownload,
    ) {
        parent::__construct('Atualize o cliente desktop para continuar.');
    }
}
