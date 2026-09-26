import { Form } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    BriefcaseBusiness,
    Check,
    Package,
    Sparkles,
    Users,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    createIdempotencyKey,
    FormActions,
    FormErrorSummary,
    FormField,
} from '@/components/operational';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { CommissionRule } from '@/types';
import { CommissionRuleSummary } from './commission-rule-summary';
import type {
    CommissionCategoryOption,
    CommissionItemType,
    CommissionProductOption,
    CommissionScopeMode,
    CommissionServiceOption,
} from './commission-rule-types';
import { SelectionPicker } from './selection-picker';

type CommissionRuleBuilderProps = {
    professionals: { id: string; name: string }[];
    rules: CommissionRule[];
    services: CommissionServiceOption[];
    products: CommissionProductOption[];
    categories?: CommissionCategoryOption[];
    serviceCategories?: CommissionCategoryOption[];
    productCategories?: CommissionCategoryOption[];
    editingRule: CommissionRule | null;
    onCancel: () => void;
};

const typeOptions: {
    value: CommissionItemType;
    label: string;
    description: string;
    icon: typeof BriefcaseBusiness;
}[] = [
    {
        value: 'service',
        label: 'Serviços',
        description: 'Atendimentos e procedimentos',
        icon: BriefcaseBusiness,
    },
    {
        value: 'product',
        label: 'Produtos',
        description: 'Itens vendidos por unidade',
        icon: Package,
    },
    {
        value: 'all',
        label: 'Todos os itens',
        description: 'Regra geral para serviços e produtos',
        icon: Users,
    },
];

function getInitialItemType(rule: CommissionRule | null): CommissionItemType {
    if (rule?.scope === 'all') {
        return 'all';
    }

    return rule?.scope === 'product' ||
        rule?.scope === 'product_category' ||
        Boolean(rule?.product_id)
        ? 'product'
        : 'service';
}

function getInitialScopeMode(rule: CommissionRule | null): CommissionScopeMode {
    if (
        rule?.scope === 'service_category' ||
        rule?.scope === 'product_category'
    ) {
        return 'category';
    }

    if (rule?.service_id || rule?.product_id) {
        return 'specific';
    }

    return 'all';
}

