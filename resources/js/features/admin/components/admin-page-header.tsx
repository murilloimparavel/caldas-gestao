import { Link } from '@inertiajs/react';
import { ArrowLeft, Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';

export function AdminPageHeader({ title, description, backHref, actionHref, actionLabel }: { title: string; description: string; backHref?: string; actionHref?: string; actionLabel?: string }) {
    return (
        <header className="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
            <div className="space-y-2">
                {backHref ? <Link href={backHref} className="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"><ArrowLeft className="size-4" /> Voltar</Link> : null}
                <p className="text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase">Administração</p>
                <h1 className="font-display text-3xl font-semibold tracking-[-0.035em] sm:text-4xl">{title}</h1>
                <p className="max-w-2xl text-sm leading-6 text-muted-foreground sm:text-base">{description}</p>
            </div>
            {actionHref && actionLabel ? <Button asChild><Link href={actionHref}><Plus className="size-4" />{actionLabel}</Link></Button> : null}
        </header>
    );
}
