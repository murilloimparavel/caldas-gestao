import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { getInitials } from '@/lib/utils';
import type { CalendarOption } from '@/types/calendar';

export function ProfessionalAvatarHeader({
    professional,
    subtitle,
}: {
    professional: CalendarOption;
    subtitle?: string;
}) {
    return (
        <div className="flex items-center gap-2 px-2 py-2">
            <Avatar className="size-8 shrink-0 border border-border">
                {professional.avatar_url ? (
                    <AvatarImage
                        src={professional.avatar_url}
                        alt={professional.name}
                    />
                ) : null}
                <AvatarFallback className="bg-primary/10 text-xs font-semibold text-primary">
                    {getInitials(professional.name)}
                </AvatarFallback>
            </Avatar>
            <div className="min-w-0 flex-1">
                <p className="truncate text-xs font-semibold text-foreground">
                    {professional.name}
                </p>
                {subtitle ? (
                    <p className="truncate text-2xs text-muted-foreground">
                        {subtitle}
                    </p>
                ) : null}
            </div>
        </div>
    );
}
