/**
 * Scanner de artefatos sensíveis (screenshots, traces, reports, fixtures, build).
 * Rejeita PFX, senha, PEM, chave privada, token, Consumer Secret, Termo XML,
 * XML fiscal real, cookie, vault_object_id e resposta externa bruta.
 *
 * Uso: pnpm test:artifacts (cwd = frontend/)
 */
import { existsSync, readdirSync, readFileSync, statSync } from 'node:fs'
import { extname, join, resolve } from 'node:path'

const roots = [
  '.output/public',
  'test-results',
  'playwright-report',
  'tests/e2e/__screenshots__',
  'tests/e2e/support',
  'tests/e2e',
  'tests/unit'
]
  .map(path => resolve(path))
  .filter(existsSync)

const textExtensions = new Set([
  '.css', '.html', '.js', '.json', '.map', '.md', '.txt', '.xml', '.zip',
  '.ts', '.mjs', '.vue', '.yml', '.yaml', '.webm', '.har'
])

/** Cada item: id legível + regex. */
const forbidden = [
  { id: 'PRIVATE_KEY_PEM', re: /-----BEGIN (?:RSA |EC |OPENSSH |ENCRYPTED )?PRIVATE KEY-----/i },
  { id: 'CERTIFICATE_PEM_BLOCK', re: /-----BEGIN CERTIFICATE-----[\s\S]{32,}-----END CERTIFICATE-----/i },
  { id: 'PFX_BINARY_HINT', re: /\b(?:MII[A-Za-z0-9+/]{40,}={0,2})\b.*(?:pfx|pkcs12|bag attributes)/i },
  { id: 'PFX_PASSWORD_FIELD', re: /\b(?:pfx[_-]?password|pkcs12[_-]?password)\b\s*[:=]\s*["'][^"']+["']/i },
  { id: 'FISCAL_XML_REAL', re: /<\?xml[\s\S]{0,800}<(?:InfNFSe|NFe|CTe|procNFe|nfeProc|cteProc|NFSe)\b/i },
  { id: 'TERMO_XML', re: /<\s*TermoDeAutorizacao\b|<\s*termoDeAutorizacao\b|Termo\s+de\s+Autoriza[cç][aã]o[\s\S]{0,200}<[?]xml/i },
  { id: 'VAULT_OBJECT_ID', re: /\bvault_object_id\b\s*[:=]\s*["']?[A-Za-z0-9._-]{8,}/i },
  { id: 'STORAGE_PATH_SECRET', re: /\bstorage_path\b\s*[:=]\s*["'][^"']+["']/i },
  { id: 'EVIDENCE_BYTES', re: /\bevidence_bytes\b\s*[:=]/i },
  { id: 'CONSUMER_SECRET', re: /\bconsumer[_-]?secret\b\s*[:=]\s*["'][^"']{8,}["']/i },
  { id: 'BEARER_TOKEN', re: /\bauthorization\b\s*[:=]\s*["']bearer\s+[a-z0-9._~-]{16,}["']/i },
  { id: 'RAW_COOKIE', re: /\b(?:set-cookie|cookie)\b\s*[:=]\s*["'][^"']+=[^"';]{8,}["']/i },
  { id: 'OPAQUE_TOKEN', re: /\b(?:access_token|refresh_token|api_token)\b\s*[:=]\s*["'][a-z0-9._~-]{20,}["']/i },
  { id: 'PASSWORD_FIELD', re: /\b(?:password|senha|private_key|privateKey)\b\s*[:=]\s*["'](?=[^"']{8,}["'])(?=[^"']*[a-z])(?=[^"']*\d)[^"']+["']/i },
  { id: 'SERPRO_RAW_RESPONSE', re: /\b(?:serpro[_-]?raw|raw[_-]?response|integra[_-]?raw)\b\s*[:=]\s*["'{[]/i }
]

function filesUnder(root, files = []) {
  const stat = statSync(root)
  if (stat.isFile()) {
    files.push(root)
    return files
  }
  for (const entry of readdirSync(root)) {
    // Ignorar node_modules e caches acidentais
    if (entry === 'node_modules' || entry === '.git' || entry === 'dist') continue
    const path = join(root, entry)
    const child = statSync(path)
    if (child.isDirectory()) filesUnder(path, files)
    else files.push(path)
  }
  return files
}

const offenders = []
const scanned = []
for (const root of roots) {
  for (const file of filesUnder(root)) {
    const ext = extname(file).toLowerCase()
    // Binários de imagem: apenas nome; conteúdo binário não é regex-friendly
    if (!textExtensions.has(ext) && !['.png', '.jpg', '.jpeg', '.webp'].includes(ext)) continue
    if (['.png', '.jpg', '.jpeg', '.webp', '.webm'].includes(ext)) {
      // Heurística leve em metadados/nome
      const base = file.toLowerCase()
      if (base.includes('pfx') || base.includes('private-key') || base.includes('termo-xml')) {
        offenders.push(`${file}: SENSITIVE_FILENAME`)
      }
      continue
    }
    let content
    try {
      content = readFileSync(file, 'utf8')
    } catch {
      continue
    }
    scanned.push(file)
    for (const { id, re } of forbidden) {
      if (re.test(content)) offenders.push(`${file}: ${id}`)
    }
  }
}

if (offenders.length) {
  console.error('Material sensível detectado em artefatos:')
  console.error([...new Set(offenders)].join('\n'))
  process.exit(1)
}

console.log(`Varredura concluída em ${roots.length} raiz(es), ${scanned.length} arquivo(s) texto, sem material sensível.`)
