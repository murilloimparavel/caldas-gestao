export type SaleStatus =
    | 'draft'
    | 'open'
    | 'ready_to_bill'
    | 'finalized'
    | 'cancelled';

export type SaleCategoryType = 'service' | 'product' | 'mixed';
export type UniquenessScope = 'customer' | 'appointment' | 'reference' | 'none';

export type SaleCategoryOption = {
    id: string;
    name: string;
    type: SaleCategoryType;
    uniqueness_scope: UniquenessScope;
    is_active?: boolean;
    key?: string;
};

export type CustomerOption = {
    id: string;
    name: string;
    phone?: string | null;
};

export type ServiceOption = {
    id: string;
    name: string;
    price_cents: number;
    duration_minutes: number;
};

export type ProductOption = {
    id: string;
    name: string;
    price_cents: number;
    sale_price_cents?: number;
    current_stock: number;
};

export type ProfessionalOption = {
    id: string;
    name: string;
};

export type SaleItem = {
    id: string;
    tenant_id: string;
    unit_id: string;
    sale_id: string;
    item_type: 'service' | 'product' | 'custom';
    service_id: string | null;
    product_id: string | null;
    professional_id: string | null;
    name_snapshot: string;
    unit_price_cents: number;
    quantity: number;
    discount_cents: number;
    total_cents: number;
    service?: { id: string; name: string; duration_minutes?: number } | null;
    product?: { id: string; name: string } | null;
    professional?: { id: string; name: string } | null;
};

export type SaleStatusHistory = {
    id: string;
    sale_id: string;
    from_status: string | null;
    to_status: string;
    user_id: string | null;
    reason: string | null;
    created_at: string;
    user?: { id: string; name: string } | null;
};

export type AppointmentSaleLinkSummary = {
    id: string;
    appointment_id: string;
    appointment?: {
        id: string;
        starts_at: string;
        ends_at: string;
        status: string;
    } | null;
};

export type Sale = {
    id: string;
    tenant_id: string;
    unit_id: string;
    customer_id: string | null;
    sale_category_id: string;
    category_key_snapshot: string | null;
    category_name_snapshot: string | null;
    reference_label: string | null;
    open_context_key: string | null;
    status: SaleStatus;
    currency: string;
    total_amount_cents: number;
    discount_amount_cents: number;
    final_amount_cents: number;
    notes: string | null;
    lock_version: number;
    created_at: string;
    updated_at: string;
    customer?: CustomerOption | null;
    category?: SaleCategoryOption | null;
    appointment_link?: AppointmentSaleLinkSummary | null;
    items?: SaleItem[];
    status_histories?: SaleStatusHistory[];
};

export type SaleMetrics = {
    open_count: number;
    ready_count: number;
    today_total_cents: number;
};
