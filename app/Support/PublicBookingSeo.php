<?php

namespace App\Support;

final class PublicBookingSeo
{
    /**
     * @param  array<string, mixed>  $unit
     * @return array{title: string, description: string, canonical_url: ?string, robots: string, image: ?string, structured_data: array<string, mixed>}
     */
    public static function forUnit(array $unit): array
    {
        $title = $unit['seo']['title'] ?? null;
        $title = is_string($title) && trim($title) !== '' ? trim($title) : 'Agendar · '.$unit['name'];
        $description = $unit['seo']['description'] ?? null;
        if (! is_string($description) || trim($description) === '') {
            $description = $unit['description'] ?? null;
        }
        $description = is_string($description) && trim($description) !== '' ? trim($description) : 'Agende seu horário em '.$unit['name'].'.';
        $url = self::httpUrl($unit['canonical_url'] ?? null);
        $image = ($unit['sections']['hero'] ?? true) !== false ? (self::httpUrl($unit['cover_image_url'] ?? null) ?? self::httpUrl($unit['logo_image_url'] ?? null)) : null;
        $contacts = ($unit['sections']['contact'] ?? true) !== false ? ($unit['contacts'] ?? []) : [];
        $schema = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'BeautySalon',
            'name' => $unit['name'],
            'description' => $description,
            'url' => $url,
            'image' => $image,
            'telephone' => $contacts['phone'] ?? $contacts['whatsapp'] ?? null,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
        $address = ($unit['sections']['contact'] ?? true) !== false ? ($unit['address'] ?? []) : [];
        $postalAddress = array_filter([
            'streetAddress' => implode(', ', array_filter([$address['street'] ?? null, $address['number'] ?? null])),
            'addressLocality' => $address['city'] ?? null,
            'addressRegion' => $address['state'] ?? null,
            'postalCode' => $address['postal_code'] ?? null,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
        if ($postalAddress !== []) {
            $schema['address'] = ['@type' => 'PostalAddress', ...$postalAddress];
        }
        $socials = array_values(array_unique(array_filter(array_map(self::httpUrl(...), [
            $contacts['instagram_url'] ?? null, $contacts['facebook_url'] ?? null, $contacts['website_url'] ?? null,
        ]))));
        if ($socials !== []) {
            $schema['sameAs'] = $socials;
        }
        $weekdays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $hours = [];
        foreach ((($unit['sections']['hours'] ?? true) !== false ? ($unit['public_hours'] ?? []) : []) as $day => $period) {
            if (! is_array($period) || ($period['enabled'] ?? true) === false || ! isset($weekdays[$day])) {
                continue;
            }
            $opens = $period['starts_at'] ?? $period['start'] ?? '';
            $closes = $period['ends_at'] ?? $period['end'] ?? '';
            if (! is_string($opens) || ! is_string($closes) || ! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $opens) || ! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $closes) || $opens >= $closes) {
                continue;
            }
            $hours[] = ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'https://schema.org/'.$weekdays[$day], 'opens' => $opens, 'closes' => $closes];
        }
        if ($hours !== []) {
            $schema['openingHoursSpecification'] = $hours;
        }

        return ['title' => $title, 'description' => $description, 'canonical_url' => $url, 'robots' => ($unit['is_preview'] ?? false) ? 'noindex,nofollow,noarchive' : 'index,follow', 'image' => $image, 'structured_data' => $schema];
    }

    private static function httpUrl(mixed $value): ?string
    {
        return is_string($value) && filter_var($value, FILTER_VALIDATE_URL) && in_array(strtolower(parse_url($value, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true) ? $value : null;
    }
}
