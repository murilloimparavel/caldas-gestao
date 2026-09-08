import { Form } from '@inertiajs/react';
import { ArrowDownRight, ArrowUpRight, Boxes, RotateCcw } from 'lucide-react';
import { useState } from 'react';
import {
    createIdempotencyKey,
    FormActions,
    FormErrorSummary,
    FormField,
    parseBrazilianCurrency,
} from '@/components/operational';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import inventory from '@/routes/inventory';

export type StockAdjustableProduct = {
    cost_price_cents?: number;
    current_stock: number;
    id: string;
    lock_version: number;
    min_stock?: number;
    name: string;
    unit_of_measure: string;
};

type Props = {
    onOpenChange: (open: boolean) => void;
    open: boolean;
    product: StockAdjustableProduct;
    trigger?: React.ReactNode;
};

export function StockAdjustmentDialog({
    product,
    open,
    onOpenChange,
    trigger,
}: Props) {
    const [movementType, setMovementType] = useState<
        | 'purchase_inflow'
        | 'adjustment_gain'
        | 'adjustment_loss'
        | 'manual_count'
    >('purchase_inflow');
    const [quantityInput, setQuantityInput] = useState<string>('1');
    const [costInput, setCostInput] = useState<string>(
        product.cost_price_cents && product.cost_price_cents > 0
            ? (product.cost_price_cents / 100).toFixed(2).replace('.', ',')
            : '',
    );
    const [idempotencyKey, setIdempotencyKey] = useState(() =>
        createIdempotencyKey('inventory-adjust'),
    );

    const qty = parseInt(quantityInput, 10);
    const validQty = Number.isFinite(qty) ? qty : 0;

    const resultingStock =
        movementType === 'manual_count'
            ? Math.max(0, validQty)
            : movementType === 'adjustment_loss'
              ? product.current_stock - Math.max(0, validQty)
              : product.current_stock + Math.max(0, validQty);

    const costCents = parseBrazilianCurrency(costInput);

    const handleOpenChange = (nextOpen: boolean) => {
        if (nextOpen) {
            setIdempotencyKey(createIdempotencyKey('inventory-adjust'));
            setQuantityInput('1');
            setCostInput(
                product.cost_price_cents && product.cost_price_cents > 0
                    ? (product.cost_price_cents / 100)
                          .toFixed(2)
                          .replace('.', ',')
                    : '',
            );
        }

        onOpenChange(nextOpen);
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            {trigger ? <DialogTrigger asChild>{trigger}</DialogTrigger> : null}
            <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Boxes className="size-5 text-primary" />
                        Ajustar Estoque
                    </DialogTitle>
                    <DialogDescription>
                        Lance uma entrada, saída ou contagem física de estoque
                        para{' '}
                        <strong className="text-foreground">
                            {product.name}
                        </strong>
                        .
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-3 rounded-xl border border-border bg-muted/40 p-4">
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">
                            Estoque atual:
                        </span>
                        <span className="font-semibold text-foreground">
                            {product.current_stock} {product.unit_of_measure}
                        </span>
                    </div>

                    <div className="flex items-center justify-between border-t border-border/60 pt-2 text-sm">
                        <span className="text-muted-foreground">
                            Estoque resultante:
                        </span>
                        <span
                            className={`flex items-center gap-1.5 font-semibold ${
                                resultingStock < 0
                                    ? 'text-destructive'
                                    : resultingStock <= (product.min_stock ?? 0)
                                      ? 'text-amber-500'
                                      : 'text-emerald-600 dark:text-emerald-400'
                            }`}
                        >
                            {movementType === 'adjustment_loss' ? (
                                <ArrowDownRight className="size-4" />
                            ) : movementType === 'manual_count' ? (
                                <RotateCcw className="size-4" />
                            ) : (
                                <ArrowUpRight className="size-4" />
                            )}
                            {resultingStock} {product.unit_of_measure}
                        </span>
                    </div>
                </div>

                <Form
                    {...inventory.movements.store.form()}
                    headers={{ 'X-Idempotency-Key': idempotencyKey }}
                    resetOnSuccess
                    onSuccess={() => onOpenChange(false)}
                    className="space-y-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <FormErrorSummary errors={errors} />

                            <input
                                type="hidden"
                                name="product_id"
                                value={product.id}
                            />
                            <input
                                type="hidden"
                                name="lock_version"
                                value={product.lock_version}
                            />

                            <div>
                                <FormField
                                    label="Tipo de movimentação"
                                    name="type"
                                    error={errors.type}
                                >
                                    <select
                                        id="type"
                                        name="type"
                                        value={movementType}
                                        onChange={(e) =>
                                            setMovementType(
                                                e.target
                                                    .value as typeof movementType,
                                            )
                                        }
                                        className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-base outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm"
                                    >
                                        <option value="purchase_inflow">
                                            Entrada por compra (Nota fiscal /
                                            Fornecedor)
                                        </option>
                                        <option value="adjustment_gain">
                                            Ajuste de entrada (Sobra /
                                            Bonificação)
                                        </option>
                                        <option value="adjustment_loss">
                                            Ajuste de saída (Avaria / Perda /
                                            Uso interno)
                                        </option>
                                        <option value="manual_count">
                                            Balanço físico (Substituir estoque
                                            contado)
                                        </option>
                                    </select>
                                </FormField>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <FormField
                                        label={
                                            movementType === 'manual_count'
                                                ? 'Nova quantidade contada'
                                                : movementType ===
                                                    'adjustment_loss'
                                                  ? 'Quantidade a subtrair'
                                                  : 'Quantidade a adicionar'
                                        }
                                        name="quantity"
                                        error={errors.quantity}
                                    >
                                        <Input
                                            id="quantity"
                                            name="quantity"
                                            type="number"
                                            min={
                                                movementType === 'manual_count'
                                                    ? 0
                                                    : 1
                                            }
                                            value={quantityInput}
                                            onChange={(e) =>
                                                setQuantityInput(e.target.value)
                                            }
                                            required
                                        />
                                    </FormField>
                                </div>

                                {movementType === 'purchase_inflow' ||
                                movementType === 'adjustment_gain' ? (
                                    <div>
                                        <FormField
                                            label="Custo unitário (R$)"
                                            name="unit_cost_cents"
                                            error={errors.unit_cost_cents}
                                        >
                                            <Input
                                                id="cost_display"
                                                name="cost_display"
                                                inputMode="decimal"
                                                placeholder="0,00"
                                                value={costInput}
                                                onChange={(e) =>
                                                    setCostInput(e.target.value)
                                                }
                                            />
                                            <input
                                                type="hidden"
                                                name="unit_cost_cents"
                                                value={costCents}
                                            />
                                        </FormField>
                                    </div>
                                ) : null}
                            </div>

                            <div>
                                <FormField
                                    label="Motivo / Justificativa"
                                    name="reason"
                                    error={errors.reason}
                                >
                                    <Input
                                        id="reason"
                                        name="reason"
                                        required
                                        placeholder={
                                            movementType === 'purchase_inflow'
                                                ? 'Ex.: Recebimento de compra NF 1042'
                                                : movementType ===
                                                    'manual_count'
                                                  ? 'Ex.: Balanço físico mensal'
                                                  : movementType ===
                                                      'adjustment_loss'
                                                    ? 'Ex.: Frasco quebrado durante transporte'
                                                    : 'Ex.: Sobra identificada em conferência'
                                        }
                                    />
                                </FormField>
                            </div>

                            <FormActions
                                processing={processing}
                                onCancel={() => onOpenChange(false)}
                                label="Confirmar movimentação"
                            />
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
