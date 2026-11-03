import { Check, Share, Smartphone, SquarePlus } from 'lucide-react';

const steps = [
    {
        icon: Share,
        title: 'Abra Compartilhar',
        description:
            'No Safari, toque no quadrado com a seta para cima. Se a barra estiver escondida, toque na parte inferior da tela.',
    },
    {
        icon: SquarePlus,
        title: 'Adicionar à Tela de Início',
        description:
            'Role as opções do menu Compartilhar até encontrar esta opção.',
    },
    {
        icon: Check,
        title: 'Confirme em Adicionar',
        description:
            'Depois, saia do Safari e abra o Caldas pelo novo ícone na Tela de Início.',
    },
];

export default function IosInstallGuide() {
    return (
        <div className="space-y-4">
            <ol
                className="space-y-3"
                aria-label="Passos para instalar no iPhone"
            >
                {steps.map(({ icon: Icon, title, description }, index) => (
                    <li
                        key={title}
                        className="flex gap-3 rounded-xl border bg-muted/25 p-4"
                    >
                        <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <Icon className="size-5" aria-hidden="true" />
                        </div>
                        <div className="space-y-1">
                            <p className="text-sm font-medium">
                                <span className="text-muted-foreground">
                                    {index + 1}.{' '}
                                </span>
                                {title}
                            </p>
                            <p className="text-sm leading-5 text-muted-foreground">
                                {description}
                            </p>
                        </div>
                    </li>
                ))}
            </ol>
            <p className="flex gap-2 text-sm leading-5 text-muted-foreground">
                <Smartphone
                    className="mt-0.5 size-4 shrink-0"
                    aria-hidden="true"
                />
                Pelo ícone instalado, volte às notificações para ativar os
                avisos. Requer iOS 16.4 ou posterior.
            </p>
        </div>
    );
}
