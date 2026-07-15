import { describe, expect, it } from 'vitest'
import { readdirSync, readFileSync, statSync } from 'node:fs'
import { join, resolve, basename } from 'node:path'

const APP_ROOT = resolve(__dirname, '../../app')
const TEST_ROOT = resolve(__dirname, '..')

const FORBIDDEN_PAYLOADS = [
  /-----BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY-----/,
  /-----BEGIN CERTIFICATE-----[\s\S]{20,}-----END CERTIFICATE-----/,
  /<\?xml[\s\S]{0,200}<InfNFSe/i,
  /<\?xml[\s\S]{0,200}<NFe/i
]

function walk(dir: string, acc: string[] = []): string[] {
  for (const entry of readdirSync(dir)) {
    const full = join(dir, entry)
    const st = statSync(full)
    if (st.isDirectory()) {
      walk(full, acc)
    } else if (/\.(vue|ts|js)$/.test(entry) && basename(full) !== 'security-surface.test.ts') {
      acc.push(full)
    }
  }
  return acc
}

describe('superfície sem material sensível', () => {
  const files = [...walk(APP_ROOT), ...walk(TEST_ROOT)]

  it('não embute PFX/PEM/XML bruto em fontes e artefatos de teste', () => {
    const offenders: string[] = []

    for (const file of files) {
      const content = readFileSync(file, 'utf8')
      for (const pattern of FORBIDDEN_PAYLOADS) {
        if (pattern.test(content)) {
          offenders.push(`${file}: ${pattern}`)
        }
      }
    }

    expect(offenders).toEqual([])
  })

  it('páginas de detalhe de nota não renderizam XML bruto', () => {
    const detail = readFileSync(resolve(APP_ROOT, 'components/notes/NotesDetail.vue'), 'utf8')
    const modal = readFileSync(resolve(APP_ROOT, 'components/notes/NotesDetailModal.vue'), 'utf8')
    expect(detail).not.toMatch(/v-html/i)
    expect(modal).not.toMatch(/v-html/i)
    expect(modal).toContain('Baixar XML')
    expect(detail).toMatch(/não é renderizado|download auditado/i)
  })

  it('modal de A1 limpa senha e arquivo ao fechar', () => {
    const panel = readFileSync(resolve(APP_ROOT, 'components/clients/ClientCredentialPanel.vue'), 'utf8')
    expect(panel).toContain('clearSensitive')
    expect(panel).toContain('credentialFile.value = null')
    expect(panel).toContain('state.password = \'\'')
  })
})
