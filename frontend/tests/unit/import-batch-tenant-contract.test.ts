import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

describe('contrato tenant-scoped do detalhe de importação', () => {
  const page = readFileSync(resolve(__dirname, '../../app/pages/docs/imports/[id].vue'), 'utf8')

  it('invalida lote, itens e polling antes de recarregar outro contexto', () => {
    expect(page).toContain('const { canImportDocuments, sessionEpoch } = useDashboard()')
    expect(page).toContain('watch([publicId, sessionEpoch]')
    expect(page).toContain('{ flush: \'sync\' }')
    expect(page).toContain('batch.value = null')
    expect(page).toContain('itemsFeed.reset()')
    expect(page).toContain('stopPoll()')
    expect(page).toContain('void reload()')
  })

  it('descarta respostas obsoletas do lote, itens e retentativa', () => {
    expect(page).toContain('seq !== batchLoadSeq || epoch !== sessionEpoch.value')
    expect(page).toContain('seq !== itemsLoadSeq || epoch !== sessionEpoch.value')
    expect(page).toContain('isCurrentContext(context, epoch, requestedPublicId)')
  })

  it('preserva o último estado apenas em falha silenciosa do polling', () => {
    expect(page).toContain('if (silent && batch.value)')
    expect(page).toContain('pollError.value = msg')
    expect(page).toMatch(/else \{\s+batch\.value = null\s+itemsLoadSeq \+= 1\s+itemsFeed\.reset\(\)/)
  })
})
