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

        Service::query()->whereNotNull('image_path')->each(function (Service $service) use ($sourceDisk, $mediaDisk, $mediaDiskName, $optimizer, &$summary): void {
            $path = (string) $service->image_path;
            if (! $sourceDisk->exists($path)) {
                $summary['skipped']++;
                $this->warn("Missing source image: {$service->name}");

                return;
            }

            if ($this->option('dry-run')) {
                $summary['migrated']++;

                return;
            }

            $temporaryPath = tempnam(sys_get_temp_dir(), 'service-image-');
            if ($temporaryPath === false || file_put_contents($temporaryPath, $sourceDisk->get($path)) === false) {
                $summary['failed']++;

                return;
            }

            try {
                $file = new UploadedFile($temporaryPath, basename($path), $sourceDisk->mimeType($path) ?: 'image/*', null, true);
                $newPath = preg_replace('/\.[^.]+$/', '.webp', $path) ?: $path.'.webp';
                $optimizer->storeWebp($file, $mediaDisk, $newPath);
                $service->forceFill(['image_path' => $newPath])->save();
                if ($mediaDiskName !== (string) $this->option('source-disk')) {
                    $sourceDisk->delete($path);
                }
                $summary['migrated']++;
            } catch (\Throwable) {
                $summary['failed']++;
            } finally {
                @unlink($temporaryPath);
            }
        });

        $this->components->info(sprintf('Images: %d migrated, %d skipped, %d failed.', ...array_values($summary)));

        return $summary['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
