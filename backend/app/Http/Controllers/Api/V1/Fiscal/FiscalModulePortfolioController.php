<?php

namespace App\Http\Controllers\Api\V1\Fiscal;

use App\DTO\Fiscal\Module\ModulePortfolioFilters;
use App\Enums\FiscalModuleKey;
use App\Http\Controllers\Controller;
use App\Http\Resources\Fiscal\FiscalModuleClientRowResource;
use App\Http\Resources\Fiscal\FiscalModuleOverviewResource;
use App\Services\FiscalMonitoring\ModulePortfolio\ModulePortfolioQueryService;
use App\Support\CurrentOffice;
use App\Support\FeatureFlags;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Read model de overview + carteira por módulo (tenant-scoped).
 * office_id só via CurrentOffice; query office_id é stripada pelo EnsureOfficeContext.
 */
class FiscalModulePortfolioController extends Controller
{
    public function __construct(
        private readonly CurrentOffice $currentOffice,
        private readonly ModulePortfolioQueryService $portfolio,
    ) {}

    public function overview(Request $request, string $module): JsonResponse
    {
        $this->assertCanRead();
        $moduleKey = $this->resolveModule($module);
        $office = $this->currentOffice->office();
        $this->assertModuleEnabled($moduleKey, (int) $office->id);

        // Nunca confiar em office_id do client (já stripado; reafirma).
        $request->query->remove('office_id');
        $request->request->remove('office_id');

        $this->assertSubmoduleAllowed($request, $moduleKey);
        $filters = ModulePortfolioFilters::fromRequest($request->query->all());
        $dto = $this->portfolio->overview($office, $moduleKey, $filters);

        return (new FiscalModuleOverviewResource($dto))->response();
    }

    public function clients(Request $request, string $module): JsonResponse
    {
        $this->assertCanRead();
        $moduleKey = $this->resolveModule($module);
        $office = $this->currentOffice->office();
        $this->assertModuleEnabled($moduleKey, (int) $office->id);

        $request->query->remove('office_id');
        $request->request->remove('office_id');

        $this->assertSubmoduleAllowed($request, $moduleKey);
        $filters = ModulePortfolioFilters::fromRequest($request->query->all());
        $page = $this->portfolio->clients($office, $moduleKey, $filters);

        return FiscalModuleClientRowResource::collection($page)->response();
    }

    private function resolveModule(string $module): FiscalModuleKey
    {
        $key = FiscalModuleKey::tryFromRoute($module);
        if ($key === null || $key === FiscalModuleKey::Dashboard) {
            abort(404, 'Módulo fiscal desconhecido.');
        }

        return $key;
    }

    private function assertModuleEnabled(FiscalModuleKey $module, int $officeId): void
    {
        $flag = $module->featureFlagKey();
        if ($flag === null || ! FeatureFlags::isModuleEnabled($flag, $officeId)) {
            abort(403, 'Módulo fiscal desabilitado para este escritório.');
        }
    }

    private function assertCanRead(): void
    {
        if ($this->currentOffice->role() === null) {
            abort(403, 'Perfil não resolvido.');
        }
    }

    /**
     * A API nunca redireciona um submódulo removido para outra superfície fiscal.
     * O estado ausente continua selecionando a superfície padrão do módulo.
     */
    private function assertSubmoduleAllowed(Request $request, FiscalModuleKey $module): void
    {
        if (! $request->query->has('submodule')) {
            return;
        }

        $raw = $request->query('submodule');
        $submodule = is_string($raw) ? strtoupper(trim($raw)) : '';
        if ($submodule !== '' && in_array($submodule, $module->knownSubmodules(), true)) {
            return;
        }

        throw ValidationException::withMessages([
            'submodule' => ['Submódulo não disponível para este módulo de monitoramento.'],
        ]);
    }
}
