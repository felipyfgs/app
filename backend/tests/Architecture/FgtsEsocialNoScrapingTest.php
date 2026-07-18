<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Proíbe scraping, Gov.br, CAPTCHA, cookie/sessão humana no módulo FGTS/eSocial.
 *
 * Escopo: Services/Esocial, Jobs relacionados, Controllers FGTS, Contracts/DTO eSocial.
 * Heurística estática — falha se padrões proibidos forem introduzidos.
 */
class FgtsEsocialNoScrapingTest extends TestCase
{
    /**
     * Diretórios relativos a app/ que compõem o módulo.
     *
     * @var list<string>
     */
    private const MODULE_PATHS = [
        'Services/Esocial',
        'Contracts/EsocialEventClient.php',
        'DTO/Esocial',
        'Http/Controllers/Api/V1/Fiscal/FgtsEsocialController.php',
        'Jobs/Fiscal/SyncFgtsEsocialCompetenceJob.php',
        'Models/EsocialEventEvidence.php',
        'Models/FgtsCompetenceStatus.php',
        'Enums/EsocialEventCode.php',
        'Enums/FgtsIndependentState.php',
    ];

    /**
     * Padrões proibidos (scraping / portal humano / sessão).
     *
     * @var list<array{0:string,1:string}>
     */
    private const FORBIDDEN = [
        ['/scraping/i', 'scraping'],
        ['/\bscrape\b/i', 'scrape'],
        ['/gov\.br/i', 'gov.br'],
        ['/captcha/i', 'CAPTCHA'],
        ['/recaptcha/i', 'reCAPTCHA'],
        ['/hcaptcha/i', 'hCaptcha'],
        ['/puppeteer/i', 'puppeteer'],
        ['/playwright/i', 'playwright'],
        ['/selenium/i', 'selenium'],
        ['/headless\s*chrome/i', 'headless chrome'],
        ['/chromedriver/i', 'chromedriver'],
        ['/webdriver/i', 'webdriver'],
        ['/session\s*cookie/i', 'session cookie'],
        ['/cookie\s*jar/i', 'cookie jar'],
        ['/CookieJar\b/', 'CookieJar'],
        ['/GuzzleHttp\\\\Cookie/i', 'Guzzle Cookie'],
        ['/Set-Cookie/i', 'Set-Cookie'],
        ['/login\.gov\.br/i', 'login.gov.br'],
        ['/sso\.gov\.br/i', 'sso.gov.br'],
        ['/fgts\.digital/i', 'fgts.digital portal'],
        ['/portal\.fgts/i', 'portal.fgts'],
        ['/procura[cç][aã]o\s+de\s+sess[aã]o/i', 'procuração de sessão'],
        ['/automa[cç][aã]o\s+de\s+navegador/i', 'automação de navegador'],
        ['/browser\s*automation/i', 'browser automation'],
        ['/\bcurl_init\s*\(/i', 'curl_init (HTTP bruto no módulo)'],
        ['/\bfile_get_contents\s*\(\s*[\'"]https?:/i', 'file_get_contents HTTP'],
    ];

    /**
     * Menções educativas permitidas apenas em strings de limitação/documentação
     * quando explicitamente negam o uso (ex.: "sem scraping").
     *
     * @var list<string>
     */
    private const ALLOWED_NEGATION_SNIPPETS = [
        'sem scraping',
        'não faz scraping',
        'nao faz scraping',
        'sem api',
        'não são fallback',
        'nao sao fallback',
        'não é fallback',
        'nao e fallback',
        'must not',
        'mustnot',
        'proíbe',
        'proibe',
        'proibido',
        'não há integração',
        'nao ha integracao',
        'sem portal',
        'sem captcha',
        'gov.br, captcha',
        'scraping / gov.br',
        'scraping, gov.br',
        'scraping/gov.br',
        'sem scraping, gov.br, captcha ou cookie',
        'portal_fallback',
        'scraping_allowed',
        'sem portal humano',
        'automação de browser',
        'automacao de browser',
    ];

