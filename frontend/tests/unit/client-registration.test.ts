import { describe, expect, it } from 'vitest'
import { readFileSync, existsSync } from 'node:fs'
import { resolve } from 'node:path'
import {
  registrationSourceLabel,
  registrationStatusLabel,
  registrationStatusIcon,
  registrationStatusColor
} from '../../app/utils/registration-labels'
import { clientSectionPath } from '../../app/composables/useClientDetail'

const APP_ROOT = resolve(__dirname, '../../app')

describe('cadastro ampliado no frontend', () => {
  const detail = readFileSync(resolve(APP_ROOT, 'pages/clients/[id].vue'), 'utf8')
  const form = readFileSync(resolve(APP_ROOT, 'components/clients/ClientForm.vue'), 'utf8')
  const formModal = readFileSync(resolve(APP_ROOT, 'components/clients/ClientFormModal.vue'), 'utf8')
  const clientsIndex = readFileSync(resolve(APP_ROOT, 'pages/clients/index.vue'), 'utf8')
  const registration = readFileSync(resolve(APP_ROOT, 'components/clients/ClientRegistration.vue'), 'utf8')
  const detailModal = readFileSync(resolve(APP_ROOT, 'components/clients/ClientDetailModal.vue'), 'utf8')

  it('detalhe usa rotas aninhadas Settings (não query section)', () => {
    expect(detail).toContain('<NuxtPage')
    expect(detail).toContain('label: \'Cadastro\'')
    expect(detail).toContain('/cadastro')
    expect(detail).toContain('exact: true')
    expect(detail).not.toMatch(/query:\s*\{\s*section:/)
    expect(detail).not.toMatch(/query:\s*\{\s*edit:/)
    expect(detail).toContain('ClientsClientDetailHeader')
    expect(detail).toContain('ClientsClientDetailAside')
    expect(detail).toContain('Voltar aos clientes')
    expect(existsSync(resolve(APP_ROOT, 'pages/clients/[id]/index.vue'))).toBe(true)
    expect(existsSync(resolve(APP_ROOT, 'pages/clients/[id]/cadastro.vue'))).toBe(true)
    expect(existsSync(resolve(APP_ROOT, 'pages/clients/[id]/estabelecimentos.vue'))).toBe(true)
    expect(existsSync(resolve(APP_ROOT, 'pages/clients/[id]/certificado.vue'))).toBe(true)
    expect(existsSync(resolve(APP_ROOT, 'pages/clients/[id]/sincronizacao.vue'))).toBe(true)
  })

  it('clientSectionPath monta paths canônicos', () => {
    expect(clientSectionPath(5)).toBe('/clients/5')
    expect(clientSectionPath(5, 'resumo')).toBe('/clients/5')
    expect(clientSectionPath(5, 'cadastro')).toBe('/clients/5/cadastro')
    // aba de filiais vinculadas (cada uma com cadastro próprio)
    expect(clientSectionPath(5, 'estabelecimentos')).toBe('/clients/5/estabelecimentos')
  })

  it('detalhe lista estabelecimentos como vínculos matriz→filial', () => {
    expect(detail).toContain(`label: 'Estabelecimentos'`)
    expect(detail).toContain(`label: 'Certificado A1'`)
    expect(detail).toContain('ClientsClientDetailHeader')
    expect(existsSync(resolve(APP_ROOT, 'components/clients/ClientBranchesPanel.vue'))).toBe(true)
  })

  it('formulário único cobre criar e editar no mesmo modal', () => {
    expect(form).toContain('isEdit')
    expect(form).toContain('api.clients.create')
    expect(form).toContain('api.clients.update')
    expect(form).toContain('existingClientId')
    expect(form).toContain('existing_client_id')
    expect(form).toContain('Nome fantasia')
    expect(form).toContain('Este número usa WhatsApp')
    expect(form).toContain('Certificado A1 (opcional)')
    expect(form).toMatch(/value: 'SECRET'/)
    expect(formModal).toContain('ClientsClientForm')
    expect(formModal).toContain('data-testid="client-form-modal"')
    expect(clientsIndex).toContain('ClientsClientFormModal')
    expect(clientsIndex).not.toContain('/clients/new')
    expect(clientsIndex).not.toContain('route.query')
    expect(clientsIndex).not.toContain('router.replace')
    expect(clientsIndex).toContain('operational_filter: kpiFilter.value')
    // Aba Cadastro embute o formulário bloqueado até Editar
    expect(registration).toContain('ClientsClientForm')
    expect(registration).toContain(':locked="!editing"')
    expect(registration).toContain('client-registration-edit')
    expect(registration).not.toContain('route.query')
    expect(detailModal).toContain('ClientsClientDashboard')
    expect(detailModal).toContain('openEditForm')
  })

  it('lista colunas com rótulos de documento e certificado digital', () => {
    expect(clientsIndex).toContain(`sortHeader('Razão social / nome'`)
    expect(clientsIndex).toContain(`sortHeader('CNPJ/CPF'`)
    expect(clientsIndex).toContain(`header: 'Certificado digital'`)
    expect(clientsIndex).toContain(`credential: 'Certificado digital'`)
    expect(clientsIndex).toContain(`chipLabel: 'Sem A1'`)
    expect(clientsIndex).toContain('size="md"')
    expect(clientsIndex).toContain('Válido até')
    expect(clientsIndex).not.toContain('label="A1"')
  })

  it('novo cliente global força modo create e zera cliente residual', () => {
    const dashboard = readFileSync(resolve(APP_ROOT, 'composables/useDashboard.ts'), 'utf8')
    expect(dashboard).toContain('clientFormCreateNonce')
    expect(dashboard).toContain('clientFormCreateNonce.value += 1')
    expect(clientsIndex).toContain('clientFormCreateNonce')
    expect(clientsIndex).toMatch(/watch\(clientFormCreateNonce[\s\S]*formClient\.value = null/)
    expect(clientsIndex).toMatch(/watch\(formOpen[\s\S]*formClient\.value = null/)
  })

  it('rótulos de situação e fonte não dependem só de cor', () => {
    expect(registrationStatusLabel('UNKNOWN')).toBe('Não consultada')
    expect(registrationStatusLabel('ACTIVE')).toBe('Ativa')
    expect(registrationStatusIcon('UNKNOWN')).toContain('help')
    expect(registrationStatusColor('CLOSED')).toBe('error')
    expect(registrationSourceLabel('CNPJ_WS')).toBe('CNPJ.ws')
  })
})
