import { router } from '@inertiajs/react';
import { Globe2 } from 'lucide-react';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { CoverEditor } from './cover-editor';
import { bookingTokens, bookingUi } from './design-tokens';
import { Field, SectionCard } from './booking-form-primitives';
import type { PublicSettings } from '../types';

export function IdentitySettingsPanel({
    settings,
    templateKey,
    coverUrl,
    coverUploadUrl,
    coverDeleteUrl,
}: {
    settings: PublicSettings;
    templateKey?: string | null;
    coverUrl?: string | null;
    coverUploadUrl?: string;
    coverDeleteUrl?: string;
}) {
    return (
        <SectionCard
            icon={Globe2}
            title="Identidade pública"
            description="Essas informações aparecem no perfil do seu negócio."
        >
            <div className={bookingTokens.layout.formGrid}>
                <div className="space-y-2 sm:col-span-2">
                    <Label htmlFor="template_key">
                        Template da página pública
                    </Label>
                    <select
                        id="template_key"
                        name="template_key"
                        defaultValue={
                            templateKey ?? settings.template_key ?? 'essential'
                        }
                        className={bookingUi.select}
                    >
                        <option value="essential">
                            Essencial — claro e direto
                        </option>
                        <option value="atelier-barber">
                            Atelier Barber — escuro e sofisticado
                        </option>
                    </select>
                    <p
                        className={`${bookingTokens.type.caption} text-muted-foreground`}
                    >
                        O template altera a apresentação pública sem mudar
                        serviços, disponibilidade ou regras de agendamento.
                    </p>
                </div>
                <Field
                    label="WhatsApp"
                    name="whatsapp_phone"
                    defaultValue={settings.whatsapp}
                    placeholder="(00) 00000-0000"
                />
                <Field
                    label="Telefone"
                    name="phone"
                    defaultValue={settings.phone}
                />
                <Field
                    label="Instagram"
                    name="instagram_url"
                    defaultValue={settings.instagram}
                    placeholder="instagram.com/seunegocio"
                />
                <Field
                    label="Facebook"
                    name="facebook_url"
                    defaultValue={settings.facebook}
                />
                <Field
                    label="Site"
                    name="website_url"
                    defaultValue={settings.website}
                    placeholder="https://seusite.com.br"
                />
            </div>
            <div className={bookingTokens.space.controlGroup}>
                <Label htmlFor="description">Descrição</Label>
                <Textarea
                    id="description"
                    name="description"
                    defaultValue={settings.description ?? ''}
                    placeholder="Conte um pouco sobre seu negócio e propósito."
                    rows={4}
                    className={bookingUi.field}
                />
            </div>
            <CoverEditor
                url={coverUrl}
                uploadUrl={coverUploadUrl}
                deleteUrl={coverDeleteUrl}
                onUploaded={() =>
                    router.reload({
                        only: [
                            'settings',
                            'gallery',
                            'publicUrl',
                            'canonicalUrl',
                        ],
                    })
                }
            />
        </SectionCard>
    );
}