    public function test_modulo_fgts_esocial_nao_contem_scraping_nem_sessao_humana(): void
    {
        $appRoot = dirname(__DIR__, 2).'/app';
        $this->assertDirectoryExists($appRoot);

        $hits = [];
        foreach ($this->collectPhpFiles($appRoot) as $rel => $content) {
            foreach (self::FORBIDDEN as [$pattern, $label]) {
                if (! preg_match($pattern, $content)) {
                    continue;
                }
                // Permite menção negada em comentários/strings de limitação
                if ($this->isOnlyNegatedMention($content, $pattern)) {
                    continue;
                }
                $hits[] = "{$rel}: {$label}";
            }
        }

        $this->assertSame(
            [],
            $hits,
            "Padrões proibidos no módulo FGTS/eSocial:\n- ".implode("\n- ", $hits)
        );
    }

    public function test_modulo_nao_declara_debito_fgts_digital_como_fonte(): void
    {
        $appRoot = dirname(__DIR__, 2).'/app';
        $hits = [];

        foreach ($this->collectPhpFiles($appRoot) as $rel => $content) {
            if (preg_match('/debito_fgts_digital\s*=\s*true/i', $content)
                || preg_match('/declares_fgts_digital_debt[\'"]?\s*=>\s*true/i', $content)
                || preg_match('/FGTS_DIGITAL_DEBT\s*=/', $content)
            ) {
                // Analyzer lista códigos proibidos — ok
                if (str_contains($rel, 'FgtsEsocialDivergenceAnalyzer.php')) {
                    continue;
                }
                $hits[] = $rel;
            }
        }

        $this->assertSame([], $hits, 'Código não deve afirmar débito FGTS Digital: '.implode(', ', $hits));
    }

    public function test_runtime_nao_contem_double_esocial(): void
    {
        $appRoot = dirname(__DIR__, 2).'/app';
        $hits = [];

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($appRoot));
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            if ($content !== false && str_contains($content, 'FakeEsocialEventClient')) {
                $hits[] = substr($file->getPathname(), strlen($appRoot) + 1);
            }
        }

        $this->assertFileDoesNotExist($appRoot.'/Services/Esocial/FakeEsocialEventClient.php');
        $this->assertSame([], $hits, 'Runtime não pode importar nem resolver o double eSocial: '.implode(', ', $hits));
    }

    /**
     * @return array<string, string> rel path => content
     */
    private function collectPhpFiles(string $appRoot): array
    {
        $files = [];
        foreach (self::MODULE_PATHS as $path) {
            $full = $appRoot.'/'.$path;
            if (is_file($full) && str_ends_with($full, '.php')) {
                $content = file_get_contents($full);
                if ($content !== false) {
                    $files[$path] = $content;
                }

                continue;
            }
            if (! is_dir($full)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($full));
            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }
                $content = file_get_contents($file->getPathname());
                if ($content === false) {
                    continue;
                }
                $rel = substr($file->getPathname(), strlen($appRoot) + 1);
                $files[$rel] = $content;
            }
        }

        return $files;
    }

    private function isOnlyNegatedMention(string $content, string $pattern): bool
    {
        if (! preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            return true;
        }

        foreach ($matches[0] as [$match, $offset]) {
            $start = max(0, $offset - 80);
            $snippet = strtolower(substr($content, $start, 160));
            $allowed = false;
            foreach (self::ALLOWED_NEGATION_SNIPPETS as $neg) {
                if (str_contains($snippet, strtolower($neg))) {
                    $allowed = true;
                    break;
                }
            }
            // Comentários PHPDoc / // com "proib" / "must not" / "não"
            if (! $allowed) {
                $lineStart = strrpos(substr($content, 0, $offset), "\n");
                $lineStart = $lineStart === false ? 0 : $lineStart + 1;
                $line = substr($content, $lineStart, 200);
                if (preg_match('/^\s*(\/\/|\*|\/\*)/', $line)
                    && preg_match('/(n[aã]o|pro[ií]b|must\s*not|sem\s+|never|false)/i', $line)
                ) {
                    $allowed = true;
                }
            }
            if (! $allowed) {
                return false;
            }
        }

        return true;
    }
}
