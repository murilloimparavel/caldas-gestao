/** Shared visual tokens for the authenticated online-booking workspace. */
export const bookingUi = {
    shell: 'bg-[#f6f5f2]',
    hero: 'relative overflow-hidden rounded-[2rem] bg-[#111112] text-white shadow-2xl shadow-slate-900/10',
    section:
        'overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm',
    sectionHeader: 'border-b border-slate-200/80 bg-slate-50/80',
    icon: 'flex size-8 items-center justify-center rounded-lg bg-amber-100 text-amber-700',
    field: 'rounded-xl border-slate-200 bg-white shadow-none focus-visible:border-amber-400 focus-visible:ring-amber-300/30',
    nav: 'rounded-2xl border border-slate-800 bg-slate-950 p-2 shadow-xl shadow-slate-950/10',
    navActive: 'bg-amber-300 text-slate-950 shadow-lg shadow-amber-300/20',
    navIdle: 'text-slate-400 hover:bg-slate-900 hover:text-white',
} as const;

export const bookingColors = {
    ink: '#111112',
    paper: '#f6f5f2',
    accent: '#fcd34d',
} as const;
