import { CheckCircle2, XCircle } from 'lucide-react';
import type { Globe2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { BookingItem } from '../types';
import { bookingTokens, bookingUi } from './design-tokens';

export function SelectionCard({
    item,
    group,
}: {
    item: BookingItem;
    group: string;
}) {
    const active = item.status === 'active';
    const id = `${group}-${item.id}`;

    return (
        <label
            htmlFor={id}
            className={`flex min-h-14 cursor-pointer items-center gap-3 ${bookingTokens.radius.control} border ${bookingTokens.space.control} ${bookingTokens.state.transition} ${active ? `${bookingTokens.color.borderStrong} ${bookingTokens.color.surface} ${bookingTokens.state.hoverAccent} ${bookingTokens.state.checkedSelection}` : `cursor-not-allowed border-slate-200/60 ${bookingTokens.color.disabledSurface} opacity-65`}`}
        >
            <input
                id={id}
                name={`${group}_ids[]`}
                type="checkbox"
                value={item.id}
                defaultChecked={active && item.online_booking_enabled}
                disabled={!active}
                className="size-5 rounded border-input accent-primary focus-visible:ring-2 focus-visible:ring-ring"
            />
            <span className="min-w-0 flex-1 truncate text-sm font-medium text-foreground">
                {item.name}
            </span>
            <Badge variant="outline" className="shrink-0 text-2xs">
                {active ? 'Ativo' : 'Inativo'}
            </Badge>
        </label>
    );
}

export function ReadinessRow({
    label,
    ready,
}: {
    label: string;
    ready: boolean;
}) {
    return (
        <li className="flex items-start gap-3 text-sm">
            {ready ? (
                <CheckCircle2
                    aria-hidden="true"
                    className="mt-0.5 size-4 shrink-0 text-emerald-600 dark:text-emerald-400"
                />
            ) : (
                <XCircle
                    aria-hidden="true"
                    className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                />
            )}
            <span
                className={ready ? 'text-foreground' : 'text-muted-foreground'}
            >
                {label}
            </span>
        </li>
    );
}

export function Field({
    label,
    name,
    defaultValue,
    placeholder,
    type = 'text',
    readOnly = false,
}: {
    label: string;
    name: string;
    defaultValue?: string | null;
    placeholder?: string;
    type?: string;
    readOnly?: boolean;
}) {
    return (
        <div className={bookingTokens.space.controlGroup}>
            <Label htmlFor={name}>{label}</Label>
            <Input
                id={name}
                name={name}
                type={type}
                defaultValue={defaultValue ?? ''}
                placeholder={placeholder}
                readOnly={readOnly}
                aria-readonly={readOnly}
                className={`${bookingUi.field} ${readOnly ? bookingTokens.color.readOnlySurface : ''}`}
            />
        </div>
    );
}

export function SectionCard({
    icon: Icon,
    title,
    description,
    children,
}: {
    icon: typeof Globe2;
    title: string;
    description: string;
    children: ReactNode;
}) {
    return (
        <Card className={bookingUi.section}>
            <CardHeader className={bookingUi.sectionHeader}>
                <CardTitle className="flex items-center gap-2 text-base">
                    <span className={bookingUi.icon}>
                        <Icon aria-hidden="true" className="size-4" />
                    </span>
                    {title}
                </CardTitle>
                <CardDescription className={bookingTokens.color.subtleText}>
                    {description}
                </CardDescription>
            </CardHeader>
            <CardContent className={bookingUi.sectionContent}>
                {children}
            </CardContent>
        </Card>
    );
}
