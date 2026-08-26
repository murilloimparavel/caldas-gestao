import { Link } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarGroup, SidebarGroupContent, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem, useSidebar } from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { SidebarNavGroup } from '@/types';

const STORAGE_VERSION = 'v1';

function readPersistedState(storageKey: string): Record<string, boolean> {
    if (typeof window === 'undefined') {
        return {};
    }

    try {
        const value = window.localStorage.getItem(storageKey);
        const parsed: unknown = value ? JSON.parse(value) : null;

        if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
            return {};
        }

        return Object.fromEntries(Object.entries(parsed).filter(([, open]) => typeof open === 'boolean'));
    } catch {
        return {};
    }
}

type NavMainProps = {
    groups: SidebarNavGroup[];
    persistenceKey?: string | number;
};

export function NavMain({ groups = [], persistenceKey = 'anonymous' }: NavMainProps) {
    const { currentUrl, isCurrentUrl } = useCurrentUrl();
    const { state } = useSidebar();
    const storageKey = `sidebar-nav-groups:${STORAGE_VERSION}:${persistenceKey}`;
    const activeGroupIds = useMemo(() => new Set(groups.filter((group) => group.items.some((item) => item.href && isCurrentUrl(item.href, currentUrl))).map((group) => group.id)), [groups, currentUrl, isCurrentUrl]);
    const [openGroups, setOpenGroups] = useState<Record<string, boolean>>(() => {
        const persisted = readPersistedState(storageKey);

        return Object.fromEntries(groups.map((group) => [group.id, persisted[group.id] ?? activeGroupIds.has(group.id)]));
    });
    const [openFlyout, setOpenFlyout] = useState<string | null>(null);

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        try {
            window.localStorage.setItem(storageKey, JSON.stringify(openGroups));
        } catch {
            // Storage may be unavailable.
        }
    }, [openGroups, storageKey]);

    if (state === 'collapsed') {
        return <>
            {groups.map((group) => {
                const groupIsActive = activeGroupIds.has(group.id);
                const GroupIcon = group.icon;

                return <SidebarGroup key={group.id} className="px-2 py-1">
                    <DropdownMenu
                        open={openFlyout === group.id}
                        onOpenChange={(open) => setOpenFlyout(open ? group.id : null)}
                    >
                        <SidebarMenu>
                            <SidebarMenuItem>
                                <DropdownMenuTrigger asChild>
                                    <SidebarMenuButton
                                        isActive={groupIsActive}
                                        tooltip={group.label}
                                        aria-label={`${group.label}: abrir itens`}
                                        aria-haspopup="menu"
                                        aria-expanded={openFlyout === group.id}
                                    >
                                        {GroupIcon && <GroupIcon aria-hidden="true" />}
                                        <span className="sr-only">{group.label}</span>
                                    </SidebarMenuButton>
                                </DropdownMenuTrigger>
                            </SidebarMenuItem>
                        </SidebarMenu>
                        <DropdownMenuContent
                            side="right"
                            align="start"
                            sideOffset={8}
                            className="w-60"
                            aria-label={`Itens de ${group.label}`}
                        >
                            <DropdownMenuLabel className="flex items-center gap-2 px-2 py-2 text-sm font-semibold">
                                {GroupIcon && <GroupIcon aria-hidden="true" className="size-4" />}
                                {group.label}
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            {group.items.map((item) => item.href ? <DropdownMenuItem key={item.title} asChild>
                                <Link href={item.href} prefetch onClick={() => setOpenFlyout(null)} className="flex w-full items-center gap-2">
                                    {item.icon && <item.icon aria-hidden="true" />}
                                    <span>{item.title}</span>
                                </Link>
                            </DropdownMenuItem> : null)}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </SidebarGroup>;
            })}
        </>;
    }

    return <>
        {groups.map((group) => {
            const groupIsOpen = openGroups[group.id] ?? activeGroupIds.has(group.id);
            const contentId = `sidebar-group-${group.id}`;
            const GroupIcon = group.icon;

            return <Collapsible key={group.id} asChild open={groupIsOpen} onOpenChange={(open) => setOpenGroups((previous) => ({ ...previous, [group.id]: open }))}>
                <SidebarGroup className="px-2 py-1">
                    <CollapsibleTrigger asChild>
                        <SidebarGroupLabel asChild className="group/section-label h-11 cursor-pointer justify-between rounded-lg border border-sidebar-border/60 bg-sidebar-accent/35 px-3 text-sm font-semibold text-sidebar-foreground hover:bg-sidebar-accent/70 hover:text-sidebar-accent-foreground focus-visible:ring-2">
                            <button type="button" aria-expanded={groupIsOpen} aria-controls={contentId} className="gap-2">
                                <span className="flex min-w-0 items-center gap-2">
                                    {GroupIcon && <GroupIcon aria-hidden="true" className="size-4 shrink-0" />}
                                    <span className="truncate tracking-tight">{group.label}</span>
                                </span>
                                <ChevronDown className="size-4 shrink-0 transition-transform duration-200 group-data-[state=open]/section-label:rotate-180" />
                            </button>
                        </SidebarGroupLabel>
                    </CollapsibleTrigger>
                    <CollapsibleContent id={contentId}>
                        <SidebarGroupContent className="mt-1 border-l border-sidebar-border/70 pl-2"><SidebarMenu className="gap-0.5">
                            {group.items.map((item) => item.href ? <SidebarMenuItem key={item.title}>
                                <SidebarMenuButton asChild isActive={isCurrentUrl(item.href)} tooltip={{ children: item.title }} className="min-h-10 pl-3 text-sm font-normal text-sidebar-foreground/80 hover:text-sidebar-accent-foreground data-[active=true]:font-medium data-[active=true]:text-sidebar-accent-foreground">
                                    <Link href={item.href} aria-current={isCurrentUrl(item.href) ? 'page' : undefined} prefetch className="gap-2">
                                        {item.icon && <item.icon aria-hidden="true" />}
                                        <span>{item.title}</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem> : null)}
                        </SidebarMenu></SidebarGroupContent>
                    </CollapsibleContent>
                </SidebarGroup>
            </Collapsible>;
        })}
    </>;
}
