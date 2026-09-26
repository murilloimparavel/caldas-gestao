import type { InertiaLinkProps } from '@inertiajs/react';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}

function getInitial(name: string): string {
    return Array.from(name)[0] ?? '';
}

export function getInitials(name: string): string {
    const names = name.trim().split(/\s+/u).filter(Boolean);

    if (names.length === 0) {
        return '';
    }

    if (names.length === 1) {
        return getInitial(names[0]).toUpperCase();
    }

    const firstInitial = getInitial(names[0]);
    const lastInitial = getInitial(names[names.length - 1]);

    return `${firstInitial}${lastInitial}`.toUpperCase();
}
