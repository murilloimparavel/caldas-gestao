<?php

namespace App\Actions\TenantDomains;

use App\Contracts\DnsResolver;
use App\Enums\TenantDomainStatus;
use App\Models\TenantDomain;
use App\Support\EntitlementService;
use App\Support\HostnameNormalizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;

class VerifyTenantDomain
{
    public function __construct(
        private readonly DnsResolver $dns,
        private readonly EntitlementService $entitlements,
    ) {}

    public function handle(TenantDomain $domain): TenantDomain
    {
        $hostname = HostnameNormalizer::normalize($domain->hostname);

        if (! $this->entitlements->can($domain->tenant_id, 'domains.custom')) {
            throw new AuthorizationException('O tenant não possui acesso a domínios personalizados.');
        }

        $cnameRecords = $this->dns->records($hostname, DNS_CNAME);
        $txtRecords = $this->dns->records('_caldas-gestao-verification.'.$hostname, DNS_TXT);
        $expectedCname = strtolower(rtrim($domain->expected_cname, '.'));
        $cnameMatches = collect($cnameRecords)->contains(static function (array $record) use ($expectedCname): bool {
            return strtolower(rtrim((string) ($record['target'] ?? ''), '.')) === $expectedCname;
        });
        $txtMatches = collect($txtRecords)->contains(static function (array $record) use ($domain): bool {
            $text = $record['txt'] ?? $record['entries'] ?? '';
            $values = is_array($text) ? $text : [$text];

            return in_array($domain->verification_token, array_map('strval', $values), true);
        });
        $checkedAt = Carbon::now();

        $domain->forceFill([
            'status' => $cnameMatches && $txtMatches ? TenantDomainStatus::Verified : TenantDomainStatus::Pending,
            'verified_at' => $cnameMatches && $txtMatches ? $checkedAt : null,
            'last_dns_check_at' => $checkedAt,
            'last_dns_error' => $cnameMatches && $txtMatches ? null : $this->diagnostic($cnameMatches, $txtMatches),
        ])->save();

        return $domain->refresh();
    }

    private function diagnostic(bool $cnameMatches, bool $txtMatches): string
    {
        return match (true) {
            ! $cnameMatches && ! $txtMatches => 'CNAME e TXT não encontrados ou incorretos.',
            ! $cnameMatches => 'CNAME ausente ou incorreto.',
            default => 'TXT de verificação ausente ou incorreto.',
        };
    }
}
