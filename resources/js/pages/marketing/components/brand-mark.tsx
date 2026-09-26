import AppLogoIcon from '@/components/app-logo-icon';
import type { Branding } from '@/types/ui';

interface BrandMarkProps {
    branding: Branding;
    light?: boolean;
    compact?: boolean;
}

export function BrandMark({
    branding,
    light = false,
    compact = false,
}: BrandMarkProps) {
    const brandName = branding?.name || 'Caldas Gestão';

    return (
        <span className="flex items-center gap-3" aria-label={brandName}>
            {branding.logoUrl ? (
                <img
                    className="h-9 max-w-40 object-contain object-left"
                    src={branding.logoUrl}
                    alt=""
                />
            ) : (
                <AppLogoIcon className="size-9 text-[#C8FF3D]" aria-label="" />
            )}
            {!compact && (
                <span
                    className={`text-[15px] font-bold tracking-[-0.03em] ${light ? 'text-[#F2EFE7]' : 'text-[#0A0C0B]'}`}
                >
                    {brandName}
                </span>
            )}
        </span>
    );
}

export default BrandMark;
