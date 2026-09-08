<?php

namespace App\Console\Commands;

use App\Models\Service;
use App\Support\Images\UploadedImageOptimizer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

#[Signature('app:backfill-service-images
    {--source-disk=public : Disk containing the legacy files}
    {--report= : Write a JSON report to this path}
    {--dry-run : Report without changing storage or database}')]
#[Description('Move service images to the configured media disk as optimized WebP files')]
final class BackfillServiceImages extends Command
{
    public function handle(UploadedImageOptimizer $optimizer): int
    {
        $sourceDisk = Storage::disk((string) $this->option('source-disk'));
        $mediaDiskName = (string) config('filesystems.media_disk');
        $mediaDisk = Storage::disk($mediaDiskName);
        $summary = ['migrated' => 0, 'skipped' => 0, 'failed' => 0];
        $items = [];

        Service::query()->whereNotNull('image_path')->each(function (Service $service) use ($sourceDisk, $mediaDisk, $mediaDiskName, $optimizer, &$summary, &$items): void {
            $path = (string) $service->image_path;
            if (! $sourceDisk->exists($path)) {
                $summary['skipped']++;
                $items[] = ['service_id' => $service->id, 'name' => $service->name, 'status' => 'skipped', 'reason' => 'missing_source'];
                $this->warn("Missing source image: {$service->name}");

                return;
            }

            if ($this->option('dry-run')) {
                $summary['migrated']++;
                $items[] = ['service_id' => $service->id, 'name' => $service->name, 'status' => 'pending', 'path' => $path];

                return;
            }

            $temporaryPath = tempnam(sys_get_temp_dir(), 'service-image-');
            if ($temporaryPath === false || file_put_contents($temporaryPath, $sourceDisk->get($path)) === false) {
                $summary['failed']++;
                $items[] = ['service_id' => $service->id, 'name' => $service->name, 'status' => 'failed', 'reason' => 'temporary_file'];

                return;
            }

            try {
                $file = new UploadedFile($temporaryPath, basename($path), $sourceDisk->mimeType($path) ?: 'image/*', null, true);
                $newPath = preg_replace('/\.[^.]+$/', '.webp', $path) ?: $path.'.webp';
                if (! $optimizer->storeWebp($file, $mediaDisk, $newPath)) {
                    throw new \RuntimeException('Could not store optimized image.');
                }
                $service->forceFill(['image_path' => $newPath])->save();
                if ($mediaDiskName !== (string) $this->option('source-disk')) {
                    $sourceDisk->delete($path);
                }
                $summary['migrated']++;
                $items[] = ['service_id' => $service->id, 'name' => $service->name, 'status' => 'migrated', 'path' => $newPath];
            } catch (\Throwable $exception) {
                $summary['failed']++;
                $items[] = ['service_id' => $service->id, 'name' => $service->name, 'status' => 'failed', 'reason' => $exception->getMessage()];
            } finally {
                @unlink($temporaryPath);
            }
        });

        $this->components->info(sprintf('Images: %d migrated, %d skipped, %d failed.', ...array_values($summary)));

        if (is_string($reportPath = $this->option('report')) && $reportPath !== '') {
            file_put_contents($reportPath, json_encode(['summary' => $summary, 'items' => $items], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $this->components->info("Report written to {$reportPath}.");
        }

        return $summary['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
