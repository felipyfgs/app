import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

describe('checklist autXML incremental', () => {
  const source = readFileSync(
    resolve(__dirname, '../../app/components/office/AutXmlOnboardingChecklist.vue'),
    'utf8'
  )

  it('anexa páginas automaticamente e exibe somente o loader transitório', () => {
    expect(source).toContain('usePagedTable<OfficeAutXmlEnrollment>')
    expect(source).toContain('ShellTableFooter')
    expect(source).not.toContain('useInfiniteTable')
    expect(source).not.toContain('ShellInfiniteTableLoader')
    expect(source).not.toContain(':virtualize=')
    expect(source).not.toContain('<UPagination')
    expect(source).not.toContain('Carregar mais')
    expect(source).not.toContain('<UCheckbox')
  })

  it('mantém ações e atualiza a linha sem descartar páginas carregadas', () => {
    expect(source).toContain('@click="enroll(row.original)"')
    expect(source).toContain('@click="confirm(row.original)"')
    expect(source).toContain('@click="inactivate(row.original)"')
    expect(source).toContain('updateEnrollment(response.data)')
    expect(source).toContain('watch(sessionEpoch')
    expect(source).toContain('epoch !== sessionEpoch.value')
  })
})
