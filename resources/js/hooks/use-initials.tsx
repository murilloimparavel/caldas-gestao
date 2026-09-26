import { getInitials } from '@/lib/utils';

export type GetInitialsFn = (fullName: string) => string;

export function useInitials(): GetInitialsFn {
    return (fullName: string): string => getInitials(fullName);
}
