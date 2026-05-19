<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Client\VersaoClienteDesktopService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InternalVersaoDesktopController extends Controller
{
    public function __construct(
        private VersaoClienteDesktopService $versoes,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'versao' => ['required', 'string', 'regex:/^\d+\.\d+\.\d+$/', 'max:32'],
            'notas' => ['nullable', 'string', 'max:5000'],
        ]);

        $registro = $this->versoes->registrarVersaoDesktop(
            (string) $data['versao'],
            isset($data['notas']) ? (string) $data['notas'] : null,
        );

        return response()->json([
            'data' => [
                'client_type' => $registro->client_type,
                'versao' => $registro->versao,
                'notas' => $registro->notas,
            ],
        ]);
    }
}
