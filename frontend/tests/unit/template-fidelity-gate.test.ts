import { describe, expect, it } from 'vitest'
import { spawnSync } from 'node:child_process'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')
const script = path.join(root, 'scripts/template-fidelity-gate.mjs')

describe('template fidelity gate', () => {
  it('rejeita wrappers e presets e aceita o contrato LIST literal', () => {
    const result = spawnSync(process.execPath, [script, '--self-test'], {
      cwd: root,
      encoding: 'utf8'
    })

    let payload: {
      ok: boolean
      issues: string[]
      checks: Record<string, boolean>
    }

    try {
      payload = JSON.parse(result.stdout || '{}')
    } catch {
      throw new Error(`Gate não retornou JSON válido.\nstdout: ${result.stdout}\nstderr: ${result.stderr}`)
    }

    expect(payload.ok).toBe(true)
    expect(Object.values(payload.checks)).not.toContain(false)
    expect(payload.issues, payload.issues?.join('\n') || 'issues').toEqual([])
    expect(result.status, result.stderr || result.stdout).toBe(0)
  })
})
