import { router } from '@inertiajs/react';
import { useState } from 'react';
import { ImagePlus } from 'lucide-react';
import { Button } from '@/components/ui/button';

type CoverEditorProps = {
    url?: string | null;
    uploadUrl?: string | null;
    deleteUrl?: string | null;
    onUploaded: () => void;
};

export function CoverEditor({
    url,
    uploadUrl,
    deleteUrl,
    onUploaded,
}: CoverEditorProps) {
    const [message, setMessage] = useState<string | null>(null);

    const upload = (file: File): void => {
        if (!uploadUrl) {
            setMessage('A capa ainda não está habilitada para esta unidade.');

            return;
        }

        const data = new FormData();
        data.append('image', file);
        router.post(uploadUrl, data, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setMessage('Capa atualizada.');
                onUploaded();
            },
        });
    };

    const remove = (): void => {
        if (!deleteUrl) {
            setMessage('A remoção da capa ainda não está habilitada.');

            return;
        }

        router.delete(deleteUrl, {
            preserveScroll: true,
            onSuccess: () => {
                setMessage('Capa removida.');
                onUploaded();
            },
        });
    };

    return (
        <div className="flex flex-col items-center gap-3 border-b border-border/60 pb-5 sm:flex-row sm:items-start">
            <div className="relative flex h-36 w-full max-w-60 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-border bg-muted/40 shadow-sm sm:h-32 sm:w-52">
                {url ? (
                    <img
                        src={url}
                        alt="Capa atual da unidade"
                        className="size-full object-cover"
                    />
                ) : (
                    <div className="flex flex-col items-center gap-2 px-4 text-center text-muted-foreground">
                        <ImagePlus aria-hidden="true" className="size-8" />
                        <span className="text-xs">Nenhuma capa definida</span>
                    </div>
                )}
            </div>
            <div className="flex min-w-0 flex-1 flex-col items-center gap-2 text-center sm:items-start sm:text-left">
                <div>
                    <p className="text-sm font-semibold">Imagem principal</p>
                    <p className="mt-1 max-w-md text-xs leading-5 text-muted-foreground">
                        É a imagem em destaque no topo do canal público. A
                        galeria continua independente e reúne as demais fotos.
                    </p>
                </div>
                <div className="flex flex-wrap justify-center gap-2 sm:justify-start">
                    <label className="inline-flex cursor-pointer items-center gap-2 rounded-md bg-primary px-3 py-2 text-sm font-medium text-primary-foreground shadow-xs transition hover:bg-primary/90 has-disabled:pointer-events-none has-disabled:opacity-50">
                        <ImagePlus aria-hidden="true" className="size-4" />
                        Alterar
                        <input
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            className="sr-only"
                            disabled={!uploadUrl}
                            onChange={(event) => {
                                const file = event.target.files?.[0];

                                if (file) {
                                    upload(file);
                                }

                                event.currentTarget.value = '';
                            }}
                        />
                    </label>
                    {url && (
                        <Button
                            type="button"
                            variant="destructive"
                            size="sm"
                            onClick={remove}
                            disabled={!deleteUrl}
                        >
                            Remover
                        </Button>
                    )}
                </div>
                <p className="text-3xs text-muted-foreground">
                    JPG, PNG ou WEBP · até 5 MB
                </p>
                {message && (
                    <p role="status" className="text-xs text-primary">
                        {message}
                    </p>
                )}
            </div>
        </div>
    );
}
