import type { CommissionRule, ProfessionalCommissionSummary } from '@/types';

export type CommissionItemType = 'all' | 'service' | 'product';
export type CommissionScopeMode = 'all' | 'category' | 'specific';

export type CommissionCategoryOption = {
    id: string;
    name: string;
    type?: 'service' | 'product' | 'general' | null;
    items_count?: number;
};

export type CommissionServiceOption = {
    id: string;
    name: string;
    price_cents: number;
    category_id?: string | null;
    category?: CommissionCategoryOption | null;
};

export type CommissionProductOption = {
    id: string;
    name: string;
    sale_price_cents: number;
    category_id?: string | null;
    category?: CommissionCategoryOption | null;
    unit_name?: string | null;
};

export type CommissionRuleBuilderProps = {
    professionals: ProfessionalCommissionSummary[];
    rules: CommissionRule[];
    services: CommissionServiceOption[];
    products: CommissionProductOption[];
    categories?: CommissionCategoryOption[];
    serviceCategories?: CommissionCategoryOption[];
    productCategories?: CommissionCategoryOption[];
    editingRule: CommissionRule | null;
    onCancel: () => void;
};
