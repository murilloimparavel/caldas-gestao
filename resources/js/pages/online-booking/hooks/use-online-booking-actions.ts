import { router, useHttp } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import onlineBooking from '@/routes/online_booking';
import galleryRoutes from '@/routes/online_booking/gallery';
import type { OnlineBookingProps } from '../types';

type UseOnlineBookingActionsOptions = {
    draft: OnlineBookingProps['draft'];
    publicUrl: string | null;
    gallery: NonNullable<OnlineBookingProps['gallery']>;
};

type GalleryUploadData = {
    image: File | null;
};

type GalleryAltData = {
    alt_text: string;
};

type GalleryReorderData = {
    image_ids: string[];
};

export function useOnlineBookingActions({
    draft,
    publicUrl,
    gallery,
}: UseOnlineBookingActionsOptions) {
    const [copied, setCopied] = useState(false);
    const [publicationError, setPublicationError] = useState<string | null>(
        null,
    );
    const [publicationProcessing, setPublicationProcessing] = useState(false);
    const [galleryItems, setGalleryItems] = useState(gallery);
    const [previousGallery, setPreviousGallery] = useState(gallery);
    const galleryReorderInProgress = useRef(false);
    const galleryUploadInProgress = useRef(false);
    const galleryUploadRequest = useHttp<GalleryUploadData>({ image: null });
    const galleryAltRequest = useHttp<GalleryAltData>({ alt_text: '' });
    const galleryDeleteRequest = useHttp();
    const galleryReorderRequest = useHttp<GalleryReorderData>({
        image_ids: [],
    });

    if (gallery !== previousGallery) {
        setPreviousGallery(gallery);
        setGalleryItems(gallery);
    }

    const copyPublicUrl = async (): Promise<void> => {
        if (!publicUrl || !navigator.clipboard) {
            return;
        }

        await navigator.clipboard.writeText(publicUrl);
        setCopied(true);
        window.setTimeout(() => setCopied(false), 2200);
    };

    const publishDraft = (): void => {
        if (!draft) {
            return;
        }

        setPublicationError(null);
        setPublicationProcessing(true);
        router.post(
            onlineBooking.publish.url(),
            { revision: draft.revision },
            {
                preserveScroll: true,
                onError: (errors) =>
                    setPublicationError(
                        errors.publication ??
                            'Não foi possível publicar. Revise os requisitos e tente novamente.',
                    ),
                onFinish: () => setPublicationProcessing(false),
            },
        );
    };

    const unpublishSite = (): void => {
        if (!window.confirm('Retirar esta página do ar agora?')) {
            return;
        }

        setPublicationError(null);
        setPublicationProcessing(true);
        router.post(
            onlineBooking.unpublish.url(),
            {},
            {
                preserveScroll: true,
                onError: () =>
                    setPublicationError(
                        'Não foi possível retirar a página do ar. Tente novamente.',
                    ),
                onFinish: () => setPublicationProcessing(false),
            },
        );
    };

    const uploadGalleryImage = (file: File): void => {
        if (galleryUploadInProgress.current) {
            return;
        }

        galleryUploadInProgress.current = true;
        galleryUploadRequest.setData({ image: file });
        const toastId = toast.loading('Enviando imagem…');
        void galleryUploadRequest
            .post(galleryRoutes.store.url(), {
                onSuccess: () => {
                    toast.success('Imagem adicionada à galeria.', {
                        id: toastId,
                    });
                    router.reload({
                        only: ['gallery'],
                        onError: () =>
                            toast.error(
                                'A imagem foi enviada, mas a galeria não pôde ser atualizada. Recarregue a página.',
                            ),
                        onNetworkError: () => {
                            toast.error(
                                'A imagem foi enviada, mas a atualização da galeria falhou por conexão. Recarregue a página.',
                            );

                            return false;
                        },
                        onHttpException: () => {
                            toast.error(
                                'A imagem foi enviada, mas o servidor não conseguiu atualizar a galeria. Recarregue a página.',
                            );

                            return false;
                        },
                    });
                },
                onError: (errors) =>
                    toast.error(
                        errors.image ??
                            'Não foi possível enviar a imagem. Tente novamente.',
                        { id: toastId },
                    ),
                onNetworkError: () => {
                    toast.error(
                        'Falha de conexão ao enviar a imagem. Verifique sua conexão e tente novamente.',
                        { id: toastId },
                    );

                    return false;
                },
                onHttpException: () => {
                    toast.error(
                        'O servidor não conseguiu enviar a imagem. Tente novamente.',
                        { id: toastId },
                    );

                    return false;
                },
                onCancel: () => toast.dismiss(toastId),
                onFinish: () => {
                    galleryUploadRequest.setData({ image: null });
                    galleryUploadInProgress.current = false;
                },
            })
            .catch(() => undefined);
    };

    const updateGalleryAlt = async (
        id: string,
        alt_text: string,
    ): Promise<boolean> => {
        galleryAltRequest.setData({ alt_text });
        const toastId = toast.loading('Salvando o texto alternativo…');
        let requestSucceeded = false;

        try {
            await galleryAltRequest.patch(galleryRoutes.update.url(id), {
                onSuccess: () => {
                    setGalleryItems((items) =>
                        items.map((item) =>
                            item.id === id
                                ? { ...item, alt: alt_text, alt_text }
                                : item,
                        ),
                    );
                    toast.success('Texto alternativo atualizado.', {
                        id: toastId,
                    });
                    requestSucceeded = true;
                },
                onError: (errors) =>
                    toast.error(
                        errors.alt_text ??
                            'Não foi possível salvar o texto alternativo. Tente novamente.',
                        { id: toastId },
                    ),
                onNetworkError: () => {
                    toast.error(
                        'Falha de conexão ao salvar o texto alternativo. Tente novamente.',
                        { id: toastId },
                    );

                    return false;
                },
                onHttpException: () => {
                    toast.error(
                        'O servidor não conseguiu salvar o texto alternativo. Tente novamente.',
                        { id: toastId },
                    );

                    return false;
                },
                onCancel: () => toast.dismiss(toastId),
            });
        } catch {
            return false;
        }

        return requestSucceeded;
    };

    const deleteGalleryImage = (id: string): void => {
        const toastId = toast.loading('Excluindo imagem…');
        void galleryDeleteRequest
            .delete(galleryRoutes.destroy.url(id), {
                onSuccess: () => {
                    setGalleryItems((items) =>
                        items.filter((item) => item.id !== id),
                    );
                    toast.success('Imagem excluída da galeria.', {
                        id: toastId,
                    });
                },
                onError: () =>
                    toast.error(
                        'Não foi possível excluir a imagem. Tente novamente.',
                        { id: toastId },
                    ),
                onNetworkError: () => {
                    toast.error(
                        'Falha de conexão ao excluir a imagem. Tente novamente.',
                        { id: toastId },
                    );

                    return false;
                },
                onHttpException: () => {
                    toast.error(
                        'O servidor não conseguiu excluir a imagem. Tente novamente.',
                        { id: toastId },
                    );

                    return false;
                },
                onCancel: () => toast.dismiss(toastId),
            })
            .catch(() => undefined);
    };

    const moveGalleryImage = (id: string, direction: -1 | 1): void => {
        if (galleryReorderInProgress.current) {
            return;
        }

        const index = galleryItems.findIndex((item) => item.id === id);
        const nextIndex = index + direction;

        if (index < 0 || nextIndex < 0 || nextIndex >= galleryItems.length) {
            return;
        }

        const next = [...galleryItems];
        const previousItems = galleryItems;

        [next[index], next[nextIndex]] = [next[nextIndex], next[index]];
        galleryReorderInProgress.current = true;
        setGalleryItems(next);
        galleryReorderRequest.setData({
            image_ids: next.map((item) => item.id),
        });
        const toastId = toast.loading('Salvando a ordem da galeria…');
        void galleryReorderRequest
            .post(galleryRoutes.reorder.url(), {
                onSuccess: () =>
                    toast.success('Ordem da galeria atualizada.', {
                        id: toastId,
                    }),
                onError: () => {
                    setGalleryItems(previousItems);
                    toast.error(
                        'Não foi possível salvar a ordem. A galeria voltou à ordem anterior.',
                        { id: toastId },
                    );
                },
                onNetworkError: () => {
                    setGalleryItems(previousItems);
                    toast.error(
                        'Falha de conexão ao salvar a ordem. A galeria voltou à ordem anterior.',
                        { id: toastId },
                    );

                    return false;
                },
                onHttpException: () => {
                    setGalleryItems(previousItems);
                    toast.error(
                        'O servidor não conseguiu salvar a ordem. A galeria voltou à ordem anterior.',
                        { id: toastId },
                    );

                    return false;
                },
                onCancel: () => {
                    setGalleryItems(previousItems);
                    toast.dismiss(toastId);
                },
                onFinish: () => {
                    galleryReorderInProgress.current = false;
                },
            })
            .catch(() => undefined);
    };

    return {
        copied,
        publicationError,
        publicationProcessing,
        galleryItems,
        copyPublicUrl,
        publishDraft,
        unpublishSite,
        uploadGalleryImage,
        updateGalleryAlt,
        deleteGalleryImage,
        moveGalleryImage,
    };
}
