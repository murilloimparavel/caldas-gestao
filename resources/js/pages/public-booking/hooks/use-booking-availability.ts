import { useHttp } from '@inertiajs/react';
import { useEffect } from 'react';
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
    const request = useHttp<
        BookingAvailabilityQuery,
        BookingAvailabilityResponse
    >({
        service_id: '',
        service_ids: [],
        professional_id: '',
        date: '',
    });

    useEffect(() => {
        if (!serviceId || !professionalId || !date) {
            return;
        }

        const selectedServiceIds = serviceIds.length ? serviceIds : [serviceId];
        const query: BookingAvailabilityQuery = {
            service_id: serviceId,
            service_ids: selectedServiceIds,
            professional_id: professionalId,
            date,
        };

        request.setData(query);
        void request.get(
            availability.url(args, {
                query,
            }),
            { onError },
        ); // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [date, professionalId, serviceId, serviceIds.join('|')]);

    return request;
}
