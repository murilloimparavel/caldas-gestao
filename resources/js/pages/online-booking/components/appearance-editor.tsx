import { router, useHttp } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import onlineBooking from '@/routes/online_booking';
import { LogoEditor } from './logo-editor';
import { bookingTokens } from './design-tokens';
import type {
    BookingAppearance,
    OnlineBookingProps,
    PublicSettings,
} from '../types';

const defaults: Record<keyof BookingAppearance, string> = {
    brand_name: '',
    headline: 'Agende seu horário.',
    subheadline: 'Escolha um serviço para começar seu agendamento.',
    primary_color: '#d4af37',
    background_color: '#0d0d0c',
    cta_label: 'Confirmar agendamento',
};

const valueOrDefault = (
    appearance: BookingAppearance | null | undefined,
    key: keyof BookingAppearance,
): string => appearance?.[key] ?? defaults[key];

export function AppearanceEditor({
    draft,
    settings,
    unitName,
    templateKey,
    logoUrl,
}: {
    draft: NonNullable<OnlineBookingProps['draft']>;
    settings: PublicSettings;
    unitName: string;
    templateKey: string;
    logoUrl?: string | null;
}) {
    const initial = {
        brand_name:
            valueOrDefault(
                draft.content?.appearance ?? settings.appearance,
                'brand_name',
            ) || unitName,
        headline: valueOrDefault(
            draft.content?.appearance ?? settings.appearance,
            'headline',
        ),
        subheadline: valueOrDefault(
            draft.content?.appearance ?? settings.appearance,
            'subheadline',
        ),
        primary_color: valueOrDefault(
            draft.content?.appearance ?? settings.appearance,
            'primary_color',
        ),
        background_color: valueOrDefault(
            draft.content?.appearance ?? settings.appearance,
            'background_color',
        ),
        cta_label: valueOrDefault(
            draft.content?.appearance ?? settings.appearance,
            'cta_label',
        ),
    } satisfies Record<keyof BookingAppearance, string>;
    const [appearance, setAppearance] = useState(initial);
    const [saving, setSaving] = useState(false);
    const [saved, setSaved] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const draftRequest = useHttp();

    const update = (key: keyof typeof appearance, value: string): void => {
        setSaved(false);
        setError(null);
        setAppearance((current) => ({ ...current, [key]: value }));
    };

    const save = async (): Promise<void> => {
        setSaving(true);
        setSaved(false);
        setError(null);
        draftRequest.setData({
            revision: draft.revision,
            content: {
                schema_version: draft.content?.schema_version ?? 1,
                sections: draft.content?.sections ?? [],
                appearance,
            },
        });

        try {
            await draftRequest.patch(onlineBooking.draft.update.url());
            setSaved(true);
            router.reload({
                only: ['draft', 'publication', 'draftDiff', 'previewUrl'],
            });
        } catch {
            setError('Não foi possível salvar a aparência. Tente novamente.');
        } finally {
            setSaving(false);
        }
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className={bookingTokens.type.sectionTitle}>
                    Identidade e conteúdo
                </CardTitle>
                <CardDescription>
                    Personalize os textos e as cores da página pública. As
                    alterações ficam no rascunho até publicar.
                </CardDescription>
            </CardHeader>
            <CardContent className={bookingTokens.space.page}>
                <LogoEditor
                    url={logoUrl}
                    onUploaded={() =>
                        router.reload({ only: ['settings', 'unit'] })
                    }
                />
                {templateKey === 'atelier-barber' ? (
                    <p className="rounded-lg border border-border bg-muted/30 p-3 text-sm text-muted-foreground">
                        A paleta de referência do Atelier Barber é fixa neste
                        template. Para personalizar as cores, selecione o
                        template Essencial.
                    </p>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className={bookingTokens.space.controlGroup}>
                            <Label htmlFor="appearance-brand-name">
                                Nome público
                            </Label>
                            <Input
                                id="appearance-brand-name"
                                value={appearance.brand_name}
                                onChange={(event) =>
                                    update('brand_name', event.target.value)
                                }
                                placeholder={unitName}
                                maxLength={120}
                            />
                        </div>
                        <div className={bookingTokens.space.controlGroup}>
                            <Label htmlFor="appearance-cta-label">
                                Texto do botão
                            </Label>
                            <Input
                                id="appearance-cta-label"
                                value={appearance.cta_label}
                                onChange={(event) =>
                                    update('cta_label', event.target.value)
                                }
                                maxLength={80}
                            />
                        </div>
                        <div
                            className={`${bookingTokens.space.controlGroup} sm:col-span-2`}
                        >
                            <Label htmlFor="appearance-headline">
                                Título principal
                            </Label>
                            <Input
                                id="appearance-headline"
                                value={appearance.headline}
                                onChange={(event) =>
                                    update('headline', event.target.value)
                                }
                                maxLength={160}
                            />
                        </div>
                        <div
                            className={`${bookingTokens.space.controlGroup} sm:col-span-2`}
                        >
                            <Label htmlFor="appearance-subheadline">
                                Subtítulo
                            </Label>
                            <Input
                                id="appearance-subheadline"
                                value={appearance.subheadline}
                                onChange={(event) =>
                                    update('subheadline', event.target.value)
                                }
                                maxLength={240}
                            />
                        </div>
                    </div>
                )}
                <div className="grid gap-4 sm:grid-cols-2">
                    {(
                        [
                            ['primary_color', 'Cor principal'],
                            ['background_color', 'Cor de fundo'],
                        ] as const
                    ).map(([key, label]) => (
                        <div
                            key={key}
                            className={bookingTokens.space.controlGroup}
                        >
                            <Label htmlFor={`appearance-${key}`}>{label}</Label>
                            <div className="flex items-center gap-3 rounded-md border border-input px-3 py-2">
                                <input
                                    id={`appearance-${key}`}
                                    type="color"
                                    value={appearance[key]}
                                    onChange={(event) =>
                                        update(key, event.target.value)
                                    }
                                    className="size-8 cursor-pointer rounded border-0 bg-transparent p-0"
                                />
                                <span className="font-mono text-xs text-muted-foreground uppercase">
                                    {appearance[key]}
                                </span>
                            </div>
                        </div>
                    ))}
                </div>
                <div className="flex flex-wrap items-center gap-3">
                    <Button type="button" onClick={save} disabled={saving}>
                        {saving ? 'Salvando…' : 'Salvar identidade'}
                    </Button>
                    {saved ? (
                        <span
                            role="status"
                            className="text-sm text-emerald-700 dark:text-emerald-300"
                        >
                            Aparência salva no rascunho.
                        </span>
                    ) : null}
                    {error ? (
                        <span role="alert" className="text-sm text-destructive">
                            {error}
                        </span>
                    ) : null}
                </div>
            </CardContent>
        </Card>
    );
}
