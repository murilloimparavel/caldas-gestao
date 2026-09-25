<?php

namespace App\Actions\OnlineBooking;

use Illuminate\Support\Str;

final class OnlineBookingAppearance
{
    /**
     * @return array{brand_name: string, headline: string, subheadline: string, primary_color: string, background_color: string, cta_label: string}
     */
    public static function defaults(?string $templateKey = null, ?string $brandName = null): array
    {
        if ($templateKey === 'atelier-barber') {
            return [
                'brand_name' => self::text($brandName, 'Caldas Gestão', 80),
                'headline' => 'Reserve seu horário',
                'subheadline' => 'Uma experiência feita para você.',
                'primary_color' => '#D4AF37',
                'background_color' => '#0D0D0C',
                'cta_label' => 'Continuar',
            ];
        }

        return [
            'brand_name' => self::text($brandName, 'Caldas Gestão', 80),
            'headline' => 'Agende seu atendimento',
            'subheadline' => 'Escolha o serviço, profissional e horário ideal para você.',
            'primary_color' => '#2563EB',
            'background_color' => '#F8FAFC',
            'cta_label' => 'Continuar',
        ];
    }

    /**
     * @param  array<string, mixed>|null  $appearance
     * @param  array<string, mixed>|null  $base
     * @return array{brand_name: string, headline: string, subheadline: string, primary_color: string, background_color: string, cta_label: string}
     */
    public static function normalize(?array $appearance, ?string $templateKey = null, ?array $base = null, ?string $brandName = null): array
    {
        $defaults = self::defaults($templateKey, $brandName);
        $merged = array_replace($defaults, is_array($base) ? $base : [], is_array($appearance) ? $appearance : []);

        return [
            'brand_name' => self::text($merged['brand_name'] ?? null, $defaults['brand_name'], 80),
            'headline' => self::text($merged['headline'] ?? null, $defaults['headline'], 120),
            'subheadline' => self::text($merged['subheadline'] ?? null, $defaults['subheadline'], 240),
            'primary_color' => self::color($merged['primary_color'] ?? null, $defaults['primary_color']),
            'background_color' => self::color($merged['background_color'] ?? null, $defaults['background_color']),
            'cta_label' => self::text($merged['cta_label'] ?? null, $defaults['cta_label'], 40),
        ];
    }

    private static function text(mixed $value, string $fallback, int $maxLength): string
    {
        if (! is_string($value)) {
            return $fallback;
        }

        $value = Str::of($value)->trim()->limit($maxLength, '')->toString();

        return $value === '' ? $fallback : $value;
    }

    private static function color(mixed $value, string $fallback): string
    {
        return is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1
            ? strtoupper($value)
            : $fallback;
    }
}
