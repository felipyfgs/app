import { existsSync, readdirSync, readFileSync, statSync } from 'node:fs'
import { extname, join, resolve } from 'node:path'

const roots = [
  '.output/public',
  'test-results',
  'playwright-report',
  'tests/e2e/__screenshots__',
  'tests/e2e/support',
  'tests/e2e/monitoring-visual.spec.ts'
]
  .map(path => resolve(path))
  .filter(existsSync)

const textExtensions = new Set(['.css', '.html', '.js', '.json', '.map', '.md', '.txt', '.xml', '.zip', '.ts', '.mjs'])
const forbidden = [
  /-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/i,
  /-----BEGIN CERTIFICATE-----[\s\S]+-----END CERTIFICATE-----/i,
  /<\?xml[\s\S]{0,500}<(?:InfNFSe|NFe|CTe|procNFe)\b/i,
  /\bvault_object_id\b\s*[:=]\s*["']?[^\s,"'}]+/i,
  /\bconsumer[_-]?secret\b\s*[:=]\s*["'][^"']{8,}["']/i,
  /\bauthorization\b\s*[:=]\s*["']bearer\s+[a-z0-9._~-]{16,}["']/i,
  /\b(?:set-cookie|cookie)\b\s*[:=]\s*["'][^"']+=[^"';]{8,}["']/i,
  /\btoken\b\s*[:=]\s*["'][a-z0-9._~-]{20,}["']/i,
  /\b(?:password|senha|pfx_password|private_key)\b\s*[:=]\s*["'](?=[^"']{8,}["'])(?=[^"']*[a-z])(?=[^"']*\d)[^"']+["']/i
]

function filesUnder(root, files = []) {
  const stat = statSync(root)
  if (stat.isFile()) {
    files.push(root)
    return files
  }
  for (const entry of readdirSync(root)) {
    const path = join(root, entry)
    const child = statSync(path)
    if (child.isDirectory()) filesUnder(path, files)
    else files.push(path)
  }
  return files
}

const offenders = []
for (const root of roots) {
  for (const file of filesUnder(root)) {
    if (!textExtensions.has(extname(file).toLowerCase())) continue
    const content = readFileSync(file, 'utf8')
    for (const pattern of forbidden) {
      if (pattern.test(content)) offenders.push(`${file}: ${pattern}`)
    }
  }
}

if (offenders.length) {
  console.error('Material sensível detectado em artefatos:')
  console.error(offenders.join('\n'))
  process.exit(1)
}

console.log(`Varredura concluída em ${roots.length} raiz(es), sem material sensível.`)
