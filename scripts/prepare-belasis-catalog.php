<?php

declare(strict_types=1);

if ($argc !== 4) {
    fwrite(STDERR, "Usage: php scripts/prepare-belasis-catalog.php <mixed-catalog.json> <products.json> <output-directory>\n");
    exit(1);
}

[$script, $mixedPath, $productsPath, $outputDirectory] = $argv;

/** @return array<string,mixed> */
function readJson(string $path): array
{
    if (! is_file($path) || ! is_readable($path)) {
        throw new RuntimeException("Input file is not readable: {$path}");
    }

    $payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (! is_array($payload)) {
        throw new RuntimeException("Input file must contain a JSON object: {$path}");
    }

    return $payload;
}

/** @return list<array<string,mixed>> */
function records(array $payload, string $key): array
{
    $records = $payload[$key] ?? null;
    if (! is_array($records)) {
        throw new RuntimeException("Input file must contain a {$key} array.");
    }

    return array_values(array_filter($records, 'is_array'));
}

/** @param list<array<string,mixed>> $records */
function writeJson(string $path, string $entity, array $records): void
{
    $payload = [
        'source' => 'belasis',
        'entity' => $entity,
        'source_count' => count($records),
        $entity => $records,
    ];
    $written = file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    if ($written === false) {
        throw new RuntimeException("Unable to write output file: {$path}");
    }
}

try {
    $mixed = records(readJson($mixedPath), 'services');
    $deduplicatedProducts = [];
    $services = [];

    foreach ($mixed as $record) {
        if (($record['is_service'] ?? null) === true) {
            $services[] = $record;

            continue;
        }
        if (($record['is_service'] ?? null) === false && is_scalar($record['source_id'] ?? null)) {
            $deduplicatedProducts[(string) $record['source_id']] = $record;
        }
    }

    foreach (records(readJson($productsPath), 'products') as $record) {
        if (is_scalar($record['source_id'] ?? null)) {
            $deduplicatedProducts[(string) $record['source_id']] = $record;
        }
    }

    if (! is_dir($outputDirectory) && ! mkdir($outputDirectory, 0775, true) && ! is_dir($outputDirectory)) {
        throw new RuntimeException("Unable to create output directory: {$outputDirectory}");
    }

    $products = array_values($deduplicatedProducts);
    writeJson(rtrim($outputDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'services-only.json', 'services', $services);
    writeJson(rtrim($outputDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'products-only.json', 'products', $products);

    printf("Prepared %d services and %d products.\n", count($services), count($products));
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage()."\n");
    exit(1);
}
