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
import onlineBooking from '@/routes/online_booking';
import type { OnlineBookingProps } from '../types';

const templateSections = [
    ['hero', 'Capa e apresentação'],
    ['services', 'Serviços'],
    ['professionals', 'Profissionais'],
    ['gallery', 'Galeria'],
    ['hours', 'Horários'],
    ['contact', 'Localização e contatos'],
    ['confirmation', 'Confirmação'],
    ['seo', 'SEO e compartilhamento'],
] as const;

export function SectionVisibilityEditor({
    draft,
}: {
    draft: NonNullable<OnlineBookingProps['draft']>;
}) {
    const initial = new Map(
        (draft.content?.sections ?? []).map((section) => [
            section.key,
            section.enabled,
        ]),
    );
    const [sections, setSections] = useState<Record<string, boolean>>(() =>
        Object.fromEntries(
            templateSections.map(([key]) => [key, initial.get(key) ?? true]),
        ),
    );
    const [saving, setSaving] = useState(false);
    const draftRequest = useHttp<
        {
            revision: number;
            content: {
                schema_version: number;
                sections: { key: string; enabled: boolean }[];
            };
        },
        { status: string }
    >();

    const save = async (): Promise<void> => {
        setSaving(true);
        draftRequest.setData({
            revision: draft.revision,
            content: {
                schema_version: 1,
                sections: templateSections.map(([key]) => ({
                    key,
                    enabled: sections[key] ?? true,
                })),
            },
        });

        try {
            await draftRequest.patch(onlineBooking.draft.update.url());
            router.reload({ only: ['draft', 'publication', 'draftDiff'] });
        } finally {
            setSaving(false);
        }
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Seções da página</CardTitle>
                <CardDescription>
                    Escolha o que aparece no canal público. Essas alterações
                    ficam no rascunho até publicar.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
                <div className="grid gap-2 sm:grid-cols-2">
                    {templateSections.map(([key, label]) => (
                        <label
                            key={key}
                            className="flex cursor-pointer items-center gap-3 rounded-xl border p-3 text-sm hover:border-primary/50"
                        >
                            <input
                                type="checkbox"
                                checked={sections[key] ?? true}
                                onChange={(event) =>
                                    setSections((current) => ({
                                        ...current,
                                        [key]: event.target.checked,
                                    }))
                                }
                                className="size-4 accent-primary"
                            />
                            <span>{label}</span>
                        </label>
                    ))}
                </div>
                <Button
                    type="button"
                    variant="outline"
                    onClick={save}
                    disabled={saving}
                >
                    {saving ? 'Salvando…' : 'Salvar seções no rascunho'}
                </Button>
            </CardContent>
        </Card>
    );
}