export function CommissionRuleBuilder({
    professionals,
    rules,
    services,
    products,
    categories = [],
    serviceCategories = [],
    productCategories = [],
    editingRule,
    onCancel,
}: CommissionRuleBuilderProps) {
    const initialItemType = getInitialItemType(editingRule);
    const initialScopeMode = getInitialScopeMode(editingRule);
    const derivedServiceCategories = categories.filter(
        (category) =>
            category.type === 'service' || category.type === 'general',
    );
    const derivedProductCategories = categories.filter(
        (category) =>
            category.type === 'product' || category.type === 'general',
    );
    const resolvedServiceCategories =
        derivedServiceCategories.length > 0
            ? derivedServiceCategories
            : serviceCategories;
    const resolvedProductCategories =
        derivedProductCategories.length > 0
            ? derivedProductCategories
            : productCategories;
    const [professionalId, setProfessionalId] = useState(
        editingRule?.professional_id ?? '',
    );
    const [itemType, setItemType] =
        useState<CommissionItemType>(initialItemType);
    const [scopeMode, setScopeMode] =
        useState<CommissionScopeMode>(initialScopeMode);
    const [selectedServiceIds, setSelectedServiceIds] = useState<string[]>(
        editingRule?.service_id ? [editingRule.service_id] : [],
    );
    const [selectedProductIds, setSelectedProductIds] = useState<string[]>(
        editingRule?.product_id ? [editingRule.product_id] : [],
    );
    const [selectedServiceCategoryId, setSelectedServiceCategoryId] = useState(
        editingRule?.scope === 'service_category'
            ? (editingRule.category_id ?? '')
            : '',
    );
    const [selectedProductCategoryId, setSelectedProductCategoryId] = useState(
        editingRule?.scope === 'product_category'
            ? (editingRule.category_id ?? '')
            : '',
    );
    const [rateType, setRateType] = useState<'percentage' | 'fixed'>(
        editingRule?.type ?? 'percentage',
    );
    const [rate, setRate] = useState(String(editingRule?.value_rate ?? 10));
    const [isActive, setIsActive] = useState(editingRule?.is_active ?? true);

    const clearTargetSelections = () => {
        setSelectedServiceIds([]);
        setSelectedProductIds([]);
        setSelectedServiceCategoryId('');
        setSelectedProductCategoryId('');
    };
    const handleItemTypeChange = (next: CommissionItemType) => {
        if (next === itemType) {
            return;
        }

        clearTargetSelections();

        if (next === 'all') {
            setScopeMode('all');
        }

        setItemType(next);
    };
    const handleScopeChange = (next: CommissionScopeMode) => {
        if (next === scopeMode) {
            return;
        }

        clearTargetSelections();
        setScopeMode(next);
    };

    const selectedCategoryId =
        itemType === 'service'
            ? selectedServiceCategoryId
            : selectedProductCategoryId;
    const selectedCategories =
        itemType === 'service'
            ? resolvedServiceCategories
            : resolvedProductCategories;
    const selectedCategoryNames = selectedCategories
        .filter((category) => category.id === selectedCategoryId)
        .map((category) => category.name);
    const selectedCount =
        itemType === 'service'
            ? selectedServiceIds.length
            : itemType === 'product'
              ? selectedProductIds.length
              : 0;
    const professionalName =
        professionals.find((professional) => professional.id === professionalId)
            ?.name ?? '';
    const currentScope =
        itemType === 'all'
            ? 'all'
            : scopeMode === 'category'
              ? itemType === 'service'
                  ? 'service_category'
                  : 'product_category'
              : itemType;
    const hasConflict = useMemo(
        () =>
            rules.some((rule) => {
                if (
                    rule.id === editingRule?.id ||
                    rule.professional_id !== (professionalId || null) ||
                    rule.scope !== currentScope
                ) {
                    return false;
                }

                if (
                    currentScope === 'service_category' ||
                    currentScope === 'product_category'
                ) {
                    return rule.category_id === selectedCategoryId;
                }

                if (currentScope === 'service') {
                    return rule.service_id === (selectedServiceIds[0] ?? null);
                }

                if (currentScope === 'product') {
                    return rule.product_id === (selectedProductIds[0] ?? null);
                }

                return true;
            }),
        [
            currentScope,
            editingRule?.id,
            professionalId,
            rules,
            selectedCategoryId,
            selectedProductIds,
            selectedServiceIds,
        ],
    );
    const isInvalid =
        (scopeMode === 'specific' && selectedCount === 0) ||
        (scopeMode === 'category' && !selectedCategoryId) ||
        hasConflict;

    return (
        <div className="space-y-6">
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="flex items-start gap-3">
                    <Button
                        variant="outline"
                        size="icon"
                        onClick={onCancel}
                        aria-label="Voltar para comissões"
                    >
                        <ArrowLeft className="h-4 w-4" />
                    </Button>
                    <div>
                        <p className="text-xs font-semibold tracking-[0.18em] text-primary uppercase">
                            Construtor de regras
                        </p>
                        <h1 className="mt-1 text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                            {editingRule
                                ? 'Editar regra de comissão'
                                : 'Nova regra de comissão'}
                        </h1>
                        <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                            Defina uma regra para serviços ou produtos sem
                            misturar os contratos de cada tipo.
                        </p>
                    </div>
                </div>
                <Badge
                    variant="outline"
                    className="gap-1 rounded-full px-3 py-1.5"
                >
                    <Sparkles className="h-3.5 w-3.5 text-primary" />
                    Configuração guiada
                </Badge>
            </div>
            <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_330px]">
                <Form
                    action={
                        editingRule
                            ? `/finance/commissions/rules/${editingRule.id}`
                            : '/finance/commissions/rules'
                    }
                    method={editingRule ? 'put' : 'post'}
                    headers={{
                        'X-Idempotency-Key': createIdempotencyKey('save-rule'),
                    }}
                    onSuccess={onCancel}
                    className="space-y-5"
                >
                    {({ processing, errors }) => (
                        <>
                            <FormErrorSummary errors={errors} />
                            {editingRule && (
                                <input
                                    type="hidden"
                                    name="lock_version"
                                    value={editingRule.lock_version}
                                />
                            )}
                            <section className="rounded-2xl border bg-card p-5 shadow-sm sm:p-6">
                                <Step
                                    number="01"
                                    title="Quem receberá?"
                                    description="Escolha um profissional ou uma regra geral."
                                />
                                <FormField
                                    label="Profissional"
                                    error={errors.professional_id}
                                >
                                    <select
                                        name="professional_id"
                                        value={professionalId}
                                        onChange={(event) =>
                                            setProfessionalId(
                                                event.target.value,
                                            )
                                        }
                                        className="h-11 w-full rounded-lg border border-input bg-background px-3 text-sm text-foreground outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                    >
                                        <option value="">
                                            Todos os profissionais (regra geral)
                                        </option>
                                        {professionals.map((professional) => (
                                            <option
                                                key={professional.id}
                                                value={professional.id}
                                            >
                                                {professional.name}
                                            </option>
                                        ))}
                                    </select>
                                </FormField>
                            </section>
                            <section className="rounded-2xl border bg-card p-5 shadow-sm sm:p-6">
                                <Step
                                    number="02"
                                    title="O que entra nesta regra?"
                                    description="O backend salva serviços e produtos em escopos separados."
                                />
                                <div className="grid gap-3 sm:grid-cols-2">
                                    {typeOptions.map(
                                        ({
                                            value,
                                            label,
                                            description,
                                            icon: Icon,
                                        }) => (
                                            <button
                                                key={value}
                                                type="button"
                                                onClick={() =>
                                                    handleItemTypeChange(value)
                                                }
                                                className={`group rounded-xl border p-4 text-left transition-all ${itemType === value ? 'border-primary bg-primary/8 shadow-sm ring-1 ring-primary/25' : 'border-border hover:border-primary/40 hover:bg-muted/30'}`}
                                            >
                                                <div className="flex items-center justify-between gap-2">
                                                    <Icon
                                                        className={`h-5 w-5 ${itemType === value ? 'text-primary' : 'text-muted-foreground'}`}
                                                    />
                                                    {itemType === value && (
                                                        <span className="flex h-5 w-5 items-center justify-center rounded-full bg-primary text-primary-foreground">
                                                            <Check className="h-3 w-3" />
                                                        </span>
                                                    )}
                                                </div>
                                                <p className="mt-4 text-sm font-semibold text-foreground">
                                                    {label}
                                                </p>
                                                <p className="mt-1 text-xs leading-5 text-muted-foreground">
                                                    {description}
                                                </p>
                                            </button>
                                        ),
                                    )}
                                </div>
                            </section>
                            <section className="rounded-2xl border bg-card p-5 shadow-sm sm:p-6">
                                <Step
                                    number="03"
                                    title="Qual é o alcance?"
                                    description="Trocar o alcance limpa seleções anteriores."
                                />
                                <div className="grid gap-3">
                                    {(
                                        [
                                            {
                                                value: 'all',
                                                label:
                                                    itemType === 'all'
                                                        ? 'Regra geral'
                                                        : `Todos ${itemType === 'service' ? 'os serviços' : 'os produtos'}`,
                                                description:
                                                    itemType === 'all'
                                                        ? 'Todos os serviços e produtos atuais e futuros.'
                                                        : `Todos os ${itemType === 'service' ? 'serviços' : 'produtos'} atuais e futuros.`,
                                            },
                                            ...(itemType === 'all'
                                                ? []
                                                : [
                                                      {
                                                          value: 'category',
                                                          label: `Categoria de ${itemType === 'service' ? 'serviços' : 'produtos'}`,
                                                          description: `Inclui novos ${itemType === 'service' ? 'serviços' : 'produtos'} da categoria escolhida.`,
                                                      },
                                                      {
                                                          value: 'specific',
                                                          label: `${itemType === 'service' ? 'Serviços' : 'Produtos'} específicos`,
                                                          description:
                                                              'Aplica somente aos itens selecionados; novos itens ficam fora.',
                                                      },
                                                  ]),
                                        ] as {
                                            value: CommissionScopeMode;
                                            label: string;
                                            description: string;
                                        }[]
                                    ).map((option) => (
                                        <label
                                            key={option.value}
                                            className={`flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition-colors ${scopeMode === option.value ? 'border-primary bg-primary/8' : 'border-border hover:border-primary/40'}`}
                                        >
                                            <input
                                                type="radio"
                                                name="scope_mode"
                                                value={option.value}
                                                checked={
                                                    scopeMode === option.value
                                                }
                                                onChange={() =>
                                                    handleScopeChange(
                                                        option.value,
                                                    )
                                                }
                                                className="mt-1 h-4 w-4 accent-primary"
                                            />
                                            <span>
                                                <span className="block text-sm font-semibold text-foreground">
                                                    {option.label}
                                                </span>
                                                <span className="mt-1 block text-xs leading-5 text-muted-foreground">
                                                    {option.description}
                                                </span>
                                            </span>
                                        </label>
                                    ))}
                                </div>
                                {itemType !== 'all' &&
                                    scopeMode === 'category' && (
                                        <CategoryList
                                            title={`Categoria de ${itemType === 'service' ? 'serviços' : 'produtos'}`}
                                            categories={selectedCategories}
                                            selectedId={selectedCategoryId}
                                            onSelect={(id) =>
                                                itemType === 'service'
                                                    ? setSelectedServiceCategoryId(
                                                          id,
                                                      )
                                                    : setSelectedProductCategoryId(
                                                          id,
                                                      )
                                            }
                                            emptyLabel={`Nenhuma categoria de ${itemType === 'service' ? 'serviço' : 'produto'} foi enviada pelo backend.`}
                                        />
                                    )}
                                {itemType !== 'all' &&
                                    scopeMode === 'specific' && (
                                        <div className="mt-4">
                                            <SelectionPicker
                                                kind={itemType}
                                                items={
                                                    itemType === 'service'
                                                        ? services
                                                        : products
                                                }
                                                categories={selectedCategories}
                                                selectedIds={
                                                    itemType === 'service'
                                                        ? selectedServiceIds
                                                        : selectedProductIds
                                                }
                                                onChange={
                                                    itemType === 'service'
                                                        ? setSelectedServiceIds
                                                        : setSelectedProductIds
                                                }
                                            />
                                        </div>
                                    )}
                                {scopeMode !== 'specific' && (
                                    <input
                                        type="hidden"
                                        name="scope"
                                        value={currentScope}
                                    />
                                )}
                                {editingRule && scopeMode === 'specific' && (
                                    <input
                                        type="hidden"
                                        name="scope"
                                        value={itemType}
                                    />
                                )}
                                {itemType !== 'all' &&
                                    scopeMode === 'category' && (
                                        <input
                                            type="hidden"
                                            name="category_id"
                                            value={selectedCategoryId}
                                        />
                                    )}
                                {!editingRule &&
                                    itemType === 'service' &&
                                    scopeMode === 'specific' &&
                                    selectedServiceIds.map((id) => (
                                        <input
                                            key={id}
                                            type="hidden"
                                            name="service_ids[]"
                                            value={id}
                                        />
                                    ))}
                                {!editingRule &&
                                    itemType === 'product' &&
                                    scopeMode === 'specific' &&
                                    selectedProductIds.map((id) => (
                                        <input
                                            key={id}
                                            type="hidden"
                                            name="product_ids[]"
                                            value={id}
                                        />
                                    ))}
                                {editingRule &&
                                    scopeMode === 'specific' &&
                                    itemType === 'service' && (
                                        <input
                                            type="hidden"
                                            name="service_id"
                                            value={selectedServiceIds[0] ?? ''}
                                        />
                                    )}
                                {editingRule &&
                                    scopeMode === 'specific' &&
                                    itemType === 'product' && (
                                        <input
                                            type="hidden"
                                            name="product_id"
                                            value={selectedProductIds[0] ?? ''}
                                        />
                                    )}
                                {itemType !== 'all' &&
                                    scopeMode === 'category' && (
                                        <p className="mt-3 flex gap-2 rounded-xl border border-primary/20 bg-primary/5 px-3 py-2.5 text-xs leading-5 text-muted-foreground">
                                            <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                                            O backend aceita uma categoria por
                                            regra. Para cobrir mais categorias,
                                            crie uma regra para cada categoria.
                                        </p>
                                    )}
                            </section>
                            <section className="rounded-2xl border bg-card p-5 shadow-sm sm:p-6">
                                <Step
                                    number="04"
                                    title="Defina o repasse"
                                    description="Para produtos, o valor fixo representa cada unidade vendida."
                                />
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <FormField
                                        label="Tipo de comissão"
                                        error={errors.type}
                                    >
                                        <select
                                            name="type"
                                            value={rateType}
                                            onChange={(event) =>
                                                setRateType(
                                                    event.target.value as
                                                        'percentage' | 'fixed',
                                                )
                                            }
                                            className="h-11 w-full rounded-lg border border-input bg-background px-3 text-sm text-foreground outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        >
                                            <option value="percentage">
                                                Percentual (%)
                                            </option>
                                            <option value="fixed">
                                                Valor fixo (centavos)
                                            </option>
                                        </select>
                                    </FormField>
                                    <FormField
                                        label={
                                            rateType === 'percentage'
                                                ? 'Percentual'
                                                : 'Valor em centavos'
                                        }
                                        description={
                                            rateType === 'percentage'
                                                ? 'Ex: 10 para 10%'
                                                : 'Ex: 1500 para R$ 15,00'
                                        }
                                        error={errors.value_rate}
                                    >
                                        <Input
                                            type="number"
                                            name="value_rate"
                                            min="0"
                                            max={
                                                rateType === 'percentage'
                                                    ? 100
                                                    : undefined
                                            }
                                            value={rate}
                                            onChange={(event) =>
                                                setRate(event.target.value)
                                            }
                                            required
                                        />
                                    </FormField>
                                </div>
                                <label className="mt-5 flex cursor-pointer items-start gap-3 rounded-xl border border-border p-4">
                                    <input
                                        type="checkbox"
                                        id="is_active"
                                        name="is_active"
                                        value="1"
                                        checked={isActive}
                                        onChange={(event) =>
                                            setIsActive(event.target.checked)
                                        }
                                        className="mt-0.5 h-4 w-4 accent-primary"
                                    />
                                    <span>
                                        <span className="block text-sm font-semibold text-foreground">
                                            Regra ativa
                                        </span>
                                        <span className="mt-1 block text-xs text-muted-foreground">
                                            Começar a apurar comissões assim que
                                            a regra for salva.
                                        </span>
                                    </span>
                                </label>
                            </section>
                            {hasConflict && (
                                <p className="flex gap-2 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm leading-5 text-amber-900 dark:border-amber-800 dark:bg-amber-950/20 dark:text-amber-300">
                                    <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                                    Já existe uma regra igual para este
                                    profissional e alcance.
                                </p>
                            )}
                            <FormActions
                                onCancel={onCancel}
                                submitLabel={
                                    editingRule
                                        ? 'Atualizar regra'
                                        : 'Salvar regra'
                                }
                                isSubmitting={processing || isInvalid}
                            />
                        </>
                    )}
                </Form>
                <CommissionRuleSummary
                    professionalName={professionalName}
                    itemType={itemType}
                    scopeMode={scopeMode}
                    categoryNames={selectedCategoryNames}
                    selectedCount={selectedCount}
                    rateType={rateType}
                    rate={rate}
                    isActive={isActive}
                />
            </div>
        </div>
    );
}

