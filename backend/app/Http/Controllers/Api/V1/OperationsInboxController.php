<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Operations\OperationsInboxBuilder;
use App\Support\CurrentOffice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperationsInboxController extends Controller
{
    public function __invoke(
        Request $request,
        CurrentOffice $currentOffice,
        OperationsInboxBuilder $inbox,
    ): JsonResponse {
        // office_id do cliente é ignorado; sempre o escritório da sessão.
        $officeId = $currentOffice->id();
        abort_if($officeId === null, 403);

        $severity = $request->query('severity');
        $type = $request->query('type');
        $severity = is_string($severity) ? $severity : null;
        $type = is_string($type) ? $type : null;

        if ($severity !== null && $severity !== '' && ! in_array($severity, OperationsInboxBuilder::SEVERITIES, true)) {
            return response()->json(['message' => 'Severidade inválida.'], 422);
        }
        if ($type !== null && $type !== '' && ! in_array($type, OperationsInboxBuilder::TYPES, true)) {
            return response()->json(['message' => 'Tipo de item inválido.'], 422);
        }

        $limit = min(max((int) $request->query('limit', 50), 1), 100);
        $cursor = $request->query('cursor');
        $cursor = is_string($cursor) ? $cursor : null;

        $payload = $inbox->build(
            officeId: $officeId,
            role: $currentOffice->role(),
            severity: $severity,
            type: $type,
            limit: $limit,
            cursor: $cursor,
        );

        return response()->json($payload);
    }
}
