import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatDuration, formatMinutes } from './date-utils';

export type DragSelection = {
    date: string;
    endMinutes: number;
    professionalId?: string;
    startMinutes: number;
};

export function SelectionPopover({
    onCancel,
    onSelectBlock,
    onSelectNew,
    selection,
}: {
    onCancel: () => void;
    onSelectBlock: (selection: DragSelection) => void;
    onSelectNew: (selection: DragSelection) => void;
    selection: DragSelection;
}) {
    const minMins = Math.min(selection.startMinutes, selection.endMinutes);
    const maxMins = Math.max(selection.startMinutes, selection.endMinutes) + 15;
    const duration = maxMins - minMins;
    const label = `${formatMinutes(minMins)} - ${formatMinutes(maxMins)} • ${formatDuration(duration)}`;

    return (
        <Dialog
            open
            onOpenChange={(open) => {
                if (!open) {
                    onCancel();
                }
            }}
        >
            <DialogContent className="p-4 sm:max-w-xs">
                <DialogHeader className="space-y-1">
                    <DialogTitle className="text-center text-sm font-semibold">
                        Horário Selecionado
                    </DialogTitle>
                    <DialogDescription className="text-center text-xs font-medium text-primary">
                        {label}
                    </DialogDescription>
                </DialogHeader>
                <div className="flex flex-col gap-2 pt-2">
                    <Button
                        size="sm"
                        className="w-full justify-start gap-2"
                        onClick={() => onSelectNew(selection)}
                    >
                        <span>🗓️</span>
                        <span>Novo Agendamento</span>
                    </Button>
                    <Button
                        size="sm"
                        variant="outline"
                        className="w-full justify-start gap-2"
                        onClick={() => onSelectBlock(selection)}
                    >
                        <span>🔒</span>
                        <span>Travar Horário / Bloqueio</span>
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
