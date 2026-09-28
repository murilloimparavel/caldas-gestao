import { Form, Head } from '@inertiajs/react';
import { AdminPageHeader } from '@/features/admin/components/admin-page-header';
import { adminRoutes } from '@/features/admin/types';
import {
    FormActions,
    FormErrorSummary,
    FormField,
    PageCanvas,
} from '@/components/operational';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Props = {
    plans?: Array<{ id: string; name: string; priceCents: number }>;
};

export default function PlatformClientCreate({ plans = [] }: Props) {
    return (
        <>
            <Head title="Novo cliente" />
            <PageCanvas className="gap-8">
                <AdminPageHeader
                    title="Criar novo cliente"
                    description="Cadastre a conta, escolha o plano inicial e defina uma senha temporária para o responsável."
                    backHref={adminRoutes.clients}
                />
                <div className="mx-auto w-full max-w-3xl">
                    <Form
                        action={adminRoutes.clients}
                        method="post"
                        className="space-y-6"
                        resetOnSuccess
                    >
                        {({ errors, processing }) => (
                            <>
                                <FormErrorSummary errors={errors} />
                                <section className="surface-panel space-y-5 p-6">
                                    <div>
                                        <h2 className="font-semibold">
                                            Dados da empresa
                                        </h2>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            Essas informações aparecem no
                                            cadastro da conta.
                                        </p>
                                    </div>
                                    <FormField
                                        name="name"
                                        label="Nome da empresa"
                                        required
                                        error={errors.name}
                                    >
                                        <Input
                                            name="name"
                                            placeholder="Nome da empresa"
                                            required
                                        />
                                    </FormField>
                                    <div className="grid gap-5 sm:grid-cols-2">
                                        <FormField
                                            name="owner_name"
                                            label="Nome do responsável"
                                            required
                                            error={errors.owner_name}
                                        >
                                            <Input
                                                name="owner_name"
                                                placeholder="Nome completo"
                                                required
                                            />
                                        </FormField>
                                        <FormField
                                            name="owner_email"
                                            label="E-mail do responsável"
                                            required
                                            error={errors.owner_email}
                                        >
                                            <Input
                                                type="email"
                                                name="owner_email"
                                                placeholder="responsavel@empresa.com"
                                                required
                                            />
                                        </FormField>
                                    </div>
                                </section>
                                <section className="surface-panel space-y-5 p-6">
                                    <div>
                                        <h2 className="font-semibold">
                                            Acesso e plano
                                        </h2>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            Você definirá uma senha temporária e
                                            deverá compartilhá-la com o
                                            responsável por um canal seguro.
                                        </p>
                                    </div>
                                    <div className="grid gap-5 sm:grid-cols-2">
                                        <FormField
                                            name="owner_password"
                                            label="Senha temporária"
                                            required
                                            error={errors.owner_password}
                                        >
                                            <Input
                                                type="password"
                                                name="owner_password"
                                                minLength={12}
                                                required
                                                autoComplete="new-password"
                                            />
                                        </FormField>
                                    </div>
                                    <FormField
                                        name="plan_id"
                                        label="Plano inicial"
                                        error={errors.plan_id}
                                    >
                                        <Select name="plan_id">
                                            <SelectTrigger>
                                                <SelectValue
                                                    placeholder={
                                                        plans.length > 0
                                                            ? 'Selecione um plano'
                                                            : 'Nenhum plano disponível'
                                                    }
                                                />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {plans.map((plan) => (
                                                    <SelectItem
                                                        key={plan.id}
                                                        value={plan.id}
                                                    >
                                                        {plan.name} ·{' '}
                                                        {(
                                                            plan.priceCents /
                                                            100
                                                        ).toLocaleString(
                                                            'pt-BR',
                                                            {
                                                                style: 'currency',
                                                                currency: 'BRL',
                                                            },
                                                        )}
                                                        /mês
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </FormField>
                                </section>
                                <FormActions
                                    processing={processing}
                                    label="Criar cliente"
                                />
                            </>
                        )}
                    </Form>
                </div>
            </PageCanvas>
        </>
    );
}

PlatformClientCreate.layout = {
    breadcrumbs: [
        { title: 'Administração', href: adminRoutes.dashboard },
        { title: 'Clientes', href: adminRoutes.clients },
        { title: 'Novo cliente', href: adminRoutes.createClient },
    ],
};
