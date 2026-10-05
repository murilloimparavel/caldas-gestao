import { Form, Head } from '@inertiajs/react';
import { Plus, ShieldCheck, UserRound } from 'lucide-react';
import { useState } from 'react';
import { PageCanvas, ResourceHeader } from '@/components/operational';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import collaborators from '@/routes/settings/collaborators';

type Permission = { id: string; key: string; description: string };
type Role = {
    id: string;
    name: string;
    description: string | null;
    is_system: boolean;
    permission_ids: string[];
};
type Membership = {
    id: string;
    status: string;
    user: { name: string; email: string } | null;
    professional: { id: string; name: string } | null;
    roles: { id: string; name: string }[];
};
type Professional = { id: string; name: string; unit_id: string };

type Props = {
    memberships: Membership[];
    roles: Role[];
    permissions: Permission[];
    professionals: Professional[];
    canManage: boolean;
};

const moduleLabels: Record<string, string> = {
    calendar: 'Agenda',
    sale: 'Comandas',
    customer: 'Clientes',
    professional: 'Profissionais',
    financial: 'Financeiro',
    cash_shift: 'Caixa',
    service: 'Serviços',
    product: 'Produtos',
    inventory: 'Estoque',
};
const actionLabels: Record<string, string> = {
    view: 'Visualizar',
    manage: 'Gerenciar',
    create: 'Criar',
    update: 'Editar',
    delete: 'Excluir',
    close: 'Fechar',
    adjust: 'Ajustar',
    configure: 'Configurar',
};
function permissionLabel(key: string): string {
    const [module, action] = key.split('.');

    return `${moduleLabels[module] ?? module} · ${actionLabels[action] ?? action}`;
}

