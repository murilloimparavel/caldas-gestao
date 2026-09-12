import { Inbox } from 'lucide-react';

type EmptyStateProps = { message: string; className?: string };

export function EmptyState({ message, className = '' }: EmptyStateProps) {
    return (
        <div
            className={`flex min-h-28 flex-col items-center justify-center gap-2 px-4 py-6 text-center ${className}`}
            role="status"
        >
            <span className="flex size-9 items-center justify-center rounded-full bg-muted text-muted-foreground">
                <Inbox className="size-4" aria-hidden="true" />
            </span>
            <p className="max-w-sm text-xs leading-5 text-muted-foreground">
                {message}
            </p>
        </div>
    );
}
