import { router } from '@inertiajs/react';
import { useState } from 'react';
import onlineBooking from '@/routes/online_booking';
import galleryRoutes from '@/routes/online_booking/gallery';
import type { OnlineBookingProps } from '../types';

type UseOnlineBookingActionsOptions = {
    draft: OnlineBookingProps['draft'];
    publicUrl: string | null;
    gallery: NonNullable<OnlineBookingProps['gallery']>;
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
        const data = new FormData();
        data.append('image', file);
        router.post(galleryRoutes.store.url(), data, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => router.reload({ only: ['gallery'] }),
        });
    };

    const updateGalleryAlt = (id: string, alt_text: string): void => {
        router.patch(
            galleryRoutes.update.url(id),
            { alt_text },
            {
                preserveScroll: true,
                onSuccess: () =>
                    setGalleryItems((items) =>
                        items.map((item) =>
                            item.id === id
                                ? { ...item, alt: alt_text, alt_text }
                                : item,
                        ),
                    ),
            },
        );
    };

    const deleteGalleryImage = (id: string): void => {
        router.delete(galleryRoutes.destroy.url(id), {
            preserveScroll: true,
            onSuccess: () =>
                setGalleryItems((items) =>
                    items.filter((item) => item.id !== id),
                ),
        });
    };

    const moveGalleryImage = (id: string, direction: -1 | 1): void => {
        const index = galleryItems.findIndex((item) => item.id === id);
        const nextIndex = index + direction;

        if (index < 0 || nextIndex < 0 || nextIndex >= galleryItems.length) {
            return;
        }

        const next = [...galleryItems];

        [next[index], next[nextIndex]] = [next[nextIndex], next[index]];
        setGalleryItems(next);
        router.post(
            galleryRoutes.reorder.url(),
            {
                image_ids: next.map((item) => item.id),
            },
            { preserveScroll: true },
        );
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
