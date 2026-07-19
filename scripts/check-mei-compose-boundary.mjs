#!/usr/bin/env node
/**
 * Garante que serviços MEI no Compose não publiquem porta no host.
 * Se ainda não houver serviço MEI (upstream incompleta), sai com SKIP (0).
 */
import { execFileSync } from 'node:child_process'
import { resolve } from 'node:path'

const root = resolve(import.meta.dirname, '..')
const composeFiles = ['docker-compose.yml', 'compose.prod.yml']
const safeEnvironment = {
  ...process.env,
  ACME_EMAIL: 'boundary-check@example.invalid',
  DB_PASSWORD: 'boundary-check',
  PROD_ENV_FILE: '.env.example',
}

function buildContextOf(service) {
  if (typeof service.build === 'object' && service.build !== null) {
    return String(service.build.context ?? '')
  }
  return String(service.build ?? '')
}

function isMeiService(name, service) {
  const buildContext = buildContextOf(service)
  const image = String(service.image ?? '')
  return (
    /^(?:mei|mei[-_])/.test(name)
    || buildContext.includes('/services/mei')
    || buildContext.endsWith('services/mei')
    || /(?:^|[/_-])mei(?:[:_-]|$)/i.test(image)
  )
}

function loadCompose(composeFile) {
  const raw = execFileSync(
    'docker',
    ['compose', '-f', composeFile, 'config', '--format', 'json'],
    { cwd: root, env: safeEnvironment, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] },
  )
  return JSON.parse(raw)
}

let checked = 0

for (const composeFile of composeFiles) {
  const config = loadCompose(composeFile)
  const services = Object.entries(config.services ?? {}).filter(([name, service]) =>
    isMeiService(name, service),
  )

  if (services.length === 0) {
    process.stdout.write(`SKIP ${composeFile}: upstream ainda nao adicionou servicos MEI\n`)
    continue
  }

  for (const [name, service] of services) {
    if (Array.isArray(service.ports) && service.ports.length > 0) {
      throw new Error(`${composeFile}:${name} publica porta no host`)
    }
    process.stdout.write(`PASS ${composeFile}:${name}: somente rede interna\n`)
    checked += 1
  }
}

process.stdout.write(`MEI compose boundary: ${checked} servico(s) verificado(s)\n`)
