import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import IosInstallGuide from '@/components/ios-install-guide';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { readIosInstallationRequired } from '@/lib/ios-installation';
import { notificationDeviceState } from '@/lib/notification-device-state';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    enablePushSubscription,
    endpointHash as hashEndpoint,
    pushRequest,
} from '@/lib/push-notifications';
import {
    pushDeviceOptOutKey,
    setPushDeviceOptedOut,
} from '@/lib/push-invitation';
import type { SharedPageProps } from '@/types';
import { edit, update } from '@/routes/notification-settings';
import { store, destroy, test } from '@/routes/push-subscriptions';

type Preferences = {
    new_appointments: boolean;
    changes: boolean;
    cancellations: boolean;
    reminders: boolean;
    reminder_minutes: number;
    quiet_start: string | null;
    quiet_end: string | null;
};
type Subscription = {
    id: string;
    device_name: string;
    endpoint_hash: string;
    created_at: string;
};
type Props = {
    preferences: Preferences;
    push: { available: boolean; publicKey: string | null };
    subscriptions: Subscription[];
};

export default function Notifications({
    preferences,
    push,
    subscriptions,
}: Props) {
    const { auth, workspace } = usePage<SharedPageProps>().props;
    const form = useForm(preferences);
    const [permission, setPermission] = useState<
        NotificationPermission | 'unsupported'
    >('default');
    const [endpoint, setEndpoint] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);
    const [message, setMessage] = useState('');
    const [installRequired, setInstallRequired] = useState(false);
    const [deviceReady, setDeviceReady] = useState(false);
    const [installGuideOpen, setInstallGuideOpen] = useState(false);
    useEffect(() => {
        const displayMode = window.matchMedia('(display-mode: standalone)');
        const refreshInstallation = () =>
            setInstallRequired(readIosInstallationRequired());
        refreshInstallation();
        displayMode.addEventListener('change', refreshInstallation);
        window.addEventListener('pageshow', refreshInstallation);
        const supported =
            window.isSecureContext &&
            'Notification' in window &&
            'serviceWorker' in navigator &&
            'PushManager' in window;
        const readDevice = async () => {
            const registration = supported
                ? await navigator.serviceWorker.getRegistration('/')
                : undefined;
            const subscription =
                await registration?.pushManager.getSubscription();
            setPermission(supported ? Notification.permission : 'unsupported');
            setInstallRequired(readIosInstallationRequired());
            setEndpoint(
                subscription ? await hashEndpoint(subscription.endpoint) : null,
            );
            setDeviceReady(true);
        };
        void readDevice().catch(() =>
            setMessage(
                'Não foi possível consultar este aparelho. Atualize a página.',
            ),
        );

        return () => {
            displayMode.removeEventListener('change', refreshInstallation);
            window.removeEventListener('pageshow', refreshInstallation);
        };
    }, []);
    const current = subscriptions.find(
        (subscription) => subscription.endpoint_hash === endpoint,
    );
    const deviceState = notificationDeviceState({
        ready: deviceReady,
        installRequired,
        permission,
        available: push.available,
        registered: !!current,
    });
    const deviceStatus = {
        loading: 'Consultando este aparelho…',
        install:
            'Primeiro, adicione o Caldas à Tela de Início. Depois, abra pelo novo ícone para ativar os avisos.',
        blocked:
            'Notificações bloqueadas. Libere a permissão nas configurações do navegador ou do iPhone e atualize esta página.',
        unsupported:
            'Este navegador não oferece notificações. Use um navegador compatível com uma conexão HTTPS.',
        unavailable:
            'O envio de notificações ainda não foi habilitado pela administração. Sua central continua disponível.',
        active: 'Notificações ativadas neste aparelho. Você pode receber avisos mesmo com o sistema fechado.',
        reconnect:
            'A permissão já foi concedida. Reconecte este aparelho para voltar a receber avisos nesta empresa.',
        activate:
            'Ative as notificações e permita o envio quando o navegador perguntar.',
    }[deviceState];
    async function enable() {
        setBusy(true);
        setMessage('');

        try {
            if (!push.publicKey) {
                setMessage('O envio de push ainda não está configurado.');

                return;
            }

            const result = await enablePushSubscription(
                push.publicKey,
                store.url(),
                /Mobile|iPhone|iPad|Android/.test(navigator.userAgent)
                    ? 'Celular / tablet'
                    : 'Computador',
            );
            setPermission(result.permission);

            if (result.permission !== 'granted' || !result.endpointHash) {
                setMessage('A permissão para notificações não foi concedida.');

                return;
            }

            if (workspace && auth.user) {
                setPushDeviceOptedOut(
                    pushDeviceOptOutKey(
                        auth.user.id,
                        workspace.tenant.id,
                        result.endpointHash,
                    ),
                    false,
                );
            }

            setEndpoint(result.endpointHash);
            setMessage('Notificações ativadas neste aparelho.');
            router.reload({
                only: ['subscriptions', 'notificationInvitation'],
            });
        } catch (error) {
            setMessage(
                error instanceof Error
                    ? error.message
                    : 'Não foi possível ativar as notificações.',
            );
        } finally {
            setBusy(false);
        }
    }
    async function deviceAction(
        subscription: Subscription,
        action: 'disable' | 'test',
    ) {
        setBusy(true);
        setMessage('');

        try {
            if (action === 'test') {
                await pushRequest(test.url(), 'POST', {
                    subscription_id: subscription.id,
                });
                setMessage(
                    'Teste solicitado. Aguarde cerca de 1 minuto. Se não aparecer, confira a conexão e a permissão de notificações nas configurações do iPhone ou do navegador.',
                );
            } else {
                await pushRequest(destroy.url(subscription.id), 'DELETE');

                if (workspace && auth.user) {
                    setPushDeviceOptedOut(
                        pushDeviceOptOutKey(
                            auth.user.id,
                            workspace.tenant.id,
                            subscription.endpoint_hash,
                        ),
                        true,
                    );
                }

                setMessage('Envios desativados para o aparelho.');
                router.reload({
                    only: ['subscriptions', 'notificationInvitation'],
                });
            }
        } catch (error) {
            setMessage(
                error instanceof Error
                    ? error.message
                    : 'Não foi possível concluir.',
            );
        } finally {
            setBusy(false);
        }
    }

    return (
        <>
            <Head title="Notificações" />
            <h1 className="sr-only">Notificações</h1>
            <div className="space-y-8">
                <Heading
                    variant="small"
                    title="Notificações"
                    description="Escolha os avisos da sua agenda nesta empresa e os aparelhos que podem recebê-los."
                />
                <section
                    className="space-y-4 rounded-xl border bg-card p-5"
                    aria-labelledby="device-title"
                >
                    <h2 id="device-title" className="font-medium">
                        Neste aparelho
                    </h2>
                    <p role="status" className="text-sm text-muted-foreground">
                        {deviceStatus}
                    </p>
                    {deviceState === 'install' && (
                        <div className="space-y-3">
                            <p className="text-sm text-muted-foreground">
                                Adicione o Caldas à Tela de Início pelo Safari
                                para receber notificações.
                            </p>
                            <Button
                                type="button"
                                onClick={() => setInstallGuideOpen(true)}
                            >
                                Ver como instalar no iPhone
                            </Button>
                            <Dialog
                                open={installGuideOpen && installRequired}
                                onOpenChange={setInstallGuideOpen}
                            >
                                <DialogContent className="max-h-[85dvh] overflow-y-auto">
                                    <DialogHeader>
                                        <DialogTitle>
                                            Instalar no iPhone
                                        </DialogTitle>
                                        <DialogDescription>
                                            Adicione o Caldas à Tela de Início
                                            pelo menu do Safari. São três
                                            passos:
                                        </DialogDescription>
                                    </DialogHeader>
                                    <IosInstallGuide />
                                    <DialogFooter>
                                        <Button
                                            type="button"
                                            onClick={() =>
                                                setInstallGuideOpen(false)
                                            }
                                        >
                                            Entendi
                                        </Button>
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>
                        </div>
                    )}
                    <div className="flex flex-wrap gap-2">
                        {deviceState === 'active' && current ? (
                            <>
                                <Button
                                    variant="outline"
                                    disabled={busy}
                                    onClick={() =>
                                        deviceAction(current, 'disable')
                                    }
                                >
                                    Desativar neste aparelho
                                </Button>
                                <Button
                                    disabled={busy || !push.available}
                                    onClick={() =>
                                        deviceAction(current, 'test')
                                    }
                                >
                                    Enviar teste
                                </Button>
                            </>
                        ) : deviceState === 'activate' ||
                          deviceState === 'reconnect' ? (
                            <Button disabled={busy} onClick={enable}>
                                {deviceState === 'reconnect'
                                    ? 'Reconectar neste aparelho'
                                    : 'Ativar notificações'}
                            </Button>
                        ) : null}
                    </div>
                    {message && (
                        <p role="status" className="text-sm">
                            {message}
                        </p>
                    )}
                </section>
                <form
                    className="space-y-6"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.patch(update.url(), { preserveScroll: true });
                    }}
                >
                    <h2 className="font-medium">Avisos da agenda</h2>
                    <p className="text-sm text-muted-foreground">
                        Estas preferências valem para esta empresa. Avisos
                        silenciados continuam no histórico da central.
                    </p>
                    {(
                        [
                            ['new_appointments', 'Novos agendamentos'],
                            ['changes', 'Alterações de horário e responsável'],
                            ['cancellations', 'Cancelamentos'],
                            ['reminders', 'Lembretes antes do atendimento'],
                        ] as const
                    ).map(([key, label]) => (
                        <div key={key} className="space-y-1">
                            <label className="flex cursor-pointer items-center justify-between gap-4 rounded-lg border p-4">
                                <span className="text-sm font-medium">
                                    {label}
                                </span>
                                <input
                                    type="checkbox"
                                    className="size-4 accent-primary"
                                    checked={form.data[key]}
                                    onChange={(event) =>
                                        form.setData(key, event.target.checked)
                                    }
                                />
                            </label>
                            <InputError message={form.errors[key]} />
                        </div>
                    ))}
                    <div className="space-y-2">
                        <Label htmlFor="reminder_minutes">
                            Antecedência do lembrete
                        </Label>
                        <select
                            id="reminder_minutes"
                            className="h-9 w-full rounded-md border bg-background px-3 text-sm"
                            value={form.data.reminder_minutes}
                            onChange={(event) =>
                                form.setData(
                                    'reminder_minutes',
                                    Number(event.target.value),
                                )
                            }
                        >
                            {[5, 10, 15, 30, 60].map((minutes) => (
                                <option key={minutes} value={minutes}>
                                    {minutes} minutos antes
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.reminder_minutes} />
                    </div>
                    <fieldset className="space-y-3">
                        <legend className="text-sm font-medium">
                            Horário de silêncio (opcional)
                        </legend>
                        <p className="text-sm text-muted-foreground">
                            Use o horário local da empresa. Durante esse
                            intervalo, os avisos ficam disponíveis somente na
                            central.
                        </p>
                        <div className="grid grid-cols-2 gap-4">
                            {(['quiet_start', 'quiet_end'] as const).map(
                                (key) => (
                                    <div key={key} className="space-y-2">
                                        <Label htmlFor={key}>
                                            {key === 'quiet_start'
                                                ? 'Início'
                                                : 'Fim'}
                                        </Label>
                                        <Input
                                            id={key}
                                            type="time"
                                            value={form.data[key] ?? ''}
                                            onChange={(event) =>
                                                form.setData(
                                                    key,
                                                    event.target.value || null,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={form.errors[key]}
                                        />
                                    </div>
                                ),
                            )}
                        </div>
                        <Button
                            variant="ghost"
                            type="button"
                            onClick={() => {
                                form.setData('quiet_start', null);
                                form.setData('quiet_end', null);
                            }}
                        >
                            Limpar horário de silêncio
                        </Button>
                    </fieldset>
                    <div className="flex items-center gap-3">
                        <Button disabled={form.processing}>
                            Salvar preferências
                        </Button>
                        {form.recentlySuccessful && (
                            <p
                                role="status"
                                className="text-sm text-muted-foreground"
                            >
                                Preferências salvas.
                            </p>
                        )}
                    </div>
                </form>
                {subscriptions.length > 0 && (
                    <section
                        className="space-y-3"
                        aria-labelledby="devices-title"
                    >
                        <h2 id="devices-title" className="font-medium">
                            Aparelhos autorizados nesta empresa
                        </h2>
                        {subscriptions.map((subscription) => (
                            <div
                                key={subscription.id}
                                className="flex items-center justify-between gap-4 rounded-lg border p-4"
                            >
                                <div>
                                    <p className="text-sm font-medium">
                                        {subscription.device_name}
                                        {subscription.endpoint_hash === endpoint
                                            ? ' · este aparelho'
                                            : ''}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Ativado em{' '}
                                        {new Date(
                                            subscription.created_at,
                                        ).toLocaleDateString('pt-BR')}
                                    </p>
                                </div>
                                <Button
                                    variant="ghost"
                                    disabled={busy}
                                    onClick={() =>
                                        deviceAction(subscription, 'disable')
                                    }
                                    aria-label={`Desativar ${subscription.device_name}`}
                                >
                                    Desativar
                                </Button>
                            </div>
                        ))}
                    </section>
                )}
            </div>
        </>
    );
}
Notifications.layout = {
    breadcrumbs: [{ title: 'Notificações', href: edit() }],
};