function Step({
    number,
    title,
    description,
}: {
    number: string;
    title: string;
    description: string;
}) {
    return (
        <div className="mb-5 flex items-center gap-3">
            <span className="flex h-8 w-8 items-center justify-center rounded-full bg-primary/12 text-sm font-bold text-primary">
                {number}
            </span>
            <div>
                <h2 className="font-semibold text-foreground">{title}</h2>
                <p className="text-sm text-muted-foreground">{description}</p>
            </div>
        </div>
    );
}

function CategoryList({
    title,
    categories,
    selectedId,
    onSelect,
    emptyLabel,
}: {
    title: string;
    categories: CommissionCategoryOption[];
    selectedId: string;
    onSelect: (id: string) => void;
    emptyLabel: string;
}) {
    return (
        <div className="mt-4 rounded-2xl border border-border/80 bg-muted/20 p-4">
            <div className="mb-3 flex items-center justify-between gap-3">
                <div>
                    <p className="text-sm font-semibold text-foreground">
                        {title}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        Escolha uma categoria por regra.
                    </p>
                </div>
                <Users className="h-4 w-4 text-primary" />
            </div>
            {categories.length === 0 ? (
                <p className="rounded-xl border border-dashed border-border px-3 py-4 text-xs leading-5 text-muted-foreground">
                    {emptyLabel}
                </p>
            ) : (
                <div className="grid gap-2 sm:grid-cols-2">
                    {categories.map((category) => (
                        <label
                            key={category.id}
                            className={`flex cursor-pointer items-center gap-3 rounded-xl border px-3 py-2.5 text-sm transition-colors ${selectedId === category.id ? 'border-primary/50 bg-primary/8' : 'border-transparent bg-background/50 hover:border-border'}`}
                        >
                            <input
                                type="radio"
                                name="category_picker"
                                checked={selectedId === category.id}
                                onChange={() => onSelect(category.id)}
                                className="h-4 w-4 accent-primary"
                            />
                            <span className="min-w-0 flex-1 truncate font-medium text-foreground">
                                {category.name}
                            </span>
                            {category.items_count !== undefined && (
                                <span className="text-xs text-muted-foreground">
                                    {category.items_count}
                                </span>
                            )}
                        </label>
                    ))}
                </div>
            )}
        </div>
    );
}
