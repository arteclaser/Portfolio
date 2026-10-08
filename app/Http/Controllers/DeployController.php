<?php

namespace App\Http\Controllers;

use App\Services\DeployFinisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** POST /_implantacao/finalizar: chamado pelo GitHub Actions ao fim da implantação por FTP. */
class DeployController extends Controller
{
    public function __invoke(Request $request, DeployFinisher $finisher): JsonResponse
    {
        // Sem código válido, a rota se comporta como inexistente.
        abort_unless($finisher->tokenMatches((string) $request->header('X-Implantacao-Token')), 404);

        $result = $finisher->finish();

        return response()->json($result, match ($result['status']) {
            'concluido' => 200,
            'configurar' => 202,
            default => 500,
        })->header('Cache-Control', 'no-store');
    }
}
