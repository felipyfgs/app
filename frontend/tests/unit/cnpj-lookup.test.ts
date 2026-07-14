import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const APP_ROOT = resolve(__dirname, '../../app')

describe('preenchimento assistido por CNPJ', () => {
  const form = readFileSync(resolve(APP_ROOT, 'components/clients/ClientForm.vue'), 'utf8')
  const api = readFileSync(resolve(APP_ROOT, 'composables/useApi.ts'), 'utf8')
  const registration = readFileSync(resolve(APP_ROOT, 'components/clients/ClientRegistration.vue'), 'utf8')

  it('consulta somente CNPJ numérico completo e sugere a razão social', () => {
    expect(form).toContain('/^\\d{14}$/')
    expect(form).toContain('api.cnpj.lookup(normalizedCnpj.value)')
    expect(form).toContain('state.legal_name = response.data.client.legal_name')
    expect(form).toContain('state.trade_name = response.data.establishment.trade_name')
    expect(form).toContain('@blur="lookupCnpj"')
  })

  it('mantém busca manual explícita e fallback sem bloquear o formulário', () => {
    expect(form).toContain('label="Buscar"')
    expect(form).toContain('Continue o cadastro manualmente')
    expect(form).toContain('type="submit"')
    expect(api).toContain('/api/v1/cnpj/${encodeURIComponent(cnpj)}/lookup')
  })

  it('mantém o formulário básico e envia cadastro transacional', () => {
    expect(form).toContain('cnpj-lookup-preview')
    expect(form).toContain('api.clients.create')
    expect(form).toContain('initial_contact')
    expect(form).toContain('custom_fields')
    expect(form).toContain('api.credentials.activate')
    expect(api).toContain('CreateClientResponse')
  })

  it('seção Cadastro distingue contatos internos do público', () => {
    expect(registration).toContain('Contatos internos')
    expect(registration).toContain('Interno')
    expect(registration).toContain('contato público')
  })
})
