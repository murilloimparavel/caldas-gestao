/** Semantic Tailwind tokens for the authenticated Online Booking workspace. */
export const bookingTokens = {
    color: {
        canvas: 'bg-[#f6f5f2] text-slate-900 dark:bg-[#0e171a] dark:text-slate-100',
        subtleText: 'text-slate-600 dark:text-slate-300',
        accent: 'text-amber-700 dark:text-amber-300',
        accentSoft: 'bg-amber-100 dark:bg-amber-950/50',
        accentDark: 'text-amber-700 dark:text-amber-200',
        accentSurface: 'bg-amber-300',
        accentForeground: 'text-slate-950 dark:text-slate-950',
        borderStrong: 'border-slate-200 dark:border-slate-700',
        surface:
            'bg-white text-slate-900 dark:bg-[#132125] dark:text-slate-100',
        surfaceSubtle: 'bg-slate-50/80 dark:bg-slate-800/70',
        disabledSurface: 'bg-slate-100/70 dark:bg-slate-800/60',
        readOnlySurface: 'bg-slate-100/80 dark:bg-slate-800/70',
    },
    type: {
        caption: 'text-xs',
        body: 'text-sm',
        sectionTitle: 'text-base',
        pageTitle: 'font-serif text-3xl tracking-tight sm:text-4xl',
    },
    space: {
        page: 'space-y-5',
        pageInset: 'px-5 py-7 sm:px-8 sm:py-9',
        heroContent: 'gap-7',
        sectionContent: 'space-y-5 pt-5',
        controlGroup: 'space-y-2',
        compactRow: 'gap-2',
        control: 'p-3',
    },
    layout: {
        formGrid: 'grid gap-5 sm:grid-cols-2',
    },
    surface: {
        hero: 'relative overflow-hidden rounded-[2rem] bg-[#111112] text-white shadow-2xl shadow-slate-900/10',
        card: 'overflow-hidden rounded-2xl border border-slate-200/80 bg-white text-slate-900 shadow-sm dark:border-slate-700 dark:bg-[#132125] dark:text-slate-100',
        nav: 'rounded-2xl border border-slate-800 bg-slate-950 p-2 shadow-xl shadow-slate-950/10',
        publication: 'rounded-2xl border border-border/70 bg-card shadow-sm',
        field: 'rounded-xl border-slate-200 bg-white text-slate-900 shadow-none placeholder:text-slate-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-400',
        select: 'flex h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-900 transition-[color,box-shadow] outline-none [color-scheme:light] dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:[color-scheme:dark]',
    },
    border: {
        sectionHeader: 'border-b border-slate-200/80 dark:border-slate-700/80',
    },
    radius: {
        control: 'rounded-xl',
        icon: 'rounded-lg',
    },
    shadow: {
        section: 'shadow-sm',
        selected: 'shadow-lg shadow-amber-300/20',
    },
    state: {
        focus: 'focus-visible:border-amber-400 focus-visible:ring-amber-300/30',
        hoverAccent: 'hover:border-amber-400',
        hoverNav: 'hover:bg-slate-900 hover:text-white',
        checkedSelection:
            'has-checked:border-amber-400 has-checked:bg-amber-50 dark:has-checked:border-amber-500 dark:has-checked:bg-amber-950/40',
        disabled: 'disabled:cursor-not-allowed disabled:opacity-50',
        transition: 'transition-colors',
    },
} as const;

/** High-level component recipes assembled from the semantic tokens above. */
export const bookingUi = {
    shell: bookingTokens.color.canvas,
    hero: bookingTokens.surface.hero,
    section: bookingTokens.surface.card,
    sectionHeader: `${bookingTokens.border.sectionHeader} ${bookingTokens.color.surfaceSubtle}`,
    icon: `flex size-8 items-center justify-center ${bookingTokens.radius.icon} ${bookingTokens.color.accentSoft} ${bookingTokens.color.accent}`,
    field: `${bookingTokens.surface.field} ${bookingTokens.state.focus}`,
    select: `${bookingTokens.surface.select} ${bookingTokens.state.focus} focus-visible:ring-[3px] disabled:pointer-events-none ${bookingTokens.state.disabled}`,
    nav: bookingTokens.surface.nav,
    navActive: `${bookingTokens.color.accentSurface} ${bookingTokens.color.accentForeground} ${bookingTokens.shadow.selected}`,
    navIdle: `${bookingTokens.color.subtleText} ${bookingTokens.state.hoverNav}`,
    sectionContent: bookingTokens.space.sectionContent,
    controlGroup: bookingTokens.space.controlGroup,
    publication: bookingTokens.surface.publication,
} as const;
