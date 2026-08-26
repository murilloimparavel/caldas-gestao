import { Scissors, Package, Layers } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import type { CategorySales } from '../types';

type SalesCategoryBreakdownProps = {
    data?: CategorySales[];
};

const DEFAULT_CATEGORY_DATA: CategorySales[] = [
    { category: 'services', label: 'Serviços', totalAmount: 'R$ 9.800,00', percentage: 66, color: '#3b82f6' },
    { category: 'products', label: 'Produtos', totalAmount: 'R$ 3.250,00', percentage: 22, color: '#10b981' },
    { category: 'packages', label: 'Pacotes', totalAmount: 'R$ 1.800,00', percentage: 12, color: '#8b5cf6' },
];

export function SalesCategoryBreakdown({ data = DEFAULT_CATEGORY_DATA }: SalesCategoryBreakdownProps) {
    const getCategoryIcon = (category: string) => {
        switch (category) {
            case 'services':
                return Scissors;
            case 'products':
                return Package;
            case 'packages':
            default:
                return Layers;
        }
    };

    return (
        <Card className="border-border/60">
            <CardHeader className="pb-3">
                <CardTitle className="text-base font-semibold">Vendas por Categoria</CardTitle>
                <CardDescription className="text-xs">Distribuição da receita entre serviços, produtos e pacotes</CardDescription>
            </CardHeader>
            <CardContent className="pt-2">
                {/* Progress bar visual */}
                <div className="flex h-3 w-full overflow-hidden rounded-full bg-muted/40">
                    {data.map((item, idx) => (
                        <div
                            key={idx}
                            style={{
                                width: `${item.percentage}%`,
                                backgroundColor: item.color,
                            }}
                            className="h-full transition-all duration-500 first:rounded-l-full last:rounded-r-full hover:opacity-85"
                            title={`${item.label}: ${item.percentage}%`}
                        />
                    ))}
                </div>

                <div className="mt-5 grid gap-3 sm:grid-cols-3">
                    {data.map((item) => {
                        const Icon = getCategoryIcon(item.category);

                        return (
                            <div key={item.category} className="flex flex-col rounded-lg border border-border/40 bg-muted/20 p-3">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-1.5">
                                        <span className="size-2 rounded-full" style={{ backgroundColor: item.color }} />
                                        <span className="text-xs font-medium text-muted-foreground">{item.label}</span>
                                    </div>
                                    <Icon className="size-3.5 text-muted-foreground" />
                                </div>
                                <span className="mt-2 text-base font-bold text-foreground">{item.totalAmount}</span>
                                <span className="text-[11px] font-medium text-muted-foreground">{item.percentage}% do total</span>
                            </div>
                        );
                    })}
                </div>
            </CardContent>
        </Card>
    );
}
