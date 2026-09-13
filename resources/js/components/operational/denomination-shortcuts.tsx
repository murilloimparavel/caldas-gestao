import { RotateCcw } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export interface DenominationShortcutsProps {
    onAdd: (amountInReais: number) => void;
    onReset: () => void;
    disabled?: boolean;
    className?: string;
}

const DENOMINATIONS = [
    { label: '+R$ 10', amount: 10 },
    { label: '+R$ 20', amount: 20 },
    { label: '+R$ 50', amount: 50 },
    { label: '+R$ 100', amount: 100 },
] as const;

export function DenominationShortcuts({
    onAdd,
    onReset,
    disabled = false,
    className,
}: DenominationShortcutsProps) {
    return (
        <div
            role="group"
            aria-label="Atalhos rápidos de cédulas"
            className={cn(
                'flex flex-wrap items-center gap-1.5 pt-1.5',
                className,
            )}
        >
            {DENOMINATIONS.map(({ label, amount }) => (
                <Button
                    key={amount}
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={disabled}
                    onClick={() => onAdd(amount)}
                    className="h-7 rounded-full border-dashed border-border px-2.5 text-xs font-medium text-foreground transition-transform hover:border-primary hover:bg-primary/10 hover:text-primary active:scale-95"
                >
                    {label}
                </Button>
            ))}
            <Button
                type="button"
                variant="ghost"
                size="sm"
                disabled={disabled}
                onClick={onReset}
                className="h-7 rounded-full px-2 text-xs font-medium text-muted-foreground transition-transform hover:bg-destructive/10 hover:text-destructive active:scale-95"
                title="Limpar valor (resetar para 0,00)"
            >
                <RotateCcw className="mr-1 size-3" />
                Limpar
            </Button>
        </div>
    );
}
