<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Client\VersaoClienteDesktopService;
use Illuminate\Http\JsonResponse;

class MetaController extends Controller
{
    public function __construct(
        private VersaoClienteDesktopService $versoes,
    ) {}

    public function versaoDesktop(): JsonResponse
    {
        return response()->json([
            'data' => $this->versoes->metaPublica(),
        ]);
    }
}
