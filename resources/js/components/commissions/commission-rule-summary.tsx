import { CheckCircle2, Info, Layers3, UserRound } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import type {
    CommissionItemType,
    CommissionScopeMode,
} from './commission-rule-types';

type CommissionRuleSummaryProps = {
    professionalName: string;
    itemType: CommissionItemType;
    scopeMode: CommissionScopeMode;
    categoryNames: string[];
    selectedCount: number;
    rateType: 'percentage' | 'fixed';
    rate: string | number;
    isActive: boolean;
};

export function CommissionRuleSummary({
    professionalName,
    itemType,
    scopeMode,
    categoryNames,
    selectedCount,
    rateType,
    rate,
    isActive,
}: CommissionRuleSummaryProps) {
    const itemLabel =
        itemType === 'all'
            ? 'Todos os itens'
            : itemType === 'service'
              ? 'Serviços'
              : 'Produtos';
    const scopeLabel =
        scopeMode === 'all'
            ? itemType === 'all'
                ? 'Todos os serviços e produtos atuais e futuros'
                : `Todos ${itemType === 'service' ? 'os serviços' : 'os produtos'}`
            : scopeMode === 'category'
              ? categoryNames.join(', ') || 'Categorias ainda não selecionadas'
              : `${selectedCount} item${selectedCount === 1 ? '' : 's'} específico${selectedCount === 1 ? '' : 's'}`;

    return (
        <aside className="h-fit overflow-hidden rounded-2xl border border-primary/20 bg-gradient-to-br from-primary/10 via-card to-card shadow-sm lg:sticky lg:top-6">
            <div className="border-b border-border/70 px-5 py-4">
                <div className="flex items-center justify-between gap-3">
                    <div>
                        <p className="text-xs font-semibold tracking-[0.16em] text-primary uppercase">
                            Prévia da regra
                        </p>
                        <h2 className="mt-1 text-lg font-semibold text-foreground">
                            Resumo em tempo real
                        </h2>
                    </div>
                    <Badge
                        variant={isActive ? 'default' : 'secondary'}
                        className="gap-1 rounded-full"
                    >
                        {isActive ? (
                            <CheckCircle2 className="h-3.5 w-3.5" />
                        ) : (
                            <Info className="h-3.5 w-3.5" />
                        )}
                        {isActive ? 'Ativa' : 'Inativa'}
                    </Badge>
                </div>
            </div>
            <div className="space-y-5 px-5 py-5">
                <div className="flex items-start gap-3">
                    <UserRound className="mt-0.5 h-4 w-4 text-primary" />
                    <div>
                        <p className="text-xs text-muted-foreground">
                            Profissional
                        </p>
                        <p className="mt-0.5 text-sm font-semibold text-foreground">
                            {professionalName || 'Todos os profissionais'}
                        </p>
                    </div>
                </div>
                <div className="flex items-start gap-3">
                    <Layers3 className="mt-0.5 h-4 w-4 text-primary" />
                    <div>
                        <p className="text-xs text-muted-foreground">
                            Abrangência
                        </p>
                        <p className="mt-0.5 text-sm font-semibold text-foreground">
                            {itemLabel}
                        </p>
                        <p className="mt-1 text-xs leading-5 text-muted-foreground">
                            {scopeLabel}
                        </p>
                    </div>
                </div>
                <div className="rounded-xl border border-border/70 bg-background/50 p-4">
                    <p className="text-xs text-muted-foreground">
                        Repasse configurado
                    </p>
                    <p className="mt-1 text-2xl font-bold tracking-tight text-foreground">
                        {rate || '0'}
                        {rateType === 'percentage' ? '%' : ' centavos'}
                    </p>
                    <p className="mt-1 text-xs text-muted-foreground">
                        {rateType === 'percentage'
                            ? 'sobre o valor de cada item'
                            : 'por atendimento ou unidade vendida'}
                    </p>
                </div>
                {scopeMode !== 'specific' && (
                    <p className="flex gap-2 text-xs leading-5 text-muted-foreground">
                        <Info className="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                        Novos{' '}
                        {itemType === 'all'
                            ? 'itens'
                            : itemType === 'product'
                              ? 'produtos'
                              : 'serviços'}{' '}
                        que entrarem neste escopo serão incluídos
                        automaticamente.
                    </p>
                )}
            </div>
        </aside>
    );
}
