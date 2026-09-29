import { useEffect, useState } from 'react';
import { availability } from '@/routes/public_booking';
import type { BookingAvailabilityResponse } from './use-booking-availability';

type Candidate = { id: string };
type Result = { professionalId: string; date: string; slot: string };
type SearchState = {
    key: string;
    status: 'idle' | 'loading' | 'found' | 'empty' | 'error';
    result: Result | null;
};

const LOOKAHEAD_DAYS = 7;
const MAX_CONCURRENT_REQUESTS = 4;

export const bookingDateInTimezone = (timezone: string): string => {
    const parts = new Intl.DateTimeFormat('en', {
        timeZone: timezone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).formatToParts(new Date());
    const part = (type: Intl.DateTimeFormatPartTypes): string =>
        parts.find((item) => item.type === type)?.value ?? '';

    return `${part('year')}-${part('month')}-${part('day')}`;
};

export const addBookingCalendarDays = (date: string, days: number): string => {
    const [year, month, day] = date.split('-').map(Number);

    return new Date(Date.UTC(year, month - 1, day + days))
        .toISOString()
        .slice(0, 10);
};

export function useFirstAvailableProfessional({
    enabled,
    args,
    serviceIds,
    professionals,
    timezone,
    refreshKey,
    onFound,
}: {
    enabled: boolean;
    args: [string, string];
    serviceIds: string[];
    professionals: Candidate[];
    timezone: string;
    refreshKey: number;
    onFound: (result: Result) => void;
}) {
    const [state, setState] = useState<SearchState>({
        key: '',
        status: 'idle',
        result: null,
    });
    const serviceIdsKey = serviceIds.join('|');
    const professionalIdsKey = professionals.map(({ id }) => id).join('|');
    const argsKey = args.join('|');
    const searchKey = [
        argsKey,
        enabled,
        serviceIdsKey,
        professionalIdsKey,
        timezone,
        refreshKey,
    ].join('||');

    useEffect(() => {
        if (!enabled || serviceIds.length === 0 || professionals.length === 0) {
            return;
        }

        const controller = new AbortController();
        const selectedServiceIds = serviceIdsKey.split('|');
        const candidateIds = professionalIdsKey.split('|');
        const startDate = bookingDateInTimezone(timezone);
        const dates = Array.from({ length: LOOKAHEAD_DAYS }, (_, index) =>
            addBookingCalendarDays(startDate, index),
        );

        const search = async (): Promise<void> => {
            try {
                for (const date of dates) {
                    let earliestForDate: Result | null = null;

                    for (
                        let index = 0;
                        index < candidateIds.length;
                        index += MAX_CONCURRENT_REQUESTS
                    ) {
                        const batch = candidateIds.slice(
                            index,
                            index + MAX_CONCURRENT_REQUESTS,
                        );
                        const matches = await Promise.all(
                            batch.map(async (professionalId) => {
                                const query = {
                                    service_id: selectedServiceIds[0],
                                    service_ids: selectedServiceIds,
                                    professional_id: professionalId,
                                    date,
                                };
                                const response = await fetch(
                                    availability.url(
                                        argsKey.split('|') as [string, string],
                                        { query },
                                    ),
                                    {
                                        headers: {
                                            Accept: 'application/json',
                                            'X-Requested-With':
                                                'XMLHttpRequest',
                                        },
                                        signal: controller.signal,
                                    },
                                );

                                if (!response.ok) {
                                    throw new Error(
                                        `Availability request failed (${response.status})`,
                                    );
                                }

                                const availabilityResponse =
                                    (await response.json()) as BookingAvailabilityResponse;
                                const slot =
                                    availabilityResponse.slots
                                        .map((item) => item.starts_at)
                                        .filter((startsAt) =>
                                            Number.isFinite(
                                                Date.parse(startsAt),
                                            ),
                                        )
                                        .sort(
                                            (first, second) =>
                                                Date.parse(first) -
                                                Date.parse(second),
                                        )[0] ?? null;

                                return slot
                                    ? { professionalId, date, slot }
                                    : null;
                            }),
                        );

                        const earliestMatch = matches
                            .filter((match): match is Result => match !== null)
                            .sort(
                                (first, second) =>
                                    Date.parse(first.slot) -
                                    Date.parse(second.slot),
                            )[0];

                        if (
                            earliestMatch &&
                            (!earliestForDate ||
                                Date.parse(earliestMatch.slot) <
                                    Date.parse(earliestForDate.slot))
                        ) {
                            earliestForDate = earliestMatch;
                        }
                    }

                    if (earliestForDate) {
                        if (!controller.signal.aborted) {
                            setState({
                                key: searchKey,
                                status: 'found',
                                result: earliestForDate,
                            });
                            onFound(earliestForDate);
                        }

                        return;
                    }
                }

                if (!controller.signal.aborted) {
                    setState({
                        key: searchKey,
                        status: 'empty',
                        result: null,
                    });
                }
            } catch {
                if (!controller.signal.aborted) {
                    setState({ key: searchKey, status: 'error', result: null });
                }
            }
        };

        void search();

        return () => {
            controller.abort();
        };
    }, [
        argsKey,
        enabled,
        professionalIdsKey,
        professionals.length,
        serviceIds.length,
        serviceIdsKey,
        searchKey,
        timezone,
        refreshKey,
        onFound,
    ]);

    const isSearchEnabled =
        enabled && serviceIds.length > 0 && professionals.length > 0;
    const status: SearchState['status'] = isSearchEnabled ? 'loading' : 'idle';

    return state.key === searchKey
        ? state
        : { key: searchKey, status, result: null };
}
