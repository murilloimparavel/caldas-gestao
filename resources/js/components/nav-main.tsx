import { Link } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { SidebarNavGroup } from '@/types';

export function NavMain({ groups = [] }: { groups: SidebarNavGroup[] }) {
    const { isCurrentUrl } = useCurrentUrl();
    const { state } = useSidebar();
    const activeGroupLabels = groups
        .filter((group) =>
            group.items.some((item) => item.href && isCurrentUrl(item.href)),
        )
        .map((group) => group.label);
    const activeGroupKey = activeGroupLabels.join('|');
    const [openGroups, setOpenGroups] = useState<Record<string, boolean>>(() =>
        Object.fromEntries(
            groups.map((group) => [
                group.label,
                activeGroupLabels.includes(group.label),
            ]),
        ),
    );
    const [lastActiveGroupKey, setLastActiveGroupKey] =
        useState(activeGroupKey);
    const routeChanged = lastActiveGroupKey !== activeGroupKey;

    return (
        <>
            {groups.map((group) => (
                <Collapsible
                    key={group.label}
                    asChild
                    open={
                        state === 'collapsed' ||
                        (routeChanged
                            ? activeGroupLabels.includes(group.label)
                            : openGroups[group.label])
                    }
                    onOpenChange={(open) => {
                        setLastActiveGroupKey(activeGroupKey);
                        setOpenGroups((current) => ({
                            ...activeGroupLabels.reduce(
                                (next, label) => ({ ...next, [label]: true }),
                                current,
                            ),
                            [group.label]: open,
                        }));
                    }}
                >
                    <SidebarGroup className="px-2 py-1">
                        <CollapsibleTrigger asChild>
                            <SidebarGroupLabel
                                asChild
                                className="group/section-label h-11 cursor-pointer justify-between px-3 text-[11px] font-semibold tracking-[0.14em] text-sidebar-foreground/60 uppercase hover:bg-sidebar-accent/70 hover:text-sidebar-accent-foreground focus-visible:ring-2"
                            >
                                <button
                                    type="button"
                                    aria-label={`${group.label} — expandir ou recolher seção`}
                                >
                                    <span>{group.label}</span>
                                    <ChevronDown className="size-4 shrink-0 transition-transform duration-200 group-data-[state=open]/section-label:rotate-180" />
                                </button>
                            </SidebarGroupLabel>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <SidebarGroupContent>
                                <SidebarMenu>
                                    {group.items.map((item) => (
                                        <SidebarMenuItem key={item.title}>
                                            {item.disabled ? (
                                                <SidebarMenuButton
                                                    aria-disabled="true"
                                                    aria-label={`${item.title} — Em breve`}
                                                    tabIndex={-1}
                                                    className="cursor-not-allowed text-sidebar-foreground/55 hover:bg-transparent hover:text-sidebar-foreground/55"
                                                    tooltip={{
                                                        children: `${item.title} — Em breve`,
                                                    }}
                                                >
                                                    {item.icon && <item.icon />}
                                                    <span className="min-w-0 flex-1 truncate group-data-[collapsible=icon]:sr-only">
                                                        {item.title}
                                                    </span>
                                                    <span className="ml-auto shrink-0 text-[10px] font-medium tracking-wide uppercase group-data-[collapsible=icon]:hidden">
                                                        Em breve
                                                    </span>
                                                </SidebarMenuButton>
                                            ) : item.href ? (
                                                <SidebarMenuButton
                                                    asChild
                                                    isActive={isCurrentUrl(
                                                        item.href,
                                                    )}
                                                    tooltip={{
                                                        children: item.title,
                                                    }}
                                                >
                                                    <Link
                                                        href={item.href}
                                                        aria-current={
                                                            isCurrentUrl(
                                                                item.href,
                                                            )
                                                                ? 'page'
                                                                : undefined
                                                        }
                                                        prefetch
                                                    >
                                                        {item.icon && (
                                                            <item.icon />
                                                        )}
                                                        <span className="group-data-[collapsible=icon]:sr-only">
                                                            {item.title}
                                                        </span>
                                                    </Link>
                                                </SidebarMenuButton>
                                            ) : null}
                                        </SidebarMenuItem>
                                    ))}
                                </SidebarMenu>
                            </SidebarGroupContent>
                        </CollapsibleContent>
                    </SidebarGroup>
                </Collapsible>
            ))}
        </>
    );
}
