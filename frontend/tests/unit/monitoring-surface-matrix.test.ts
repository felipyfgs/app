/**
 * Task 5.4 — testes dirigidos pela page-payload-matrix:
 * result kinds, documentActionVisible, sem ação em MIT/mailbox/cadastros/e-Processo,
 * contratos estruturais de fonte (sem Playwright / live SERPRO).
 */
import { existsSync, readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'
import {
  ALL_RESULT_KINDS,
  documentActionVisible,
  documentActionVisibleForSurface,
  getMonitoringSurface,
  isStructuredOnlySurface,
  MONITORING_SURFACE_KEYS,
  MONITORING_SURFACE_MATRIX,
  resultKindsInMatrix,
  STRUCTURED_ONLY_SURFACE_KEYS,
  surfaceNeverShowsDocumentAction,
  type FiscalEvidenceDocument,
  type MonitoringResultKind
} from '../../app/utils/monitoring-surfaces'

const APP = resolve(__dirname, '../../app')

function readApp(rel: string): string {
  return readFileSync(resolve(APP, rel), 'utf8')
}

function optionalRead(rel: string): string | null {
  const p = resolve(APP, rel)
  return existsSync(p) ? readFileSync(p, 'utf8') : null
}

describe('monitoring surface matrix (page-payload-matrix)', () => {
  it('enumera todas as surface_key da matriz (17)', () => {
    expect(MONITORING_SURFACE_KEYS).toHaveLength(17)
    expect(new Set(MONITORING_SURFACE_KEYS).size).toBe(17)
    expect(MONITORING_SURFACE_KEYS).toEqual([
      'monitoring_dashboard',
      'simples_mei_pgdasd',
      'simples_mei_pgmei',
      'simples_mei_dasn',
      'simples_mei_regime',
      'dctfweb',
      'mit',
      'fgts',
      'installments',
      'sitfis',
      'mailbox_list',
      'mailbox_detail',
      'declarations',
      'guides',
      'registrations',
      'tax_processes',
      'client_detail'
    ])
  })

  it('cobre STRUCTURED, PDF, ASYNC_PDF, AGGREGATE e UNAVAILABLE', () => {
    const kinds = resultKindsInMatrix().sort()
    expect(kinds).toEqual([...ALL_RESULT_KINDS].sort())

    const byKind = (k: MonitoringResultKind) =>
      MONITORING_SURFACE_MATRIX.filter(e => e.resultKind === k).map(e => e.surfaceKey)

    expect(byKind('STRUCTURED')).toEqual(
      expect.arrayContaining(['mit', 'mailbox_list', 'mailbox_detail', 'registrations', 'tax_processes', 'fgts'])
    )
    expect(byKind('PDF')).toEqual(
      expect.arrayContaining([
        'simples_mei_pgdasd',
        'simples_mei_pgmei',
        'simples_mei_regime',
        'dctfweb',
        'installments',
        'guides'
      ])
    )
    expect(byKind('ASYNC_PDF')).toEqual(['sitfis'])
    expect(byKind('AGGREGATE')).toEqual(
      expect.arrayContaining(['monitoring_dashboard', 'declarations', 'client_detail'])
    )
    expect(byKind('UNAVAILABLE')).toEqual(['simples_mei_dasn'])
  })

  it('MIT, Caixa Postal, Cadastros e e-Processo nunca permitem documento', () => {
    for (const key of STRUCTURED_ONLY_SURFACE_KEYS) {
      const entry = getMonitoringSurface(key)
      expect(entry, key).toBeDefined()
      expect(entry!.resultKind).toBe('STRUCTURED')
      expect(entry!.allowsDocument).toBe(false)
      expect(entry!.neverDocumentAction).toBe(true)
      expect(isStructuredOnlySurface(key)).toBe(true)
      expect(surfaceNeverShowsDocumentAction(key)).toBe(true)
    }
  })

  it('DASN é UNAVAILABLE e nunca mostra ação de documento', () => {
    const dasn = getMonitoringSurface('simples_mei_dasn')!
    expect(dasn.resultKind).toBe('UNAVAILABLE')
    expect(dasn.allowsDocument).toBe(false)
    expect(surfaceNeverShowsDocumentAction('simples_mei_dasn')).toBe(true)
  })

  it('SITFIS é ASYNC_PDF e permite documento somente com artefato', () => {
    const sitfis = getMonitoringSurface('sitfis')!
    expect(sitfis.resultKind).toBe('ASYNC_PDF')
    expect(sitfis.allowsDocument).toBe(true)
    expect(sitfis.neverDocumentAction).toBe(false)
  })
})

describe('documentActionVisible', () => {
  it('só é true com available && href real', () => {
    expect(documentActionVisible(null)).toBe(false)
    expect(documentActionVisible(undefined)).toBe(false)
    expect(documentActionVisible({ available: false, href: null })).toBe(false)
    expect(documentActionVisible({ available: false, href: '/api/v1/fiscal/evidence/1/download' })).toBe(false)
    expect(documentActionVisible({ available: true, href: null })).toBe(false)
    expect(documentActionVisible({ available: true, href: '' })).toBe(false)
    expect(documentActionVisible({ available: true, href: '   ' })).toBe(false)
    expect(documentActionVisible({
      available: true,
      href: '/api/v1/fiscal/evidence/123/download'
    })).toBe(true)
  })

  it('não inventa ação a partir do módulo — exige descriptor', () => {
    // UI nunca monta href só com surface_key
    const forged: FiscalEvidenceDocument = {
      available: true,
      href: null,
      source_surface: 'sitfis'
    }
    expect(documentActionVisible(forged)).toBe(false)
  })

  it('MIT / mailbox / registrations / tax_processes helpers nunca mostram ação', () => {
    const fakeDoc: FiscalEvidenceDocument = {
      available: true,
      href: '/api/v1/fiscal/evidence/999/download',
      source_surface: 'mit'
    }

    for (const key of STRUCTURED_ONLY_SURFACE_KEYS) {
      expect(documentActionVisibleForSurface(key, fakeDoc)).toBe(false)
    }

    // Superfície PDF com o mesmo descriptor inconsistente ainda respeita available+href
    expect(documentActionVisibleForSurface('sitfis', fakeDoc)).toBe(true)
    expect(documentActionVisibleForSurface('sitfis', {
      available: false,
      href: null
    })).toBe(false)
  })
})

describe('source-check: páginas e componentes (contratos estruturais)', () => {
  const structuredPageFiles = [
    'pages/monitoring/dctfweb/index.vue',
    'pages/monitoring/mailbox.vue',
    'pages/monitoring/mailbox/[id].vue',
    'pages/monitoring/mailbox/index.vue',
    'pages/monitoring/registrations.vue',
    'pages/monitoring/tax-processes.vue'
  ] as const

  it('superfícies estruturadas não fabricam download de evidência fiscal genérico', () => {
    for (const rel of structuredPageFiles) {
      const src = readApp(rel)
      // Não montar href de evidência a partir do nome do módulo / client_id
      expect(src, rel).not.toMatch(/evidenceDownloadUrl\s*\(\s*row/)
      expect(src, rel).not.toMatch(/\/api\/v1\/fiscal\/evidence\/\$\{/)
      expect(src, rel).not.toContain('fiscal/evidence/')
    }
  })

  it('MIT submodule path não força botão de PDF genérico', () => {
    const dctf = readApp('pages/monitoring/dctfweb/index.vue')
    // Coluna de evidência não deve inventar link de download de artefato
    expect(dctf).not.toMatch(/evidenceDownloadUrl/)
    expect(dctf).not.toContain('/api/v1/fiscal/evidence/')
  })

  it('mailbox detalhe oficial: sem inventar anexo a partir de surface_key', () => {
    const mail = readApp('components/monitoring/MailboxMail.vue')
    // Download de anexo só com attachmentId da API — não com module name
    expect(mail).toContain('attachmentDownloadUrl')
    expect(mail).toMatch(/openAttachment\(attachmentId/)
    // Não usa endpoint genérico de evidência fiscal como anexo
    expect(mail).not.toContain('evidenceDownloadUrl')
    expect(mail).not.toMatch(/\/api\/v1\/fiscal\/evidence\//)
  })

  it('DASN: página simples-mei não inventa botão de documento por submodule', () => {
    const page = readApp('pages/monitoring/simples-mei/index.vue')
    expect(page).not.toContain('evidenceDownloadUrl')
    expect(page).not.toMatch(/\/api\/v1\/fiscal\/evidence\//)
    // Tabs incluem DASN (query submodule=dasn-simei no path do módulo)
    expect(page).toContain('SIMPLES_MEI_TABS')
  })

  it('quando FiscalDocumentAction existir, superfícies PDF/ASYNC o referenciam ou guardam document.available', () => {
    const componentPath = 'components/monitoring/FiscalDocumentAction.vue'
    const componentSrc = optionalRead(componentPath)

    const documentCapablePages = [
      'pages/monitoring/sitfis.vue',
      'pages/monitoring/simples-mei/index.vue',
      'pages/monitoring/dctfweb/index.vue',
      'pages/monitoring/installments.vue',
      'pages/monitoring/guides.vue',
      'pages/monitoring/declarations.vue'
    ] as const

    if (componentSrc == null) {
      // Task 5.3 ainda pode não ter aplicado o componente — matriz e helpers cobrem a política.
      // Garantia mínima: páginas document-capable não montam href de evidência por convenção solta.
      for (const rel of documentCapablePages) {
        const src = readApp(rel)
        expect(src, rel).not.toMatch(/href:\s*[`'"]\/api\/v1\/fiscal\/evidence\/\$\{/)
      }
      return
    }

    expect(componentSrc).toMatch(/documentActionVisible|document\.available/)

    for (const rel of documentCapablePages) {
      const src = readApp(rel)
      const hasAction = src.includes('FiscalDocumentAction')
      const hasGuard = src.includes('document.available') || src.includes('documentActionVisible')
      expect(
        hasAction || hasGuard,
        `${rel} deve usar FiscalDocumentAction ou guard de document.available`
      ).toBe(true)
    }
  })

  it('utilitário da matriz não vaza coordenadas SERPRO', () => {
    const util = readApp('utils/monitoring-surfaces.ts')
    // Contrato público: nenhuma propriedade/literal de coordenada técnica
    expect(util).not.toMatch(/\bidSistema\b|\bidServico\b|\bid_sistema\b|\bid_servico\b/)
    expect(util).not.toMatch(/operation_key\s*:/)
    expect(util).not.toContain('vault_object_id')
    expect(util).not.toContain('content_sha256')
  })

  it('backend registry test e matriz frontend enumeram o mesmo conjunto de keys', () => {
    const php = readFileSync(
      resolve(__dirname, '../../../backend/tests/Unit/FiscalMonitoring/MonitoringSurfaceRegistryTest.php'),
      'utf8'
    )
    for (const key of MONITORING_SURFACE_KEYS) {
      expect(php, `backend registry test missing ${key}`).toContain(`'${key}'`)
    }
  })
})
