<?php

namespace App\Support\Images;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use RuntimeException;

final class UploadedImageOptimizer
{
    private const int MAX_WIDTH = 1600;

    private const int WEBP_QUALITY = 82;

    private const int THUMBNAIL_SIZE = 400;

    private const int THUMBNAIL_QUALITY = 78;

    public function encodeWebp(UploadedFile $file): string
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if ($source === false) {
            throw new RuntimeException('Não foi possível processar a imagem enviada.');
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $targetWidth = min($sourceWidth, self::MAX_WIDTH);
        $targetHeight = (int) round($sourceHeight * ($targetWidth / $sourceWidth));
        $targetHeight = max(1, $targetHeight);
        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        if ($transparent === false) {
            imagedestroy($source);
            imagedestroy($canvas);
            throw new RuntimeException('Não foi possível preparar a transparência da imagem.');
        }
        imagefill($canvas, 0, 0, $transparent);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);

        $stream = fopen('php://temp', 'w+b');
        if ($stream === false || ! imagewebp($canvas, $stream, self::WEBP_QUALITY)) {
            imagedestroy($source);
            imagedestroy($canvas);
            throw new RuntimeException('Não foi possível converter a imagem para WebP.');
        }

        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);
        imagedestroy($source);
        imagedestroy($canvas);

        if ($contents === false) {
            throw new RuntimeException('Não foi possível ler a imagem convertida.');
        }

        return $contents;
    }

    public function storeWebp(UploadedFile $file, FilesystemAdapter $disk, string $path): bool
    {
        return (bool) $disk->put($path, $this->encodeWebp($file));
    }

    public function storeSquareWebp(UploadedFile $file, FilesystemAdapter $disk, string $path): bool
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if ($source === false) {
            throw new RuntimeException('Não foi possível processar a miniatura.');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $cropSize = min($width, $height);
        $sourceX = (int) floor(($width - $cropSize) / 2);
        $sourceY = (int) floor(($height - $cropSize) / 2);
        $canvas = imagecreatetruecolor(self::THUMBNAIL_SIZE, self::THUMBNAIL_SIZE);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        if ($transparent === false) {
            imagedestroy($source);
            imagedestroy($canvas);
            throw new RuntimeException('Não foi possível preparar a miniatura.');
        }
        imagefill($canvas, 0, 0, $transparent);
        imagecopyresampled($canvas, $source, 0, 0, $sourceX, $sourceY, self::THUMBNAIL_SIZE, self::THUMBNAIL_SIZE, $cropSize, $cropSize);
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false || ! imagewebp($canvas, $stream, self::THUMBNAIL_QUALITY)) {
            imagedestroy($source);
            imagedestroy($canvas);
            throw new RuntimeException('Não foi possível converter a miniatura para WebP.');
        }
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);
        imagedestroy($source);
        imagedestroy($canvas);
        if ($contents === false) {
            throw new RuntimeException('Não foi possível ler a miniatura convertida.');
        }

        return (bool) $disk->put($path, $contents);
    }
}
