import { Link } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useEffect, useState } from 'react';
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
    const { currentUrl, isCurrentUrl } = useCurrentUrl();
    const { state } = useSidebar();
    const [openGroups, setOpenGroups] = useState<Record<string, boolean>>(() =>
        Object.fromEntries(groups.map((group) => [group.label, true])),
    );

    useEffect(() => {
        const activeGroups = groups.filter((group) =>
            group.items.some((item) => item.href && isCurrentUrl(item.href)),
        );

        if (activeGroups.length > 0) {
            setOpenGroups((prev) => {
                const next = { ...prev };
                let hasChanges = false;
                for (const group of activeGroups) {
                    if (!next[group.label]) {
                        next[group.label] = true;
                        hasChanges = true;
                    }
                }
                return hasChanges ? next : prev;
            });
        }
    }, [currentUrl, groups, isCurrentUrl]);

    return (
        <>
            {groups.map((group) => (
                <Collapsible
                    key={group.label}
                    asChild
                    open={
                        state === 'collapsed'
                            ? false
                            : (openGroups[group.label] ?? true)
                    }
                    onOpenChange={(open) => {
                        setOpenGroups((prev) => ({
                            ...prev,
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
