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

    public function urlDownloadDiretoParaVersao(?string $versao): string
    {
        $override = config('elyndor.desktop_download_url');
        if (is_string($override) && $override !== '' && str_ends_with(strtolower($override), '.exe')) {
            return $override;
        }

        $versaoInstalador = $versao ?? '0.1.0';
        $repositorio = (string) config('elyndor.desktop_github_repo', 'lucaswalmor/elyndor-releases');
        $nomeBase = (string) config('elyndor.desktop_installer_basename', 'Elyndor');

        return sprintf(
            'https://github.com/%s/releases/latest/download/%s_%s_x64-setup.exe',
            $repositorio,
            $nomeBase,
            $versaoInstalador,
        );
    }

    public function urlPaginaReleases(): string
    {
        $override = config('elyndor.desktop_download_url');
        if (is_string($override) && $override !== '' && ! str_ends_with(strtolower($override), '.exe')) {
            return $override;
        }

        $repositorio = (string) config('elyndor.desktop_github_repo', 'lucaswalmor/elyndor-releases');

        return "https://github.com/{$repositorio}/releases/latest";
    }

    public function metaPublica(): array
    {
        $registro = $this->versaoAtualDesktop();
        $versao = $registro?->versao ?? '0.1.0';

        return [
            'versao' => $versao,
            'notas' => $registro?->notas,
            'url_download' => $this->urlDownloadDiretoParaVersao($versao),
            'url_releases' => $this->urlPaginaReleases(),
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
                $this->urlDownloadDiretoParaVersao($registro->versao),
            );
        }

        if ($versaoCliente !== $registro->versao) {
            throw new VersaoClienteDesatualizadaException(
                $versaoCliente,
                $registro->versao,
                $this->urlDownloadDiretoParaVersao($registro->versao),
            );
        }
    }
}
