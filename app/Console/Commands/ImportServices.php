<?php

namespace App\Console\Commands;

use App\Actions\Services\CreateService;
use App\Models\Category;
use App\Models\Professional;
use App\Models\Service;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

#[Signature('app:import-services
    {file : JSON file containing a services array}
    {--tenant-email= : Owner email used to resolve the destination tenant}
    {--unit-id= : Explicit destination unit UUID}
    {--dry-run : Validate and report without creating services}
    {--report= : Optional JSON report output path}')]
#[Description('Import services from a sanitized JSON export into a tenant')]
final class ImportServices extends Command
{
    /** @var array<string, int> */
    private array $summary = ['created' => 0, 'skipped' => 0, 'pending' => 0];

    /** @var array<string, true> */
    private array $seenNames = [];

    /** @var list<array<string, mixed>> */
    private array $report = [];

    public function handle(CreateService $createService): int
    {
        $payload = $this->readPayload((string) $this->argument('file'));
        $email = trim((string) $this->option('tenant-email'));
        if ($email === '') {
            throw new RuntimeException('The --tenant-email option is required.');
        }

        $actor = User::query()->where('email_normalized', Str::lower($email))->first();
        if ($actor === null) {
            throw new RuntimeException('No user found for the supplied tenant email.');
        }

        $context = TenantContext::forUser($actor, null, $this->option('unit-id'));
        if ($context->unit === null) {
            throw new RuntimeException('The destination tenant has no active unit.');
        }

        foreach ($payload['services'] as $index => $source) {
            $this->importOne($source, $actor, $context, $createService, $index + 1);
        }

        $this->components->info(sprintf(
            'Services: %d created, %d skipped, %d pending relation(s).',
            $this->summary['created'],
            $this->summary['skipped'],
            $this->summary['pending'],
        ));

        $reportPath = $this->option('report');
        if (is_string($reportPath) && $reportPath !== '') {
            $written = file_put_contents($reportPath, json_encode([
                'summary' => $this->summary,
                'services' => $this->report,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            if ($written === false) {
                throw new RuntimeException("Unable to write import report: {$reportPath}");
            }
            $this->components->info("Report written to {$reportPath}.");
        }

        return self::SUCCESS;
    }

    /** @return array{services: list<array<string, mixed>>} */
    private function readPayload(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("Import file is not readable: {$path}");
        }

        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded) || ! isset($decoded['services']) || ! is_array($decoded['services'])) {
            throw new RuntimeException('Import file must contain a services array.');
        }

        return ['services' => array_values(array_filter($decoded['services'], 'is_array'))];
    }

    /** @param array<string, mixed> $source */
    private function importOne(array $source, User $actor, TenantContext $context, CreateService $createService, int $position): void
    {
        $name = trim((string) ($source['name'] ?? ''));
        if ($name === '') {
            $this->warn("Row {$position}: skipped because name is missing.");
            $this->summary['skipped']++;
            $this->report[] = ['name' => null, 'status' => 'skipped', 'reason' => 'missing_name'];

            return;
        }

        $normalizedName = Str::lower($name);
        if (isset($this->seenNames[$normalizedName])) {
            $this->line("Skipped duplicate in import: {$name}");
            $this->summary['skipped']++;
            $this->report[] = ['name' => $name, 'status' => 'skipped', 'reason' => 'duplicate_in_import'];

            return;
        }
        $this->seenNames[$normalizedName] = true;

        $existing = Service::query()
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $context->unit->getKey())
            ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
            ->exists();
        if ($existing) {
            $this->line("Skipped existing service: {$name}");
            $this->summary['skipped']++;
            $this->report[] = ['name' => $name, 'status' => 'skipped', 'reason' => 'already_exists'];

            return;
        }

        $data = [
            'name' => $name,
            'description' => $this->nullableString($source['description'] ?? null),
            'duration_minutes' => $this->durationMinutes($source['duration_minutes'] ?? $source['duration'] ?? null),
            'price_cents' => array_key_exists('price_cents', $source)
                ? $this->integerCents($source['price_cents'])
                : $this->priceCents($source['price'] ?? null),
            'category_id' => $this->resolveCategoryId($source['category_name'] ?? null, $context),
            'status' => ($source['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active',
            'online_booking_enabled' => (bool) ($source['online_booking_enabled'] ?? $source['show_on_site'] ?? false),
            'professional_ids' => $this->resolveProfessionalIds($source['professional_names'] ?? [], $context),
        ];

        $image = $source['image_path'] ?? null;
        if (is_string($image) && is_file($image) && is_readable($image)) {
            $data['image'] = new UploadedFile($image, basename($image), mime_content_type($image) ?: null, null, true);
        }

        if ($this->option('dry-run')) {
            $this->line("Would create: {$name}");
            $this->summary['created']++;
            $this->report[] = ['name' => $name, 'status' => 'would_create', 'pending_relations' => $this->summary['pending']];

            return;
        }

        $createService->handle($actor, $context, $data);
        $this->line("Created: {$name}");
        $this->summary['created']++;
        $this->report[] = ['name' => $name, 'status' => 'created'];
    }

    private function durationMinutes(mixed $value): int
    {
        $minutes = (int) $value;
        if ($minutes < 1 || $minutes > 1440) {
            throw new RuntimeException('Each service duration must be between 1 and 1440 minutes.');
        }

        return $minutes;
    }

    private function priceCents(mixed $value): int
    {
        if (is_string($value)) {
            $value = str_replace(['R$', ' '], '', $value);
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }
        $cents = (int) round(((float) $value) * 100);
        if ($cents < 0) {
            throw new RuntimeException('Service price cannot be negative.');
        }

        return $cents;
    }

    private function integerCents(mixed $value): int
    {
        $cents = filter_var($value, FILTER_VALIDATE_INT);
        if ($cents === false || $cents < 0) {
            throw new RuntimeException('Service price_cents must be a non-negative integer.');
        }

        return $cents;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function resolveCategoryId(mixed $name, TenantContext $context): ?string
    {
        $name = $this->nullableString($name);
        if ($name === null) {
            return null;
        }

        $category = Category::query()
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $context->unit->getKey())
            ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
            ->first(['id']);
        if ($category === null) {
            $this->summary['pending']++;
            $this->warn("Category relation pending: {$name}");

            return null;
        }

        return (string) $category->getKey();
    }

    /** @param mixed $names @return list<string> */
    private function resolveProfessionalIds(mixed $names, TenantContext $context): array
    {
        if (! is_array($names)) {
            return [];
        }

        $ids = [];
        foreach ($names as $name) {
            $professional = Professional::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $context->unit->getKey())
                ->whereRaw('LOWER(name) = ?', [Str::lower(trim((string) $name))])
                ->first(['id']);
            if ($professional === null) {
                $this->summary['pending']++;
                $this->warn('Professional relation pending: '.trim((string) $name));

                continue;
            }
            $ids[] = (string) $professional->getKey();
        }

        return array_values(array_unique($ids));
    }
}
