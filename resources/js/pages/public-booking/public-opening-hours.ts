export type PublicOpeningDay = {
    enabled?: boolean;
    starts_at?: string;
    ends_at?: string;
    start?: string;
    end?: string;
    display_periods?: { starts_at: string; ends_at: string }[];
};

export function publicOpeningPeriods(
    day?: PublicOpeningDay,
): { starts_at: string; ends_at: string }[] {
    if (!day || day.enabled === false) {
        return [];
    }

    const periods = Object.prototype.hasOwnProperty.call(day, 'display_periods')
        ? day.display_periods
        : [
              {
                  starts_at: day.starts_at ?? day.start,
                  ends_at: day.ends_at ?? day.end,
              },
          ];
    const clock = /^(?:[01]\d|2[0-3]):[0-5]\d$/;

    return (Array.isArray(periods) ? periods : []).filter(
        (period): period is { starts_at: string; ends_at: string } =>
            period !== null &&
            typeof period === 'object' &&
            typeof period.starts_at === 'string' &&
            typeof period.ends_at === 'string' &&
            clock.test(period.starts_at) &&
            clock.test(period.ends_at) &&
            period.starts_at < period.ends_at,
    );
}

export function publicOpeningLabel(day?: PublicOpeningDay): string {
    if (day?.enabled === false) {
        return 'Fechado';
    }

    return (
        publicOpeningPeriods(day)
            .map((period) => `${period.starts_at} às ${period.ends_at}`)
            .join(' · ') || 'Não informado'
    );
}
