import { GalleryHorizontalEnd, ImagePlus } from 'lucide-react';
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
    onUpdateAlt: (id: string, alt: string) => void;
    onDelete: (id: string) => void;
    onMove: (id: string, direction: -1 | 1) => void;
}) {
    return (
        <SectionCard
            icon={GalleryHorizontalEnd}
            title="Galeria de fotos"
            description="Mostre o ambiente e a experiência da sua unidade."
        >
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
                                aria-label={`Texto alternativo da imagem ${index + 1}`}
                                defaultValue={image.alt ?? image.alt_text ?? ''}
                                onBlur={(event) =>
                                    onUpdateAlt(image.id, event.target.value)
                                }
                                placeholder="Texto alternativo"
                                className="h-7 text-3xs"
                            />
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
