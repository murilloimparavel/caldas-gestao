import { useHttp } from '@inertiajs/react';
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
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import customers from '@/routes/customers';
import products from '@/routes/products';
import professionals from '@/routes/professionals';
import services from '@/routes/services';

export type CreatedEntity = {
    id: string;
    name: string;
    phone?: string | null;
    duration_minutes?: number;
    price_cents?: number;
    current_stock?: number;
};

interface QuickCreateModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onSuccess: (created: CreatedEntity) => void;
}

type CustomerFormData = {
    name: string;
    email: string;
    phone: string;
};

export function QuickCreateCustomerModal({
    open,
    onOpenChange,
    onSuccess,
}: QuickCreateModalProps) {
    const [mutationKey, setMutationKey] = useState(() =>
        createIdempotencyKey('customer-quick-create'),
    );
    const form = useHttp<CustomerFormData>({
        name: '',
        email: '',
        phone: '',
    });

    const handleClose = () => {
        form.reset();
        form.clearErrors();
        onOpenChange(false);
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        form.clearErrors();
        form.transform((data) => ({
            name: data.name,
            email: data.email || null,
            phone: data.phone || null,
        }));

        try {
            await form.post(customers.store.url(), {
                headers: {
                    'X-Idempotency-Key': mutationKey,
                },
                onSuccess: (responseData: any) => {
                    const createdCustomer: CreatedEntity = {
                        id: responseData?.id || responseData?.customer?.id,
                        name: responseData?.name || responseData?.customer?.name,
                        phone: responseData?.phone || responseData?.customer?.phone,
                    };

                    handleClose();
                    setMutationKey(createIdempotencyKey('customer-quick-create'));
                    onSuccess(createdCustomer);
                },
            });
        } catch {
            form.setError('name', 'Erro de conexão ao cadastrar cliente.');
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Novo Cliente</DialogTitle>
                    <DialogDescription>
                        Cadastre um cliente rapidamente para selecionar no
                        agendamento.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <FormErrorSummary errors={form.errors} />
                    <FormField
                        label="Nome"
                        name="name"
                        error={form.errors.name}
                        required
                    >
                        <Input
                            id="quick_customer_name"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            placeholder="Nome completo do cliente"
                            required
                        />
                    </FormField>

                    <FormField label="E-mail" name="email" error={form.errors.email}>
                        <Input
                            id="quick_customer_email"
                            type="email"
                            value={form.data.email}
                            onChange={(e) => form.setData('email', e.target.value)}
                            placeholder="cliente@exemplo.com"
                        />
                    </FormField>

                    <FormField
                        label="Telefone / WhatsApp"
                        name="phone"
                        error={form.errors.phone}
                    >
                        <Input
                            id="quick_customer_phone"
                            value={form.data.phone}
                            onChange={(e) => form.setData('phone', e.target.value)}
                            placeholder="(11) 99999-9999"
                        />
                    </FormField>

                    <FormActions
                        processing={form.processing}
                        onCancel={handleClose}
                        submitLabel="Cadastrar e Selecionar"
                    />
                </form>
            </DialogContent>
        </Dialog>
    );
}

type ServiceFormData = {
    name: string;
    priceFormatted: string;
    durationMinutes: string;
    price_cents?: number;
    duration_minutes?: number;
};

export function QuickCreateServiceModal({
    open,
    onOpenChange,
    onSuccess,
}: QuickCreateModalProps) {
    const [mutationKey, setMutationKey] = useState(() =>
        createIdempotencyKey('service-quick-create'),
    );
    const form = useHttp<ServiceFormData>({
        name: '',
        priceFormatted: '',
        durationMinutes: '30',
    });

    const handleClose = () => {
        form.reset();
        form.clearErrors();
        onOpenChange(false);
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        form.clearErrors();

        const priceCents = parseBrazilianCurrency(form.data.priceFormatted);
        const duration = Number.parseInt(form.data.durationMinutes, 10) || 30;

        form.transform((data) => ({
            name: data.name,
            price_cents: priceCents,
            duration_minutes: duration,
        }));

        try {
            await form.post(services.store.url(), {
                headers: {
                    'X-Idempotency-Key': mutationKey,
                },
                onSuccess: (responseData: any) => {
                    const createdService: CreatedEntity = {
                        id: responseData?.id || responseData?.service?.id,
                        name: responseData?.name || responseData?.service?.name,
                        duration_minutes: duration,
                        price_cents: priceCents,
                    };

                    handleClose();
                    setMutationKey(createIdempotencyKey('service-quick-create'));
                    onSuccess(createdService);
                },
            });
        } catch {
            form.setError('name', 'Erro de conexão ao cadastrar serviço.');
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Novo Serviço</DialogTitle>
                    <DialogDescription>
                        Cadastre um novo serviço rapidamente para vincular ao
                        agendamento.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <FormErrorSummary errors={form.errors} />
                    <FormField
                        label="Nome do Serviço"
                        name="name"
                        error={form.errors.name}
                        required
                    >
                        <Input
                            id="quick_service_name"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            placeholder="Ex.: Corte de Cabelo"
                            required
                        />
                    </FormField>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            label="Preço (R$)"
                            name="price_cents"
                            error={form.errors.price_cents}
                            required
                        >
                            <Input
                                id="quick_service_price"
                                value={form.data.priceFormatted}
                                onChange={(e) =>
                                    form.setData('priceFormatted', e.target.value)
                                }
                                placeholder="50,00"
                                required
                            />
                        </FormField>

                        <FormField
                            label="Duração (minutos)"
                            name="duration_minutes"
                            error={form.errors.duration_minutes}
                            required
                        >
                            <Input
                                id="quick_service_duration"
                                type="number"
                                min={1}
                                max={1440}
                                value={form.data.durationMinutes}
                                onChange={(e) =>
                                    form.setData('durationMinutes', e.target.value)
                                }
                                placeholder="30"
                                required
                            />
                        </FormField>
                    </div>

                    <FormActions
                        processing={form.processing}
                        onCancel={handleClose}
                        submitLabel="Cadastrar e Selecionar"
                    />
                </form>
            </DialogContent>
        </Dialog>
    );
}

type ProfessionalFormData = {
    name: string;
    phone: string;
};

export function QuickCreateProfessionalModal({
    open,
    onOpenChange,
    onSuccess,
}: QuickCreateModalProps) {
    const [mutationKey, setMutationKey] = useState(() =>
        createIdempotencyKey('professional-quick-create'),
    );
    const form = useHttp<ProfessionalFormData>({
        name: '',
        phone: '',
    });

    const handleClose = () => {
        form.reset();
        form.clearErrors();
        onOpenChange(false);
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        form.clearErrors();
        form.transform((data) => ({
            name: data.name,
            phone: data.phone || null,
        }));

        try {
            await form.post(professionals.store.url(), {
                headers: {
                    'X-Idempotency-Key': mutationKey,
                },
                onSuccess: (responseData: any) => {
                    const createdProfessional: CreatedEntity = {
                        id: responseData?.id || responseData?.professional?.id,
                        name: responseData?.name || responseData?.professional?.name,
                        phone: responseData?.phone || responseData?.professional?.phone,
                    };

                    handleClose();
                    setMutationKey(createIdempotencyKey('professional-quick-create'));
                    onSuccess(createdProfessional);
                },
            });
        } catch {
            form.setError('name', 'Erro de conexão ao cadastrar profissional.');
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Novo Profissional</DialogTitle>
                    <DialogDescription>
                        Cadastre um novo profissional para atender agendamentos.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <FormErrorSummary errors={form.errors} />
                    <FormField
                        label="Nome do Profissional"
                        name="name"
                        error={form.errors.name}
                        required
                    >
                        <Input
                            id="quick_professional_name"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            placeholder="Nome completo do profissional"
                            required
                        />
                    </FormField>

                    <FormField
                        label="Telefone / Contato"
                        name="phone"
                        error={form.errors.phone}
                    >
                        <Input
                            id="quick_professional_phone"
                            value={form.data.phone}
                            onChange={(e) => form.setData('phone', e.target.value)}
                            placeholder="(11) 99999-9999"
                        />
                    </FormField>

                    <FormActions
                        processing={form.processing}
                        onCancel={handleClose}
                        submitLabel="Cadastrar e Selecionar"
                    />
                </form>
            </DialogContent>
        </Dialog>
    );
}

type SupplierFormData = {
    name: string;
    documentNumber: string;
    phone: string;
    email: string;
    document_number?: string | null;
};

export function QuickCreateSupplierModal({
    open,
    onOpenChange,
    onSuccess,
}: QuickCreateModalProps) {
    const [mutationKey, setMutationKey] = useState(() =>
        createIdempotencyKey('supplier-quick-create'),
    );
    const form = useHttp<SupplierFormData>({
        name: '',
        documentNumber: '',
        phone: '',
        email: '',
    });

    const handleClose = () => {
        form.reset();
        form.clearErrors();
        onOpenChange(false);
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        form.clearErrors();
        form.transform((data) => ({
            name: data.name,
            document_number: data.documentNumber || null,
            phone: data.phone || null,
            email: data.email || null,
        }));

        try {
            await form.post('/suppliers', {
                headers: {
                    'X-Idempotency-Key': mutationKey,
                },
                onSuccess: (responseData: any) => {
                    const createdSupplier: CreatedEntity = {
                        id: responseData?.id || responseData?.supplier?.id,
                        name: responseData?.name || responseData?.supplier?.name,
                        phone: responseData?.phone || responseData?.supplier?.phone,
                    };

                    handleClose();
                    setMutationKey(createIdempotencyKey('supplier-quick-create'));
                    onSuccess(createdSupplier);
                },
            });
        } catch {
            form.setError('name', 'Erro de conexão ao cadastrar fornecedor.');
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Novo Fornecedor</DialogTitle>
                    <DialogDescription>
                        Cadastre um fornecedor rapidamente para vincular ao
                        lançamento.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <FormErrorSummary errors={form.errors} />
                    <FormField
                        label="Razão Social / Empresa"
                        name="name"
                        error={form.errors.name}
                        required
                    >
                        <Input
                            id="quick_supplier_name"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            placeholder="Nome da empresa ou fornecedor"
                            required
                        />
                    </FormField>

                    <FormField
                        label="CNPJ / CPF"
                        name="document_number"
                        error={form.errors.document_number}
                    >
                        <Input
                            id="quick_supplier_document"
                            value={form.data.documentNumber}
                            onChange={(e) =>
                                form.setData('documentNumber', e.target.value)
                            }
                            placeholder="00.000.000/0000-00"
                        />
                    </FormField>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            label="Telefone / Contato"
                            name="phone"
                            error={form.errors.phone}
                        >
                            <Input
                                id="quick_supplier_phone"
                                value={form.data.phone}
                                onChange={(e) =>
                                    form.setData('phone', e.target.value)
                                }
                                placeholder="(11) 99999-9999"
                            />
                        </FormField>

                        <FormField
                            label="E-mail"
                            name="email"
                            error={form.errors.email}
                        >
                            <Input
                                id="quick_supplier_email"
                                type="email"
                                value={form.data.email}
                                onChange={(e) =>
                                    form.setData('email', e.target.value)
                                }
                                placeholder="fornecedor@exemplo.com"
                            />
                        </FormField>
                    </div>

                    <FormActions
                        processing={form.processing}
                        onCancel={handleClose}
                        submitLabel="Cadastrar e Selecionar"
                    />
                </form>
            </DialogContent>
        </Dialog>
    );
}

type CategoryFormData = {
    name: string;
};

export function QuickCreateCategoryModal({
    open,
    onOpenChange,
    onSuccess,
}: QuickCreateModalProps) {
    const [mutationKey, setMutationKey] = useState(() =>
        createIdempotencyKey('category-quick-create'),
    );
    const form = useHttp<CategoryFormData>({
        name: '',
    });

    const handleClose = () => {
        form.reset();
        form.clearErrors();
        onOpenChange(false);
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        form.clearErrors();
        form.transform((data) => ({
            name: data.name,
        }));

        try {
            await form.post('/categories', {
                headers: {
                    'X-Idempotency-Key': mutationKey,
                },
                onSuccess: (responseData: any) => {
                    const createdCategory: CreatedEntity = {
                        id: responseData?.id || responseData?.category?.id,
                        name: responseData?.name || responseData?.category?.name,
                    };

                    handleClose();
                    setMutationKey(createIdempotencyKey('category-quick-create'));
                    onSuccess(createdCategory);
                },
            });
        } catch {
            form.setError('name', 'Erro de conexão ao cadastrar categoria.');
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Nova Categoria</DialogTitle>
                    <DialogDescription>
                        Cadastre uma nova categoria para organizar itens e
                        lançamentos.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <FormErrorSummary errors={form.errors} />
                    <FormField
                        label="Nome da Categoria"
                        name="name"
                        error={form.errors.name}
                        required
                    >
                        <Input
                            id="quick_category_name"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            placeholder="Ex.: Produtos de Cabelo, Bebidas, etc."
                            required
                        />
                    </FormField>

                    <FormActions
                        processing={form.processing}
                        onCancel={handleClose}
                        submitLabel="Cadastrar e Selecionar"
                    />
                </form>
            </DialogContent>
        </Dialog>
    );
}

type ProductFormData = {
    name: string;
    priceFormatted: string;
    sale_price_cents?: number;
    unit_of_measure?: string;
};

export function QuickCreateProductModal({
    open,
    onOpenChange,
    onSuccess,
}: QuickCreateModalProps) {
    const [mutationKey, setMutationKey] = useState(() =>
        createIdempotencyKey('product-quick-create'),
    );
    const form = useHttp<ProductFormData>({
        name: '',
        priceFormatted: '',
    });

    const handleClose = () => {
        form.reset();
        form.clearErrors();
        onOpenChange(false);
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        form.clearErrors();
        const priceCents = parseBrazilianCurrency(form.data.priceFormatted);

        form.transform((data) => ({
            name: data.name,
            sale_price_cents: priceCents,
            unit_of_measure: 'un',
        }));

        try {
            await form.post(products.store.url(), {
                headers: {
                    'X-Idempotency-Key': mutationKey,
                },
                onSuccess: (responseData: any) => {
                    const createdProduct: CreatedEntity = {
                        id: responseData?.id || responseData?.product?.id,
                        name: responseData?.name || responseData?.product?.name,
                        price_cents: priceCents,
                        current_stock:
                            responseData?.current_stock ??
                            responseData?.product?.current_stock ??
                            0,
                    };

                    handleClose();
                    setMutationKey(createIdempotencyKey('product-quick-create'));
                    onSuccess(createdProduct);
                },
            });
        } catch {
            form.setError('name', 'Erro de conexão ao cadastrar produto.');
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Novo Produto</DialogTitle>
                    <DialogDescription>
                        Cadastre um produto rapidamente para incluir na comanda.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <FormErrorSummary errors={form.errors} />
                    <FormField
                        label="Nome do Produto"
                        name="name"
                        error={form.errors.name}
                        required
                    >
                        <Input
                            id="quick_product_name"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            placeholder="Ex.: Pomada Modeladora"
                            required
                        />
                    </FormField>

                    <FormField
                        label="Preço de Venda (R$)"
                        name="sale_price_cents"
                        error={form.errors.sale_price_cents}
                        required
                    >
                        <Input
                            id="quick_product_price"
                            value={form.data.priceFormatted}
                            onChange={(e) =>
                                form.setData('priceFormatted', e.target.value)
                            }
                            placeholder="35,00"
                            required
                        />
                    </FormField>

                    <FormActions
                        processing={form.processing}
                        onCancel={handleClose}
                        submitLabel="Cadastrar e Selecionar"
                    />
                </form>
            </DialogContent>
        </Dialog>
    );
}

// Aliases matching dialog naming conventions
export const QuickCustomerDialog = QuickCreateCustomerModal;
export const QuickServiceDialog = QuickCreateServiceModal;
export const QuickProfessionalDialog = QuickCreateProfessionalModal;
export const QuickProductDialog = QuickCreateProductModal;
export const QuickCategoryDialog = QuickCreateCategoryModal;
export const QuickSupplierDialog = QuickCreateSupplierModal;