export default function Collaborators({
    memberships,
    roles,
    permissions,
    professionals,
    canManage,
}: Props) {
    const [showRole, setShowRole] = useState(false);
    const [selectedPermissions, setSelectedPermissions] = useState<string[]>(
        [],
    );

    return (
        <>
            <Head title="Colaboradores" />
            <PageCanvas>
                <ResourceHeader
                    eyebrow="Configurações"
                    title="Colaboradores"
                    description="Convide sua equipe, crie perfis de acesso e vincule cada colaborador a um profissional."
                    action={
                        canManage ? (
                            <Button onClick={() => setShowRole(!showRole)}>
                                <Plus /> Novo perfil
                            </Button>
                        ) : undefined
                    }
                />

                {showRole && (
                    <Card className="mb-6">
                        <CardHeader>
                            <CardTitle>Novo perfil de acesso</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Form
                                {...collaborators.roles.store.form()}
                                className="space-y-4"
                                onSuccess={() => setShowRole(false)}
                            >
                                <Input
                                    name="name"
                                    placeholder="Ex.: Barbeiro"
                                    required
                                />
                                <Input
                                    name="description"
                                    placeholder="Descrição (opcional)"
                                />
                                <div className="grid gap-2 sm:grid-cols-2">
                                    {permissions.map((permission) => (
                                        <label
                                            key={permission.id}
                                            className="flex items-center gap-2 rounded-md border p-2 text-sm"
                                        >
                                            <input
                                                type="checkbox"
                                                name="permission_ids[]"
                                                value={permission.id}
                                                checked={selectedPermissions.includes(
                                                    permission.id,
                                                )}
                                                onChange={(event) =>
                                                    setSelectedPermissions(
                                                        (current) =>
                                                            event.target.checked
                                                                ? [
                                                                      ...current,
                                                                      permission.id,
                                                                  ]
                                                                : current.filter(
                                                                      (id) =>
                                                                          id !==
                                                                          permission.id,
                                                                  ),
                                                    )
                                                }
                                            />
                                            {permissionLabel(permission.key)}
                                        </label>
                                    ))}
                                </div>
                                <Button type="submit">Salvar perfil</Button>
                            </Form>
                        </CardContent>
                    </Card>
                )}

                {canManage && (
                    <Card className="mb-6">
                        <CardHeader>
                            <CardTitle>Adicionar colaborador</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="mb-3 text-sm text-muted-foreground">
                                O colaborador precisa ter um usuário já
                                cadastrado e com e-mail verificado.
                            </p>
                            <Form
                                {...collaborators.store.form()}
                                className="flex flex-col gap-3 sm:flex-row"
                            >
                                <Input
                                    name="email"
                                    type="email"
                                    placeholder="E-mail de um usuário cadastrado"
                                    required
                                />
                                <Button type="submit">Adicionar</Button>
                            </Form>
                        </CardContent>
                    </Card>
                )}

                {canManage && roles.length > 0 && (
                    <Card className="mb-6">
                        <CardHeader>
                            <CardTitle>Perfis existentes</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {roles.map((role) => (
                                <Form
                                    key={role.id}
                                    {...collaborators.roles.update.form(
                                        role.id,
                                    )}
                                    className="space-y-3 rounded-lg border p-3"
                                >
                                    <div className="grid gap-2 sm:grid-cols-[1fr_1fr_auto]">
                                        <Input
                                            name="name"
                                            defaultValue={role.name}
                                            aria-label={`Nome do perfil ${role.name}`}
                                        />
                                        <Input
                                            name="description"
                                            defaultValue={
                                                role.description ?? ''
                                            }
                                            placeholder="Descrição"
                                            aria-label={`Descrição do perfil ${role.name}`}
                                        />
                                        <Button
                                            type="submit"
                                            size="sm"
                                            variant="outline"
                                        >
                                            Salvar perfil
                                        </Button>
                                    </div>
                                    <div className="grid gap-2 sm:grid-cols-2">
                                        {permissions.map((permission) => (
                                            <label
                                                key={permission.id}
                                                className="flex items-center gap-2 text-sm"
                                            >
                                                <input
                                                    type="checkbox"
                                                    name="permission_ids[]"
                                                    value={permission.id}
                                                    defaultChecked={role.permission_ids.includes(
                                                        permission.id,
                                                    )}
                                                />
                                                {permissionLabel(
                                                    permission.key,
                                                )}
                                            </label>
                                        ))}
                                    </div>
                                </Form>
                            ))}
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Equipe</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {memberships.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                Nenhum colaborador adicional foi adicionado
                                ainda.
                            </p>
                        )}
                        {memberships.map((membership) => (
                            <div
                                key={membership.id}
                                className="grid gap-3 rounded-lg border p-4 md:grid-cols-[1fr_auto_auto] md:items-center"
                            >
                                <div className="flex items-center gap-3">
                                    <UserRound className="text-muted-foreground" />
                                    <div>
                                        <p className="font-medium">
                                            {membership.user?.name ?? 'Usuário'}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {membership.user?.email}
                                        </p>
                                    </div>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    {membership.roles.map((role) => (
                                        <Badge
                                            key={role.id}
                                            variant="secondary"
                                        >
                                            {role.name}
                                        </Badge>
                                    ))}
                                    {membership.status !== 'active' && (
                                        <Badge variant="outline">
                                            {membership.status === 'invited'
                                                ? 'Convite pendente'
                                                : membership.status}
                                        </Badge>
                                    )}
                                </div>
                                {canManage && (
                                    <div className="flex flex-col gap-2 sm:flex-row">
                                        <Form
                                            {...collaborators.role.assign.form(
                                                membership.id,
                                            )}
                                        >
                                            <select
                                                name="role_id"
                                                className="h-9 rounded-md border bg-background px-2 text-sm"
                                                defaultValue=""
                                            >
                                                <option value="" disabled>
                                                    Perfil
                                                </option>
                                                {roles.map((role) => (
                                                    <option
                                                        key={role.id}
                                                        value={role.id}
                                                    >
                                                        {role.name}
                                                    </option>
                                                ))}
                                            </select>
                                            <input
                                                type="hidden"
                                                name="scope_kind"
                                                value="tenant"
                                            />
                                            <Button type="submit" size="sm">
                                                <ShieldCheck /> Atribuir
                                            </Button>
                                        </Form>
                                        <Form
                                            {...collaborators.professional.update.form(
                                                membership.id,
                                            )}
                                        >
                                            <select
                                                name="professional_id"
                                                className="h-9 rounded-md border bg-background px-2 text-sm"
                                                defaultValue={
                                                    membership.professional
                                                        ?.id ?? ''
                                                }
                                            >
                                                <option value="">
                                                    Sem profissional
                                                </option>
                                                {professionals.map(
                                                    (professional) => (
                                                        <option
                                                            key={
                                                                professional.id
                                                            }
                                                            value={
                                                                professional.id
                                                            }
                                                        >
                                                            {professional.name}
                                                        </option>
                                                    ),
                                                )}
                                            </select>
                                            <Button
                                                type="submit"
                                                size="sm"
                                                variant="outline"
                                            >
                                                Vincular
                                            </Button>
                                        </Form>
                                        <Form
                                            {...collaborators.revoke.form(
                                                membership.id,
                                            )}
                                            onSubmit={(event) => {
                                                if (
                                                    !window.confirm(
                                                        'Revogar o acesso deste colaborador? Ele perderá o acesso a este tenant.',
                                                    )
                                                ) {
                                                    event.preventDefault();
                                                }
                                            }}
                                        >
                                            <Button
                                                type="submit"
                                                size="sm"
                                                variant="destructive"
                                            >
                                                Revogar acesso
                                            </Button>
                                        </Form>
                                    </div>
                                )}
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </PageCanvas>
        </>
    );
}
