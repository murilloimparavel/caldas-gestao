import { useSyncExternalStore } from 'react';

const MOBILE_BREAKPOINT = 640;

const mql =
    typeof window === 'undefined'
        ? undefined
        : window.matchMedia(`(max-width: ${MOBILE_BREAKPOINT - 1}px)`);

const tabletMql =
    typeof window === 'undefined'
        ? undefined
        : window.matchMedia(
              `(min-width: ${MOBILE_BREAKPOINT}px) and (max-width: 1023px)`,
          );

function mediaQueryListener(callback: (event: MediaQueryListEvent) => void) {
    if (!mql) {
        return () => {};
    }

    mql.addEventListener('change', callback);

    return () => {
        mql.removeEventListener('change', callback);
    };
}

function isSmallerThanBreakpoint(): boolean {
    return mql?.matches ?? false;
}

function isTablet(): boolean {
    return tabletMql?.matches ?? false;
}

function getServerSnapshot(): boolean {
    return false;
}

export function useIsMobile(): boolean {
    return useSyncExternalStore(
        mediaQueryListener,
        isSmallerThanBreakpoint,
        getServerSnapshot,
    );
}

export function useIsTablet(): boolean {
    return useSyncExternalStore(
        (callback) => {
            if (!tabletMql) {
                return () => {};
            }

            tabletMql.addEventListener('change', callback);

            return () => tabletMql.removeEventListener('change', callback);
        },
        isTablet,
        getServerSnapshot,
    );
}
