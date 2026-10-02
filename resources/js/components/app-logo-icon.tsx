import type { SVGAttributes } from 'react';

export default function AppLogoIcon({
    'aria-label': ariaLabel = 'Logotipo da aplicação',
    ...props
}: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 40 40"
            xmlns="http://www.w3.org/2000/svg"
            role="img"
            aria-label={ariaLabel}
        >
            <rect width="40" height="40" rx="12" fill="currentColor" />
            <path
                d="M26.7 12.3A10.2 10.2 0 1 0 29.4 27l-4.1-3a5.2 5.2 0 1 1-1.7-7.6l3.1-4.1Z"
                fill="white"
            />
            <path d="M21 19h9v3.8h-4.2V29H21V19Z" fill="white" />
        </svg>
    );
}
