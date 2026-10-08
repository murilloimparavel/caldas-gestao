import { useEffect, useRef, useState } from 'react';
import type { Dispatch, SetStateAction } from 'react';

type ParseStoredValue<T> = (value: string) => T | null;
type SerializeStoredValue<T> = (value: T) => string;

export function useHydratedLocalStorageState<T>(
    storageKey: string,
    defaultValue: T,
    parseStoredValue: ParseStoredValue<T>,
    serializeStoredValue: SerializeStoredValue<T> = JSON.stringify,
): [T, Dispatch<SetStateAction<T>>] {
    const [value, setValue] = useState(defaultValue);
    const hydratedStorageKeyRef = useRef<string | null>(null);
    const skipPersistRef = useRef(false);

    useEffect(() => {
        skipPersistRef.current = true;

        let storedValue: T | null = null;

        try {
            const persistedValue = window.localStorage.getItem(storageKey);

            if (persistedValue !== null) {
                storedValue = parseStoredValue(persistedValue);
            }
        } catch {
            // Storage may be unavailable.
        }

        // Hydrate browser-only preference after the deterministic SSR render.
        // eslint-disable-next-line react-hooks/set-state-in-effect
        setValue(storedValue ?? defaultValue);
        hydratedStorageKeyRef.current = storageKey;
        // The initial fallback is read once for each storage key; later prop changes
        // must not replace the user's current preference.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [storageKey]);

    useEffect(() => {
        if (skipPersistRef.current) {
            skipPersistRef.current = false;

            return;
        }

        if (hydratedStorageKeyRef.current !== storageKey) {
            return;
        }

        try {
            window.localStorage.setItem(
                storageKey,
                serializeStoredValue(value),
            );
        } catch {
            // Storage may be unavailable.
        }
    }, [serializeStoredValue, storageKey, value]);

    return [value, setValue];
}
