<?php

namespace App\Services\Client;

use App\Exceptions\VersaoClienteDesatualizadaException;
use App\Models\ProjectVersion;
use Illuminate\Http\Request;

class VersaoClienteDesktopService
{
    public function deveValidar(): bool
    {
        if (! config('elyndor.desktop_version_check_enabled', true)) {
            return false;
        }

        return ! app()->environment('local');
    }

    public function versaoAtualDesktop(): ?ProjectVersion
    {
        return ProjectVersion::query()
            ->where('client_type', ProjectVersion::CLIENT_DESKTOP)
            ->first();
    }

    public function metaPublica(): array
    {
        $registro = $this->versaoAtualDesktop();

        return [
            'versao' => $registro?->versao ?? '0.1.0',
            'notas' => $registro?->notas,
            'url_download' => (string) config('elyndor.desktop_download_url'),
        ];
    }

    public function registrarVersaoDesktop(string $versao, ?string $notas = null): ProjectVersion
    {
        return ProjectVersion::query()->updateOrCreate(
            ['client_type' => ProjectVersion::CLIENT_DESKTOP],
            [
                'versao' => $versao,
                'notas' => $notas,
            ],
        );
    }

    public function lerVersaoDoRequest(Request $request): ?string
    {
        $header = $request->header('X-Client-Version');

        if (is_string($header) && $header !== '') {
            return substr(trim($header), 0, 32);
        }

        return null;
    }

    public function lerTipoClienteDoRequest(Request $request): ?string
    {
        $header = $request->header('X-Client-Type');

        if (is_string($header) && $header !== '') {
            return substr(trim($header), 0, 20);
        }

        $body = $request->input('client_type');

        if (is_string($body) && $body !== '') {
            return substr(trim($body), 0, 20);
        }

        return null;
    }

    public function assertCompativel(Request $request): void
    {
        if (! $this->deveValidar()) {
            return;
        }

        if ($this->lerTipoClienteDoRequest($request) !== ProjectVersion::CLIENT_DESKTOP) {
            return;
        }

        $registro = $this->versaoAtualDesktop();
        if ($registro === null) {
            return;
        }

        $versaoCliente = $this->lerVersaoDoRequest($request);
        if ($versaoCliente === null || $versaoCliente === '') {
            throw new VersaoClienteDesatualizadaException(
                $versaoCliente,
                $registro->versao,
                (string) config('elyndor.desktop_download_url'),
            );
        }

        if ($versaoCliente !== $registro->versao) {
            throw new VersaoClienteDesatualizadaException(
                $versaoCliente,
                $registro->versao,
                (string) config('elyndor.desktop_download_url'),
            );
        }
    }
}
