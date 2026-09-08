<?php

namespace App\Support;

use App\Contracts\CustomDomainProvisioner;
use App\Models\TenantDomain;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class CoolifyCustomDomainProvisioner implements CustomDomainProvisioner
{
    public function provision(TenantDomain $domain): void
    {
        $baseUrl = rtrim((string) config('services.coolify.url'), '/');
        $applicationUuid = (string) config('services.coolify.application_uuid');
        $token = (string) config('services.coolify.token');

        if ($baseUrl === '' || $applicationUuid === '' || $token === '') {
            throw new RuntimeException('A integração com Coolify não está configurada.');
        }

        $client = Http::withToken($token)->acceptJson()->timeout(10);
        $current = $client->get($baseUrl.'/api/v1/applications/'.$applicationUuid);

        if ($current->failed()) {
            throw new RuntimeException('Não foi possível consultar a aplicação no Coolify.');
        }

        $domains = $current->json('fqdn', '');
        $domains = is_string($domains)
            ? preg_split('/\s*,\s*|\R+/', $domains, -1, PREG_SPLIT_NO_EMPTY) ?: []
            : (is_array($domains) ? $domains : []);
        $domains = array_map(static fn (mixed $value): string => 'https://'.ltrim(str_replace(['http://', 'https://'], '', (string) $value), '/'), $domains);
        $domains = array_values(array_unique([...$domains, 'https://'.$domain->hostname]));

        $response = $client
            ->acceptJson()
            ->patch($baseUrl.'/api/v1/applications/'.$applicationUuid, [
                'domains' => implode(',', $domains),
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Não foi possível provisionar o domínio no Coolify.');
        }
    }
}
