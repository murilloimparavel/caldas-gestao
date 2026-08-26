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

export function QuickCreateCustomerModal({
    open,
    onOpenChange,
    onSuccess,
}: QuickCreateModalProps) {
    const [mutationKey, setMutationKey] = useState(() =>
        createIdempotencyKey('customer-quick-create'),
    );
    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [phone, setPhone] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const handleClose = () => {
        setName('');
        setEmail('');
        setPhone('');
        setErrors({});
        onOpenChange(false);
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        try {
            const response = await fetch(customers.store.url(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Idempotency-Key': mutationKey,
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as HTMLMetaElement
                        )?.content || '',
                },
                body: JSON.stringify({
                    name,
                    email: email || null,
                    phone: phone || null,
                }),
            });

            const data = await response.json();

            if (!response.ok) {
                if (data.errors) {
                    setErrors(data.errors);
                } else {
                    setErrors({
                        name: data.message || 'Erro ao cadastrar cliente.',
                    });
                }

                setProcessing(false);

                return;
            }

            const createdCustomer: CreatedEntity = {
                id: data.id || data.customer?.id,
                name: data.name || data.customer?.name,
                phone: data.phone || data.customer?.phone,
            };

            setProcessing(false);
            handleClose();
            setMutationKey(createIdempotencyKey('customer-quick-create'));
            onSuccess(createdCustomer);
        } catch {
            setErrors({ name: 'Erro de conexão ao cadastrar cliente.' });
            setProcessing(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Novo Cliente</DialogTitle>
                    <DialogDescription>
                        Cadastre um cliente rapidamente para selecionar no agendamento.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <FormErrorSummary errors={errors} />
                    <FormField label="Nome" name="name" error={errors.name} required>
                        <Input
                            id="quick_customer_name"
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            placeholder="Nome completo do cliente"
                            required
                        />
                    </FormField>

                    <FormField label="E-mail" name="email" error={errors.email}>
                        <Input
                            id="quick_customer_email"
                            type="email"
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                            placeholder="cliente@exemplo.com"
                        />
                    </FormField>

                    <FormField label="Telefone / WhatsApp" name="phone" error={errors.phone}>
                        <Input
                            id="quick_customer_phone"
                            value={phone}
                            onChange={(e) => setPhone(e.target.value)}
                            placeholder="(11) 99999-9999"
                        />
                    </FormField>

                    <FormActions
                        processing={processing}
                        onCancel={handleClose}
                        submitLabel="Cadastrar e Selecionar"
                    />
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function QuickCreateServiceModal({
    open,
    onOpenChange,
    onSuccess,
}: QuickCreateModalProps) {
    const [mutationKey, setMutationKey] = useState(() =>
        createIdempotencyKey('service-quick-create'),
    );
    const [name, setName] = useState('');
    const [priceFormatted, setPriceFormatted] = useState('');
    const [durationMinutes, setDurationMinutes] = useState('30');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const handleClose = () => {
        setName('');
        setPriceFormatted('');
        setDurationMinutes('30');
        setErrors({});
        onOpenChange(false);
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        const priceCents = parseBrazilianCurrency(priceFormatted);
        const duration = Number.parseInt(durationMinutes, 10) || 30;

        try {
            const response = await fetch(services.store.url(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Idempotency-Key': mutationKey,
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as HTMLMetaElement
                        )?.content || '',
                },
                body: JSON.stringify({
                    name,
                    price_cents: priceCents,
                    duration_minutes: duration,
                }),
            });

            const data = await response.json();

            if (!response.ok) {
                if (data.errors) {
                    setErrors(data.errors);
                } else {
                    setErrors({
                        name: data.message || 'Erro ao cadastrar serviço.',
                    });
                }

                setProcessing(false);

                return;
            }

            const createdService: CreatedEntity = {
                id: data.id || data.service?.id,
                name: data.name || data.service?.name,
                duration_minutes: duration,
                price_cents: priceCents,
            };

            setProcessing(false);
            handleClose();
            setMutationKey(createIdempotencyKey('service-quick-create'));
            onSuccess(createdService);
        } catch {
            setErrors({ name: 'Erro de conexão ao cadastrar serviço.' });
            setProcessing(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Novo Serviço</DialogTitle>
                    <DialogDescription>
                        Cadastre um novo serviço rapidamente para vincular ao agendamento.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <FormErrorSummary errors={errors} />
                    <FormField label="Nome do Serviço" name="name" error={errors.name} required>
                        <Input
                            id="quick_service_name"
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            placeholder="Ex.: Corte de Cabelo"
                            required
                        />
                    </FormField>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField label="Preço (R$)" name="price_cents" error={errors.price_cents} required>
                            <Input
                                id="quick_service_price"
                                value={priceFormatted}
                                onChange={(e) => setPriceFormatted(e.target.value)}
                                placeholder="50,00"
                                required
                            />
                        </FormField>

                        <FormField label="Duração (minutos)" name="duration_minutes" error={errors.duration_minutes} required>
                            <Input
                                id="quick_service_duration"
                                type="number"
                                min={1}
                                max={1440}
                                value={durationMinutes}
                                onChange={(e) => setDurationMinutes(e.target.value)}
                                placeholder="30"
                                required
                            />
                        </FormField>
                    </div>

                    <FormActions
                        processing={processing}
                        onCancel={handleClose}
                        submitLabel="Cadastrar e Selecionar"
                    />
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function QuickCreateProfessionalModal({
    open,
    onOpenChange,
    onSuccess,
}: QuickCreateModalProps) {
    const [mutationKey, setMutationKey] = useState(() =>
        createIdempotencyKey('professional-quick-create'),
    );
    const [name, setName] = useState('');
    const [phone, setPhone] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const handleClose = () => {
        setName('');
        setPhone('');
        setErrors({});
        onOpenChange(false);
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        try {
            const response = await fetch(professionals.store.url(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Idempotency-Key': mutationKey,
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as HTMLMetaElement
                        )?.content || '',
                },
                body: JSON.stringify({
                    name,
                    phone: phone || null,
                }),
            });

            const data = await response.json();

            if (!response.ok) {
                if (data.errors) {
                    setErrors(data.errors);
                } else {
                    setErrors({
                        name: data.message || 'Erro ao cadastrar profissional.',
                    });
                }

                setProcessing(false);

                return;
            }

            const createdProfessional: CreatedEntity = {
                id: data.id || data.professional?.id,
                name: data.name || data.professional?.name,
                phone: data.phone || data.professional?.phone,
            };

            setProcessing(false);
            handleClose();
            setMutationKey(createIdempotencyKey('professional-quick-create'));
            onSuccess(createdProfessional);
        } catch {
            setErrors({ name: 'Erro de conexão ao cadastrar profissional.' });
            setProcessing(false);
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
                    <FormErrorSummary errors={errors} />
                    <FormField label="Nome do Profissional" name="name" error={errors.name} required>
                        <Input
                            id="quick_professional_name"
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            placeholder="Nome completo do profissional"
                            required
                        />
                    </FormField>

                    <FormField label="Telefone / Contato" name="phone" error={errors.phone}>
                        <Input
                            id="quick_professional_phone"
                            value={phone}
                            onChange={(e) => setPhone(e.target.value)}
                            placeholder="(11) 99999-9999"
                        />
                    </FormField>

                    <FormActions
                        processing={processing}
                        onCancel={handleClose}
                        submitLabel="Cadastrar e Selecionar"
                    />
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function QuickCreateSupplierModal({
    open,
    onOpenChange,
    onSuccess,
}: QuickCreateModalProps) {
    const [mutationKey, setMutationKey] = useState(() =>
        createIdempotencyKey('supplier-quick-create'),
    );
    const [name, setName] = useState('');
    const [documentNumber, setDocumentNumber] = useState('');
    const [phone, setPhone] = useState('');
    const [email, setEmail] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const handleClose = () => {
        setName('');
        setDocumentNumber('');
        setPhone('');
        setEmail('');
        setErrors({});
        onOpenChange(false);
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        try {
            const response = await fetch('/suppliers', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Idempotency-Key': mutationKey,
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as HTMLMetaElement
                        )?.content || '',
                },
                body: JSON.stringify({
                    name,
                    document_number: documentNumber || null,
                    phone: phone || null,
                    email: email || null,
                }),
            });

            const data = await response.json();

            if (!response.ok) {
                if (data.errors) {
                    setErrors(data.errors);
                } else {
                    setErrors({
                        name: data.message || 'Erro ao cadastrar fornecedor.',
                    });
                }

                setProcessing(false);

                return;
            }

            const createdSupplier: CreatedEntity = {
                id: data.id || data.supplier?.id,
                name: data.name || data.supplier?.name,
                phone: data.phone || data.supplier?.phone,
            };

            setProcessing(false);
            handleClose();
            setMutationKey(createIdempotencyKey('supplier-quick-create'));
            onSuccess(createdSupplier);
        } catch {
            setErrors({ name: 'Erro de conexão ao cadastrar fornecedor.' });
            setProcessing(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Novo Fornecedor</DialogTitle>
                    <DialogDescription>
                        Cadastre um fornecedor rapidamente para vincular ao lançamento.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <FormErrorSummary errors={errors} />
                    <FormField label="Razão Social / Empresa" name="name" error={errors.name} required>
                        <Input
                            id="quick_supplier_name"
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            placeholder="Nome da empresa ou fornecedor"
                            required
                        />
                    </FormField>

                    <FormField label="CNPJ / CPF" name="document_number" error={errors.document_number}>
                        <Input
                            id="quick_supplier_document"
                            value={documentNumber}
                            onChange={(e) => setDocumentNumber(e.target.value)}
                            placeholder="00.000.000/0000-00"
                        />
                    </FormField>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField label="Telefone / Contato" name="phone" error={errors.phone}>
                            <Input
                                id="quick_supplier_phone"
                                value={phone}
                                onChange={(e) => setPhone(e.target.value)}
                                placeholder="(11) 99999-9999"
                            />
                        </FormField>

                        <FormField label="E-mail" name="email" error={errors.email}>
                            <Input
                                id="quick_supplier_email"
                                type="email"
                                value={email}
                                onChange={(e) => setEmail(e.target.value)}
                                placeholder="fornecedor@exemplo.com"
                            />
                        </FormField>
                    </div>

                    <FormActions
                        processing={processing}
                        onCancel={handleClose}
                        submitLabel="Cadastrar e Selecionar"
                    />
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function QuickCreateCategoryModal({
    open,
    onOpenChange,
    onSuccess,
}: QuickCreateModalProps) {
    const [mutationKey, setMutationKey] = useState(() =>
        createIdempotencyKey('category-quick-create'),
    );
    const [name, setName] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const handleClose = () => {
        setName('');
        setErrors({});
        onOpenChange(false);
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        try {
            const response = await fetch('/categories', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Idempotency-Key': mutationKey,
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as HTMLMetaElement
                        )?.content || '',
                },
                body: JSON.stringify({
                    name,
                }),
            });

            const data = await response.json();

            if (!response.ok) {
                if (data.errors) {
                    setErrors(data.errors);
                } else {
                    setErrors({
                        name: data.message || 'Erro ao cadastrar categoria.',
                    });
                }

                setProcessing(false);

                return;
            }

            const createdCategory: CreatedEntity = {
                id: data.id || data.category?.id,
                name: data.name || data.category?.name,
            };

            setProcessing(false);
            handleClose();
            setMutationKey(createIdempotencyKey('category-quick-create'));
            onSuccess(createdCategory);
        } catch {
            setErrors({ name: 'Erro de conexão ao cadastrar categoria.' });
            setProcessing(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Nova Categoria</DialogTitle>
                    <DialogDescription>
                        Cadastre uma nova categoria para organizar itens e lançamentos.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <FormErrorSummary errors={errors} />
                    <FormField label="Nome da Categoria" name="name" error={errors.name} required>
                        <Input
                            id="quick_category_name"
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            placeholder="Ex.: Produtos de Cabelo, Bebidas, etc."
                            required
                        />
                    </FormField>

                    <FormActions
                        processing={processing}
                        onCancel={handleClose}
                        submitLabel="Cadastrar e Selecionar"
                    />
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function QuickCreateProductModal({
    open,
    onOpenChange,
    onSuccess,
}: QuickCreateModalProps) {
    const [mutationKey, setMutationKey] = useState(() =>
        createIdempotencyKey('product-quick-create'),
    );
    const [name, setName] = useState('');
    const [priceFormatted, setPriceFormatted] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const handleClose = () => {
        setName('');
        setPriceFormatted('');
        setErrors({});
        onOpenChange(false);
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        const priceCents = parseBrazilianCurrency(priceFormatted);

        try {
            const response = await fetch(products.store.url(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Idempotency-Key': mutationKey,
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as HTMLMetaElement
                        )?.content || '',
                },
                body: JSON.stringify({
                    name,
                    sale_price_cents: priceCents,
                    unit_of_measure: 'un',
                }),
            });

            const data = await response.json();

            if (!response.ok) {
                if (data.errors) {
                    setErrors(data.errors);
                } else {
                    setErrors({
                        name: data.message || 'Erro ao cadastrar produto.',
                    });
                }

                setProcessing(false);

                return;
            }

            const createdProduct: CreatedEntity = {
                id: data.id || data.product?.id,
                name: data.name || data.product?.name,
                price_cents: priceCents,
                current_stock: data.current_stock ?? data.product?.current_stock ?? 0,
            };

            setProcessing(false);
            handleClose();
            setMutationKey(createIdempotencyKey('product-quick-create'));
            onSuccess(createdProduct);
        } catch {
            setErrors({ name: 'Erro de conexão ao cadastrar produto.' });
            setProcessing(false);
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
                    <FormErrorSummary errors={errors} />
                    <FormField label="Nome do Produto" name="name" error={errors.name} required>
                        <Input
                            id="quick_product_name"
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            placeholder="Ex.: Pomada Modeladora"
                            required
                        />
                    </FormField>

                    <FormField label="Preço de Venda (R$)" name="sale_price_cents" error={errors.sale_price_cents} required>
                        <Input
                            id="quick_product_price"
                            value={priceFormatted}
                            onChange={(e) => setPriceFormatted(e.target.value)}
                            placeholder="35,00"
                            required
                        />
                    </FormField>

                    <FormActions
                        processing={processing}
                        onCancel={handleClose}
                        submitLabel="Cadastrar e Selecionar"
                    />
                </form>
            </DialogContent>
        </Dialog>
    );
}

