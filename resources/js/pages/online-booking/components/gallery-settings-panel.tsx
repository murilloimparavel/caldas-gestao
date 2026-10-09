import { GalleryHorizontalEnd, ImagePlus } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { SectionCard } from './booking-form-primitives';
import { bookingTokens } from './design-tokens';

type GalleryItem = {
    id: string;
    url?: string | null;
    path?: string | null;
    alt?: string | null;
    alt_text?: string | null;
};

export function GallerySettingsPanel({
    galleryItems,
    onUpload,
    onUpdateAlt,
    onDelete,
    onMove,
}: {
    galleryItems: GalleryItem[];
    onUpload: (file: File) => void;
    onUpdateAlt: (id: string, alt: string) => Promise<boolean>;
    onDelete: (id: string) => void;
    onMove: (id: string, direction: -1 | 1) => void;
}) {
    const [pendingAltTextIds, setPendingAltTextIds] = useState<Set<string>>(
        () => new Set(),
    );

    const saveAltText = async (
        id: string,
        value: string,
        persistedValue: string,
        input: HTMLInputElement,
    ): Promise<void> => {
        setPendingAltTextIds((current) => new Set(current).add(id));

        try {
            const saved = await onUpdateAlt(id, value);

            if (!saved) {
                input.value = persistedValue;
            }
        } catch {
            input.value = persistedValue;
        } finally {
            setPendingAltTextIds((current) => {
                const next = new Set(current);
                next.delete(id);

                return next;
            });
        }
    };

    return (
        <SectionCard
            icon={GalleryHorizontalEnd}
            title="Galeria de fotos"
            description="Mostre o ambiente e a experiência da sua unidade."
        >
            <p className="text-xs leading-5 text-muted-foreground">
                Alterações na galeria são salvas imediatamente. Salve o rascunho
                para atualizar a prévia e publique para atualizar o link
                público. Imagens excluídas são removidas imediatamente e podem
                deixar de aparecer no link antes da publicação.
            </p>
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                {galleryItems.map((image, index) => (
                    <div
                        key={image.id}
                        className={`relative aspect-square overflow-hidden ${bookingTokens.radius.control} border border-border bg-muted`}
                    >
                        {image.url ? (
                            <img
                                src={image.url}
                                alt={image.alt ?? ''}
                                className="size-full object-cover"
                            />
                        ) : image.path ? (
                            <div className="flex size-full items-center justify-center px-3 text-center text-xs text-muted-foreground">
                                Imagem enviada
                            </div>
                        ) : (
                            <div className="flex size-full items-center justify-center text-muted-foreground">
                                <ImagePlus className="size-6" />
                            </div>
                        )}
                        <div className="absolute inset-x-0 bottom-0 space-y-1 bg-background/90 p-2 backdrop-blur-sm">
                            <Input
                                key={`${image.id}:${image.alt ?? image.alt_text ?? ''}`}
                                aria-label={`Texto alternativo da imagem ${index + 1}`}
                                defaultValue={image.alt ?? image.alt_text ?? ''}
                                disabled={pendingAltTextIds.has(image.id)}
                                onBlur={(event) => {
                                    const persistedValue =
                                        image.alt ?? image.alt_text ?? '';
                                    const input = event.currentTarget;
                                    const value = input.value;

                                    if (value !== persistedValue) {
                                        void saveAltText(
                                            image.id,
                                            value,
                                            persistedValue,
                                            input,
                                        );
                                    }
                                }}
                                placeholder="Texto alternativo"
                                className="h-7 text-3xs"
                            />
                            {pendingAltTextIds.has(image.id) ? (
                                <span
                                    role="status"
                                    className="text-3xs text-muted-foreground"
                                >
                                    Salvando texto alternativo…
                                </span>
                            ) : null}
                            <div className="flex gap-1">
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    disabled={index === 0}
                                    onClick={() => onMove(image.id, -1)}
                                    aria-label="Mover imagem para cima"
                                >
                                    ↑
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    disabled={index === galleryItems.length - 1}
                                    onClick={() => onMove(image.id, 1)}
                                    aria-label="Mover imagem para baixo"
                                >
                                    ↓
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    className="ml-auto text-destructive"
                                    onClick={() => onDelete(image.id)}
                                    aria-label="Excluir imagem"
                                >
                                    Excluir
                                </Button>
                            </div>
                        </div>
                    </div>
                ))}
                <label className="flex aspect-square cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-border bg-muted/20 text-center hover:border-primary/60">
                    <ImagePlus className="size-6 text-primary" />
                    <span className="text-xs font-medium">Adicionar foto</span>
                    <span className="text-3xs text-muted-foreground">
                        JPG, PNG, WEBP até 5MB
                    </span>
                    <input
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        className="sr-only"
                        onChange={(event) => {
                            const file = event.target.files?.[0];

                            if (file) {
                                onUpload(file);
                            }

                            event.currentTarget.value = '';
                        }}
                    />
                </label>
            </div>
        </SectionCard>
    );
}
