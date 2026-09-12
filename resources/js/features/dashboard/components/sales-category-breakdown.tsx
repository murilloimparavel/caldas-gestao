import { Scissors, Package, Layers } from 'lucide-react';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/components/ui/card';
import type { CategorySales, SalesByCategoryBackend } from '../types';
import { ChartLegend } from './chart-legend';

type SalesCategoryBreakdownProps = {
    data?: CategorySales[] | SalesByCategoryBackend;
};

export function SalesCategoryBreakdown({ data }: SalesCategoryBreakdownProps) {
    const formatCurrency = (cents: number) => {
        return (cents / 100).toLocaleString('pt-BR', {
            style: 'currency',
            currency: 'BRL',
        });
    };

    const categoriesList: CategorySales[] = Array.isArray(data)
        ? data
        : data
          ? [
                {
                    category: 'services',
                    label: 'Serviços',
                    totalAmount: formatCurrency(data.services.total_cents),
                    percentage: data.services.percentage,
                    color: 'var(--chart-1)',
                },
                {
                    category: 'products',
                    label: 'Produtos',
                    totalAmount: formatCurrency(data.products.total_cents),
                    percentage: data.products.percentage,
                    color: 'var(--chart-2)',
                },
                {
                    category: 'packages',
                    label: 'Pacotes',
                    totalAmount: formatCurrency(data.packages.total_cents),
                    percentage: data.packages.percentage,
                    color: 'var(--chart-4)',
                },
            ]
          : [];

    const hasData =
        categoriesList.length > 0 &&
        categoriesList.some(
            (item) => item.percentage > 0 || item.totalAmount !== 'R$ 0,00',
        );

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
                <CardTitle className="text-base font-semibold">
                    Vendas por Categoria
                </CardTitle>
                <CardDescription className="text-xs">
                    Distribuição da receita entre serviços, produtos e pacotes
                </CardDescription>
            </CardHeader>
            <CardContent className="pt-2">
                {!hasData ? (
                    <div className="flex h-28 items-center justify-center rounded-lg border border-dashed border-border/60 text-xs text-muted-foreground">
                        Nenhuma venda registrada no período
                    </div>
                ) : (
                    <>
                        {/* Progress bar visual */}
                        <div className="flex h-3 w-full overflow-hidden rounded-full bg-muted/40">
                            {categoriesList.map((item, idx) => (
                                <div
                                    key={idx}
                                    style={{
                                        width: `${item.percentage}%`,
                                        backgroundColor: item.color,
                                    }}
                                    className="h-full transition-all duration-500 first:rounded-l-full last:rounded-r-full hover:opacity-85"
                                    role="img"
                                    aria-label={`${item.label}: ${item.percentage}%`}
                                    title={`${item.label}: ${item.percentage}%`}
                                />
                            ))}
                        </div>

                        <div className="mt-3">
                            <ChartLegend
                                items={categoriesList.map((item) => ({
                                    label: item.label,
                                    color: item.color,
                                }))}
                            />
                        </div>

                        <div className="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            {categoriesList.map((item) => {
                                const Icon = getCategoryIcon(item.category);

                                return (
                                    <div
                                        key={item.category}
                                        className="flex flex-col rounded-lg border border-border/40 bg-muted/20 p-3 transition-colors hover:bg-muted/40"
                                    >
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-1.5">
                                                <span
                                                    className="size-2 rounded-full"
                                                    style={{
                                                        backgroundColor:
                                                            item.color,
                                                    }}
                                                />
                                                <span className="text-xs font-medium text-muted-foreground">
                                                    {item.label}
                                                </span>
                                            </div>
                                            <Icon className="size-3.5 text-muted-foreground" />
                                        </div>
                                        <span className="mt-2 text-base font-bold text-foreground">
                                            {item.totalAmount ??
                                                (item.total_cents !== undefined
                                                    ? formatCurrency(
                                                          item.total_cents,
                                                      )
                                                    : 'R$ 0,00')}
                                        </span>
                                        <span className="text-[11px] font-medium text-muted-foreground">
                                            {item.percentage}% do total
                                        </span>
                                    </div>
                                );
                            })}
                        </div>
                    </>
                )}
            </CardContent>
        </Card>
    );
}
