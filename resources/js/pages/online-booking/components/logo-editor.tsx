import { router } from '@inertiajs/react';
import { ImagePlus } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import logoRoutes from '@/routes/online_booking/logo';

const MAX_FILE_SIZE = 5 * 1024 * 1024;
const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

type LogoEditorProps = {
    url?: string | null;
    onUploaded: () => void;
};

export function LogoEditor({ url, onUploaded }: LogoEditorProps) {
    const [message, setMessage] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);

    const upload = (file: File): void => {
        setMessage(null);

        if (!ACCEPTED_TYPES.includes(file.type)) {
            setMessage('Use uma imagem JPG, PNG ou WEBP.');

            return;
        }

        if (file.size > MAX_FILE_SIZE) {
            setMessage('A imagem deve ter no máximo 5 MB.');

            return;
        }

        const data = new FormData();
        data.append('image', file);
        setProcessing(true);
        router.post(logoRoutes.store.url(), data, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setMessage('Logo atualizado.');
                onUploaded();
            },
            onError: (errors) => {
                setMessage(
                    errors.image ??
                        errors.logo ??
                        'Não foi possível atualizar o logo. Tente novamente.',
                );
            },
            onFinish: () => setProcessing(false),
        });
    };

    const remove = (): void => {
        setMessage(null);
        setProcessing(true);
        router.delete(logoRoutes.destroy.url(), {
            preserveScroll: true,
            onSuccess: () => {
                setMessage('Logo removido.');
                onUploaded();
            },
            onError: () =>
                setMessage('Não foi possível remover o logo. Tente novamente.'),
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <div className="flex flex-col items-center gap-3 border-b border-border/60 pb-5 sm:flex-row sm:items-start">
            <div className="flex size-28 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-border bg-muted/40 p-3 shadow-sm">
                {url ? (
                    <img
                        src={url}
                        alt="Logo atual da unidade"
                        className="max-h-full max-w-full object-contain"
                    />
                ) : (
                    <div className="flex flex-col items-center gap-2 px-2 text-center text-muted-foreground">
                        <ImagePlus aria-hidden="true" className="size-7" />
                        <span className="text-xs">Nenhum logo definido</span>
                    </div>
                )}
            </div>
            <div className="flex min-w-0 flex-1 flex-col items-center gap-2 text-center sm:items-start sm:text-left">
                <div>
                    <p className="text-sm font-semibold">Logo da unidade</p>
                    <p className="mt-1 max-w-md text-xs leading-5 text-muted-foreground">
                        O logo aparece no cabeçalho do agendamento público. Se
                        não for informado, usamos o nome da unidade.
                    </p>
                    <p className="mt-1 max-w-md text-xs leading-5 text-muted-foreground">
                        Alterações no logo são salvas imediatamente e podem
                        aparecer no link público sem publicar o rascunho.
                    </p>
                </div>
                <div className="flex flex-wrap justify-center gap-2 sm:justify-start">
                    <label className="inline-flex cursor-pointer items-center gap-2 rounded-md bg-primary px-3 py-2 text-sm font-medium text-primary-foreground shadow-xs transition hover:bg-primary/90 has-disabled:pointer-events-none has-disabled:opacity-50">
                        <ImagePlus aria-hidden="true" className="size-4" />
                        {url ? 'Substituir' : 'Adicionar'}
                        <input
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            className="sr-only"
                            disabled={processing}
                            onChange={(event) => {
                                const file = event.target.files?.[0];

                                if (file) {
                                    upload(file);
                                }

                                event.currentTarget.value = '';
                            }}
                        />
                    </label>
                    {url ? (
                        <Button
                            type="button"
                            variant="destructive"
                            size="sm"
                            onClick={remove}
                            disabled={processing}
                        >
                            Remover
                        </Button>
                    ) : null}
                </div>
                <p className="text-3xs text-muted-foreground">
                    JPG, PNG ou WEBP · até 5 MB
                </p>
                {message ? (
                    <p role="status" className="text-xs text-primary">
                        {message}
                    </p>
                ) : null}
            </div>
        </div>
    );
}
