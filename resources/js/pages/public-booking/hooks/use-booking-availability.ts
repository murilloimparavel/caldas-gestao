import { useEffect, useState } from 'react';
import { availability } from '@/routes/public_booking';

export type BookingAvailabilityQuery = {
    service_id: string;
    service_ids?: string[];
    professional_id: string;
    date: string;
    from?: string;
    to?: string;
};

export type BookingAvailabilityResponse = {
    date: string;
    timezone: string;
    slots: { starts_at: string; ends_at: string }[];
};

export function useBookingAvailability({
    args,
    serviceId,
    serviceIds,
    professionalId,
    date,
    onError,
}: {
    args: [string, string];
    serviceId: string;
    serviceIds: string[];
    professionalId: string;
    date: string;
    onError: () => void;
}) {
    const [state, setState] = useState<{
        key: string;
        response: BookingAvailabilityResponse | null;
        processing: boolean;
    }>({ key: '', response: null, processing: false });
    const serviceIdsKey = serviceIds.join('|');
    const argsKey = args.join('|');
    const requestKey = [
        argsKey,
        serviceId,
        serviceIdsKey,
        professionalId,
        date,
    ].join('||');
    const enabled = Boolean(serviceId && professionalId && date);

    useEffect(() => {
        if (!enabled) {
            return;
        }

        const controller = new AbortController();
        const selectedServiceIds = serviceIdsKey
            ? serviceIdsKey.split('|')
            : [serviceId];
        const query: BookingAvailabilityQuery = {
            service_id: serviceId,
            service_ids: selectedServiceIds,
            professional_id: professionalId,
            date,
        };

        void fetch(
            availability.url(argsKey.split('|') as [string, string], {
                query,
            }),
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                signal: controller.signal,
            },
        )
            .then(async (result) => {
                if (!result.ok) {
                    throw new Error(
                        `Availability request failed (${result.status})`,
                    );
                }

                return (await result.json()) as BookingAvailabilityResponse;
            })
            .then((availabilityResponse) => {
                if (!controller.signal.aborted) {
                    setState({
                        key: requestKey,
                        response: availabilityResponse,
                        processing: false,
                    });
                }
            })
            .catch(() => {
                if (!controller.signal.aborted) {
                    setState({
                        key: requestKey,
                        response: null,
                        processing: false,
                    });
                    onError();
                }
            });

        return () => {
            controller.abort();
        };
    }, [
        argsKey,
        date,
        enabled,
        onError,
        professionalId,
        requestKey,
        serviceId,
        serviceIdsKey,
    ]);

    const isCurrentRequest = state.key === requestKey;

    return {
        response: enabled && isCurrentRequest ? state.response : null,
        processing: enabled && (!isCurrentRequest || state.processing),
    };
}
