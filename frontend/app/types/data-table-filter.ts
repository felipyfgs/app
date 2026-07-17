/**
 * Contrato controlado de filtros estruturados de tabela (igualdade).
 * Independente do contrato backend-facing do Monitoramento.
 */

export type DataTableFilterOperator = 'eq'

export type DataTableFilterOptionItem = {
  label: string
  value: string
}

export type DataTableFilterDefinition
  = | {
    key: string
    kind: 'option'
    label: string
    items: DataTableFilterOptionItem[]
    /** Valor vazio/default que não forma chip (default: 'all'). */
    emptyValue?: string
  }
  | {
    key: string
    kind: 'month'
    label: string
    emptyValue?: string
  }
  | {
    key: string
    kind: 'client'
    label: string
    emptyValue?: null
  }

/** Modelo aplicado (chip) — valor bruto + rótulo visual opcional. */
export interface DataTableFilterModel {
  key: string
  operator: DataTableFilterOperator
  /** Identificador bruto (string de option/month ou id numérico de cliente). */
  value: string | number
  /** Rótulo exibido no chip (obrigatório para cliente; option usa label do item). */
  label?: string
}

export type DataTableFilterDraft
  = | {
    mode: 'add' | 'edit'
    key: string
    value: string | number | null
    label?: string
  }
  | null
