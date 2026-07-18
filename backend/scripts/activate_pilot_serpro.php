<?php

/**
 * Ativa o SERPRO Trial do contador em uma única execução idempotente.
 *
 * Se o A1 já estiver no vault, nenhum secret é exigido. No primeiro uso:
 * SERPRO_PILOT_PFX_PATH + SERPRO_PILOT_PFX_PASSWORD devem vir do ambiente.
 */

declare(strict_types=1);

use App\Enums\AuthorCertificateMode;
use App\Enums\AuthorIdentityType;
use App\Enums\SerproEnvironment;
use App\Models\Client;
use App\Models\Office;
use App\Models\User;
use App\Services\Integra\ClientProcuracaoSyncService;
use App\Services\Integra\OfficeSerproAuthorizationService;
use App\Support\FiscalDataModel\PrivilegedOfficeContext;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

function out(string $msg): void
{
    fwrite(STDOUT, $msg.PHP_EOL);
}

PrivilegedOfficeContext::enter('script:activate_pilot_serpro');
register_shutdown_function(static function (): void {
    PrivilegedOfficeContext::leave();
});

try {
    if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
        throw new RuntimeException('Execute o script como www-data; root não pode criar objetos no vault.');
    }

    $office = Office::query()->where('slug', 'contador')->firstOrFail();
    $client = Client::query()
        ->where('office_id', $office->id)
        ->where('root_cnpj', '30288513')
        ->where('is_active', true)
        ->firstOrFail();
    $actor = User::query()->where('email', 'gustavo@example.com')->firstOrFail();
    $env = SerproEnvironment::Trial;

    out('Ativando SERPRO Trial para contador / AUTO CENTER…');

    $authSvc = app(OfficeSerproAuthorizationService::class);
    $procSvc = app(ClientProcuracaoSyncService::class);

    $auth = $authSvc->configureAuthor(
        $office,
        $env,
        AuthorIdentityType::Cnpj,
        '48123272000105',
        'G A SILVA ASSESSORIA CONTABIL',
        AuthorCertificateMode::ManagedA1,
        $actor->id,
    );

    if ($auth->author_pfx_vault_object_id === null) {
        $pfxPath = getenv('SERPRO_PILOT_PFX_PATH');
        $pfxPassword = getenv('SERPRO_PILOT_PFX_PASSWORD');
        if (! is_string($pfxPath) || $pfxPath === '' || ! is_readable($pfxPath)) {
            throw new RuntimeException('A1 ausente no vault e SERPRO_PILOT_PFX_PATH não é legível.');
        }
        if (! is_string($pfxPassword) || $pfxPassword === '') {
            throw new RuntimeException('A1 ausente no vault e SERPRO_PILOT_PFX_PASSWORD não foi informado.');
        }

        $pfxBinary = file_get_contents($pfxPath);
        if ($pfxBinary === false || $pfxBinary === '') {
            throw new RuntimeException('PFX do autor está vazio ou não pôde ser lido.');
        }

        $auth = $authSvc->storeManagedAuthorA1(
            $office,
            $env,
            $pfxBinary,
            $pfxPassword,
            true,
            $actor->id,
        );
        unset($pfxBinary, $pfxPassword);
    }

    if ($auth->termo_vault_object_id === null) {
        $authSvc->generateTermoDraft($office, $env, null, $actor->id);
        $auth = $authSvc->dispatchManagedA1Sign($office, $env, true, $actor->id);

        $deadline = microtime(true) + 60;
        while ($auth->termo_vault_object_id === null && microtime(true) < $deadline) {
            usleep(500_000);
            $auth = $auth->fresh();
        }
        if ($auth->termo_vault_object_id === null) {
            throw new RuntimeException('A fila não concluiu a assinatura do Termo em até 60 segundos.');
        }
    }

    $authSvc->refreshProcuradorToken($office, $env, $actor->id);
    $auth = $auth->fresh();
    if ($auth->procurador_token_vault_object_id === null
        || $auth->procurador_token_expires_at === null
        || $auth->procurador_token_expires_at->isPast()
    ) {
        throw new RuntimeException('Token do procurador não ficou ativo após a autenticação.');
    }

    $result = $procSvc->syncOfficial($office, $client, $env, $actor->id, true);
    $snapshot = $result['snapshot'];

    out('Ativação concluída.');
    out('authorization_status='.$auth->status->value);
    out('proxy_status='.$snapshot->status->value);
    out('proxy_power_count='.count($result['powers']));
    exit(0);
} catch (Throwable $e) {
    out('Falha na ativação: '.$e->getMessage());
    exit(1);
}
