import { Head } from '@inertiajs/react';
import { AttentionQueue } from '@/features/dashboard/components/attention-queue';
import { MetricCard } from '@/features/dashboard/components/metric-card';
import { NextAppointments } from '@/features/dashboard/components/next-appointments';
import type { DashboardSnapshot } from '@/features/dashboard/types';
import { dashboard } from '@/routes';

type Props = {
    dashboard: DashboardSnapshot;
};

export default function Dashboard({ dashboard: snapshot }: Props) {
    const isEmpty = snapshot.mode === 'empty';

    return (
        <>
            <Head title="Visão de hoje" />
            <div className="dashboard-canvas flex min-h-full flex-1 flex-col gap-6 px-4 py-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] sm:px-6 lg:px-8 lg:py-8">
                <header>
                    <div className="max-w-2xl">
                        <h1 className="font-display text-3xl leading-tight font-semibold tracking-[-0.035em] text-foreground sm:text-4xl">
                            {isEmpty
                                ? 'Seu painel começa assim que os dados estiverem conectados.'
                                : 'Acompanhe o ritmo da sua operação.'}
                        </h1>
                        <p className="mt-3 max-w-xl text-sm leading-6 text-muted-foreground sm:text-base">
                            {isEmpty
                                ? 'Os indicadores, atendimentos e alertas aparecerão aqui quando houver uma fonte operacional configurada.'
                                : 'Indicadores, atendimentos e alertas são atualizados conforme as fontes operacionais são conectadas.'}
                        </p>
                    </div>
                </header>

                <section aria-labelledby="pulse-title">
                    <div className="mb-3">
                        <h2
                            id="pulse-title"
                            className="text-sm font-semibold text-foreground"
                        >
                            Pulso de hoje
                        </h2>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        {snapshot.metrics.length > 0 ? (
                            snapshot.metrics.map((metric) => (
                                <MetricCard
                                    key={metric.label}
                                    metric={metric}
                                />
                            ))
                        ) : (
                            <div className="surface-panel p-5 text-sm text-muted-foreground sm:col-span-2 xl:col-span-4">
                                Nenhum indicador disponível ainda.
                            </div>
                        )}
                    </div>
                </section>

                <section className="grid min-w-0 gap-5 md:grid-cols-[minmax(0,1.35fr)_minmax(16rem,0.8fr)] xl:grid-cols-[minmax(0,1.55fr)_minmax(320px,0.75fr)]">
                    <NextAppointments appointments={snapshot.appointments} />
                    <AttentionQueue items={snapshot.attentionItems} />
                </section>

                <section className="surface-panel flex flex-col gap-5 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                    <div>
                        <p className="text-xs font-semibold tracking-[0.14em] text-muted-foreground uppercase">
                            Resumo financeiro
                        </p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {isEmpty
                                ? 'A previsão aparecerá quando os dados financeiros estiverem conectados.'
                                : 'Dados financeiros consolidados para hoje.'}
                        </p>
                    </div>
                </section>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Visão de hoje', href: dashboard() }],
};
