import { ImagePlus, Trash2, Upload } from 'lucide-react';
import React, { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export interface ImageUploaderProps {
    value?: string | File | null;
    onChange?: (file: File | null) => void;
    onRemove?: () => void;
    name?: string;
    accept?: string;
    disabled?: boolean;
    error?: string;
    className?: string;
    aspectRatio?: 'square' | 'video' | 'auto';
    previewHeight?: string;
}

export function ImageUploader({
    value,
    onChange,
    onRemove,
    name = 'photo',
    accept = '.jpg,.jpeg,.png,.webp',
    disabled = false,
    error,
    className,
    aspectRatio = 'square',
    previewHeight,
}: ImageUploaderProps) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);
    const [previewError, setPreviewError] = useState(false);

    useEffect(() => {
        if (!value) {
            setPreviewUrl(null);
            setPreviewError(false);
            return;
        }

        if (typeof value === 'string') {
            setPreviewUrl(value);
            setPreviewError(false);
            return;
        }

        if (value instanceof File) {
            setPreviewError(false);
            const url = URL.createObjectURL(value);
            setPreviewUrl(url);
            return () => {
                URL.revokeObjectURL(url);
            };
        }
    }, [value]);

    const handleFileChange = (event: React.ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0] ?? null;
        if (onChange) {
            onChange(file);
        }
    };

    const handleClear = (event: React.MouseEvent) => {
        event.stopPropagation();
        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
        if (onRemove) {
            onRemove();
        } else if (onChange) {
            onChange(null);
        }
    };

    const handleClickTrigger = () => {
        if (!disabled) {
            fileInputRef.current?.click();
        }
    };

    const aspectRatioClass =
        aspectRatio === 'square'
            ? 'aspect-square'
            : aspectRatio === 'video'
              ? 'aspect-video'
              : '';

    return (
        <div className={cn('space-y-2', className)}>
            <input
                ref={fileInputRef}
                type="file"
                name={name}
                accept={accept}
                disabled={disabled}
                onChange={handleFileChange}
                className="sr-only"
                aria-label="Upload de imagem"
            />

            <div
                onClick={handleClickTrigger}
                className={cn(
                    'group relative flex cursor-pointer flex-col items-center justify-center overflow-hidden rounded-xl border-2 border-dashed border-muted-foreground/25 bg-muted/30 p-4 transition-colors hover:border-primary/50 hover:bg-muted/50 focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-2',
                    disabled && 'pointer-events-none opacity-60',
                    error && 'border-destructive/50 bg-destructive/5',
                    aspectRatioClass,
                )}
                style={previewHeight ? { height: previewHeight } : undefined}
            >
                {previewUrl && !previewError ? (
                    <div className="relative size-full overflow-hidden rounded-lg">
                        <img
                            src={previewUrl}
                            alt="Preview da foto"
                            className="size-full object-cover transition-transform duration-300 group-hover:scale-105"
                            onError={() => setPreviewError(true)}
                        />
                        <div className="absolute inset-0 bg-black/40 opacity-0 transition-opacity group-hover:opacity-100 flex items-center justify-center gap-2">
                            <Button
                                type="button"
                                variant="secondary"
                                size="sm"
                                disabled={disabled}
                                onClick={(e) => {
                                    e.stopPropagation();
                                    handleClickTrigger();
                                }}
                                className="h-8 px-2.5 text-xs font-medium"
                            >
                                <Upload className="mr-1 size-3.5" />
                                Alterar
                            </Button>
                            <Button
                                type="button"
                                variant="destructive"
                                size="sm"
                                disabled={disabled}
                                onClick={handleClear}
                                className="h-8 px-2.5 text-xs font-medium"
                            >
                                <Trash2 className="mr-1 size-3.5" />
                                Remover
                            </Button>
                        </div>
                    </div>
                ) : (
                    <div className="flex flex-col items-center justify-center text-center">
                        <div className="mb-2 flex size-10 items-center justify-center rounded-full bg-background shadow-xs text-muted-foreground group-hover:text-primary">
                            <ImagePlus className="size-5" />
                        </div>
                        <p className="text-xs font-medium text-foreground">
                            {previewError
                                ? 'Imagem indisponível — clique para substituir'
                                : 'Clique para selecionar uma imagem'}
                        </p>
                        <p className="mt-1 text-[11px] text-muted-foreground">
                            JPG, PNG ou WEBP
                        </p>
                    </div>
                )}
            </div>

            {error ? (
                <p className="text-xs font-medium text-destructive">{error}</p>
            ) : null}
        </div>
    );
}
