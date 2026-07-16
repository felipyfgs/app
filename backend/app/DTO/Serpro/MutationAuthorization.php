<?php

namespace App\DTO\Serpro;

/**
 * Autorização tipada de mutação fiscal (Emitir/Declarar/etc.).
 *
 * Nesta change, mutações permanecem bloqueadas: somente `none()` é aceito
 * como “não mutante”. Qualquer pedido mutante exige um fluxo superior futuro
 * com approvals; o booleano genérico foi removido.
 */
final class MutationAuthorization
{
    public function __construct(
        public readonly bool $approved,
        public readonly string $reasonCode,
        public readonly ?string $approverRef = null,
        public readonly ?string $policyVersion = null,
        public readonly ?string $approvedAtIso = null,
    ) {}

    /**
     * Sem autorização de mutação (padrão fail-closed).
     */
    public static function none(): self
    {
        return new self(
            approved: false,
            reasonCode: 'MUTATION_DISABLED',
        );
    }

    /**
     * Nesta change, nenhuma mutação é liberada — mesmo com approved=true
     * o executor revalida e bloqueia Emitir/Declarar/adapters mutantes.
     */
    public function allowsMutatingOperation(string $operationKey, bool $isMutating): bool
    {
        if (! $isMutating) {
            return true;
        }

        // Go-live controlado: Emitir/Declarar e demais mutantes permanecem OFF.
        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function toSanitizedArray(): array
    {
        return [
            'approved' => $this->approved,
            'reason_code' => $this->reasonCode,
            'has_approver' => $this->approverRef !== null && $this->approverRef !== '',
            'policy_version' => $this->policyVersion,
            'approved_at' => $this->approvedAtIso,
        ];
    }
}
