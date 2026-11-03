import { router, usePage } from '@inertiajs/react';
import { BellRing, CalendarClock, Smartphone } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import IosInstallGuide from '@/components/ios-install-guide';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    dismissPushInvitation,
    isPushInvitationDismissed,
    isPushDeviceOptedOut,
    setPushDeviceOptedOut,
    pushDeviceOptOutKey,
    pushInvitationMode,
    shouldOfferPushRecovery,
} from '@/lib/push-invitation';
import {
    enablePushSubscription,
    endpointHash,
    pushRequest,
} from '@/lib/push-notifications';
import { dismiss as dismissInvitation } from '@/routes/notification-settings/invitation';
import { store } from '@/routes/push-subscriptions';
import type { SharedPageProps } from '@/types';

function isMobileDevice(): boolean {
    return (
        /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent) ||
        (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)
    );
}

function isIosDevice(): boolean {
    return (
        /iPad|iPhone|iPod/.test(navigator.userAgent) ||
        (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)
    );
}

function isInstalledApp(): boolean {
    return (
        window.matchMedia('(display-mode: standalone)').matches ||
        ('standalone' in navigator && navigator.standalone === true)
    );
}

export default function PushInvitation() {
    const page = usePage<SharedPageProps>();
    const { auth, notificationInvitation, workspace } = page.props;
    const showsDeviceSettings =
        page.url.split('?')[0] === '/settings/notifications';
    const [openTenantId, setOpenTenantId] = useState<string | null>(null);
    const [installGuideTenantId, setInstallGuideTenantId] = useState<
        string | null
    >(null);
    const [recoveryTenantId, setRecoveryTenantId] = useState<string | null>(
        null,
    );
    const [busy, setBusy] = useState(false);
    const [message, setMessage] = useState('');
    const suppressDismissOnNextClose = useRef(false);
    const tenantId = workspace?.tenant.id ?? null;
    const open = tenantId !== null && openTenantId === tenantId;
    const showInstallGuide =
        tenantId !== null && installGuideTenantId === tenantId;
    const isIos = typeof navigator !== 'undefined' && isIosDevice();

    useEffect(() => {
        if (
            !tenantId ||
            !auth.user ||
            !isMobileDevice() ||
            !window.isSecureContext ||
            !('Notification' in window) ||
            !('serviceWorker' in navigator) ||
            !('PushManager' in window)
        ) {
            return;
        }

        const userId = auth.user.id;
        let cancelled = false;
        void (async () => {
            const registration =
                await navigator.serviceWorker.getRegistration('/');
            const subscription =
                await registration?.pushManager.getSubscription();
            const hash = subscription
                ? await endpointHash(subscription.endpoint)
                : null;
            const optedOut =
                hash !== null &&
                isPushDeviceOptedOut(
                    pushDeviceOptOutKey(userId, tenantId, hash),
                );
            const recover = shouldOfferPushRecovery({
                available: !!notificationInvitation?.available,
                dismissed:
                    !!notificationInvitation?.dismissed ||
                    isPushInvitationDismissed(userId, tenantId),
                permission: Notification.permission,
                registeredEndpointHashes:
                    notificationInvitation?.registeredEndpointHashes ?? [],
                currentEndpointHash: hash,
                optedOut,
            });

            if (!cancelled) {
                setRecoveryTenantId(recover ? tenantId : null);
            }
        })().catch(() => undefined);

        return () => {
            cancelled = true;
        };
    }, [auth.user, notificationInvitation, tenantId]);

    useEffect(() => {
        const mode = pushInvitationMode({
            eligible: !!tenantId && !!notificationInvitation?.eligible,
            dismissed:
                !!notificationInvitation?.dismissed ||
                (!!auth.user &&
                    !!tenantId &&
                    isPushInvitationDismissed(auth.user.id, tenantId)),
            mobile: isMobileDevice(),
            ios: isIosDevice(),
            installed: isInstalledApp(),
            secure: window.isSecureContext,
            permission:
                'Notification' in window ? Notification.permission : undefined,
            serviceWorker: 'serviceWorker' in navigator,
            pushManager: 'PushManager' in window,
        });

        if (mode === null) {
            return;
        }

        let cancelled = false;
        const timer = window.setTimeout(() => {
            if (mode === 'install') {
                setInstallGuideTenantId(tenantId);
                setOpenTenantId(tenantId);

                return;
            }

            void navigator.serviceWorker
                .getRegistration('/')
                .then((registration) =>
                    registration?.pushManager.getSubscription(),
                )
                .then((subscription) => {
                    if (
                        !cancelled &&
                        !subscription &&
                        Notification.permission === 'default'
                    ) {
                        setOpenTenantId(tenantId);
                    }
                })
                .catch(() => undefined);
        }, 1200);

        return () => {
            cancelled = true;
            window.clearTimeout(timer);
        };
    }, [auth.user, notificationInvitation, tenantId]);

    async function dismiss() {
        if (busy) {
            return;
        }

        setBusy(true);
        setMessage('');

        try {
            await pushRequest(dismissInvitation.url(), 'POST');

            if (auth.user && tenantId) {
                dismissPushInvitation(auth.user.id, tenantId);
            }

            setOpenTenantId(null);
            setInstallGuideTenantId(null);
            setRecoveryTenantId(null);
        } catch (error) {
            setMessage(
                error instanceof Error
                    ? error.message
                    : 'Não foi possível salvar sua escolha. Tente novamente.',
            );
        } finally {
            setBusy(false);
        }
    }

    async function enable() {
        if (isIos && !isInstalledApp()) {
            setInstallGuideTenantId(tenantId);

            return;
        }

        if (!notificationInvitation.publicKey) {
            setMessage('O envio de notificações ainda não está configurado.');

            return;
        }

        setBusy(true);
        setMessage('');

        try {
            const result = await enablePushSubscription(
                notificationInvitation.publicKey,
                store.url(),
                'Celular / tablet',
            );

            if (result.permission !== 'granted' || !result.endpointHash) {
                setOpenTenantId(null);

                return;
            }

            if (tenantId && auth.user) {
                setPushDeviceOptedOut(
                    pushDeviceOptOutKey(
                        auth.user.id,
                        tenantId,
                        result.endpointHash,
                    ),
                    false,
                );
            }

            setRecoveryTenantId(null);
            setOpenTenantId(null);
            router.reload({ only: ['notificationInvitation'] });
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

    function continueAfterInstallGuide() {
        if (!isInstalledApp()) {
            suppressDismissOnNextClose.current = true;
            setInstallGuideTenantId(null);
            setOpenTenantId(null);

            return;
        }

        setInstallGuideTenantId(null);
        void enable();
    }

    return (
        <>
            {tenantId &&
                recoveryTenantId === tenantId &&
                !showsDeviceSettings && (
                    <aside
                        className="fixed inset-x-4 bottom-[calc(5rem+env(safe-area-inset-bottom))] z-40 mx-auto max-w-md space-y-3 rounded-xl border bg-card p-4 shadow-lg sm:bottom-4"
                        aria-label="Reconectar notificações"
                    >
                        <p className="text-sm">
                            Este aparelho precisa ser reconectado para receber
                            os avisos da sua agenda.
                        </p>
                        <div className="flex flex-wrap gap-2">
                            <Button
                                disabled={busy}
                                onClick={() => void enable()}
                            >
                                Reconectar neste aparelho
                            </Button>
                            <Button
                                variant="ghost"
                                disabled={busy}
                                onClick={() => void dismiss()}
                            >
                                Agora não
                            </Button>
                        </div>
                        {message && (
                            <p
                                role="status"
                                className="text-sm text-destructive"
                            >
                                {message}
                            </p>
                        )}
                    </aside>
                )}
            <Dialog
                open={open}
                onOpenChange={(nextOpen) => {
                    if (!nextOpen && open) {
                        if (suppressDismissOnNextClose.current) {
                            suppressDismissOnNextClose.current = false;

                            return;
                        }

                        void dismiss();
                    }
                }}
            >
                <DialogContent className="max-h-[85dvh] gap-5 overflow-y-auto rounded-2xl p-6 sm:p-7">
                    <DialogHeader className="gap-3 text-left">
                        <div className="flex size-12 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                            {showInstallGuide ? (
                                <Smartphone
                                    className="size-6"
                                    aria-hidden="true"
                                />
                            ) : (
                                <BellRing
                                    className="size-6"
                                    aria-hidden="true"
                                />
                            )}
                        </div>
                        <DialogTitle className="text-xl">
                            {showInstallGuide
                                ? 'Adicione o Caldas à tela inicial'
                                : 'Receba os avisos da sua agenda'}
                        </DialogTitle>
                        <DialogDescription className="text-sm leading-6">
                            {showInstallGuide
                                ? 'No iPhone, abra o menu Compartilhar do Safari, toque em “Adicionar à Tela de Início” e depois abra o Caldas pelo novo ícone.'
                                : 'Ative notificações para saber quando entra um novo horário, quando a agenda muda e quando um atendimento está próximo.'}
                        </DialogDescription>
                    </DialogHeader>

                    {showInstallGuide && <IosInstallGuide />}
                    {!showInstallGuide && (
                        <div className="flex items-start gap-3 rounded-xl border bg-muted/35 p-4">
                            <CalendarClock
                                className="mt-0.5 size-5 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <p className="text-sm leading-5 text-muted-foreground">
                                Você controla os avisos a qualquer momento nas
                                configurações. O navegador só perguntará depois
                                que você tocar em ativar.
                            </p>
                        </div>
                    )}

                    {message && (
                        <p role="status" className="text-sm text-destructive">
                            {message}
                        </p>
                    )}

                    <DialogFooter className="flex-col-reverse gap-2 sm:flex-row">
                        <Button
                            type="button"
                            variant="ghost"
                            disabled={busy}
                            onClick={() => void dismiss()}
                        >
                            Agora não
                        </Button>
                        {showInstallGuide ? (
                            <Button
                                type="button"
                                disabled={busy}
                                onClick={continueAfterInstallGuide}
                            >
                                Entendi
                            </Button>
                        ) : (
                            <Button
                                type="button"
                                disabled={busy}
                                onClick={() => void enable()}
                            >
                                Ativar notificações
                            </Button>
                        )}
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
