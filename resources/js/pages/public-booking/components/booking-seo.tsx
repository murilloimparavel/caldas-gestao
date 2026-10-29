import { Head } from '@inertiajs/react';
import type { Unit } from '../show';

export function BookingSeo({ unit }: { unit: Unit }) {
    const meta = unit.public_meta;
    const title = meta?.title ?? unit.seo?.title ?? `Agendar · ${unit.name}`;
    const description =
        meta?.description ??
        unit.seo?.description ??
        unit.description ??
        `Agende seu horário em ${unit.name}.`;
    const canonical = meta ? meta.canonical_url : unit.canonical_url;
    const image = meta ? meta.image : unit.cover_image_url;
    const robots =
        meta?.robots ??
        (unit.is_preview ? 'noindex,nofollow,noarchive' : 'index,follow');

    return (
        <Head title={title}>
            <meta
                head-key="description"
                name="description"
                content={description}
            />
            <meta head-key="robots" name="robots" content={robots} />
            {canonical ? (
                <link head-key="canonical" rel="canonical" href={canonical} />
            ) : null}
            <meta head-key="og:title" property="og:title" content={title} />
            <meta
                head-key="og:description"
                property="og:description"
                content={description}
            />
            <meta head-key="og:type" property="og:type" content="website" />
            <meta head-key="og:locale" property="og:locale" content="pt_BR" />
            {canonical ? (
                <meta head-key="og:url" property="og:url" content={canonical} />
            ) : null}
            {image ? (
                <meta head-key="og:image" property="og:image" content={image} />
            ) : null}
            <meta
                head-key="twitter:card"
                name="twitter:card"
                content={image ? 'summary_large_image' : 'summary'}
            />
            <meta
                head-key="twitter:title"
                name="twitter:title"
                content={title}
            />
            <meta
                head-key="twitter:description"
                name="twitter:description"
                content={description}
            />
            {image ? (
                <meta
                    head-key="twitter:image"
                    name="twitter:image"
                    content={image}
                />
            ) : null}
            {meta?.structured_data ? (
                <script head-key="business-schema" type="application/ld+json">
                    {JSON.stringify(meta.structured_data).replace(
                        /</g,
                        '\\u003c',
                    )}
                </script>
            ) : null}
        </Head>
    );
}
