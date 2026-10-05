import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    Bot,
    Check,
    Clock3,
    LockKeyhole,
    Plus,
    Send,
    ShieldCheck,
    Sparkles,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import assistant from '@/routes/assistant';
import integrationProposals from '@/routes/integration-proposals';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import type { SharedPageProps } from '@/types/ui';

type Message = {
    id: string;
    role: 'user' | 'assistant';
    content: string;
    createdAt: string | null;
    redacted: boolean;
};

type Conversation = {
    id: string;
    title: string | null;
    expiresAt: string;
    messages: Message[];
};

type ConversationSummary = {
    id: string;
    title: string;
    lastMessageAt: string | null;
};

type AssistantPageProps = {
    conversation: Conversation | null;
    conversations: ConversationSummary[];
    workspace: {
        tenantName: string;
        unitName: string | null;
    };
    limits: {
        maxPromptLength: number;
        retentionDays: number;
        available: boolean;
    };
};

export default function AssistantPage({
    conversation,
    conversations,
    workspace,
    limits,
}: AssistantPageProps) {
    const [draft, setDraft] = useState('');
    const { flash } = usePage<SharedPageProps>().props;

    return (
        <>
            <Head title="Assistente IA" />

            <div className="min-h-[calc(100vh-5rem)] bg-[radial-gradient(circle_at_top_right,rgba(190,120,64,0.18),transparent_35%),linear-gradient(135deg,#181211_0%,#241917_48%,#32201c_100%)] px-4 py-6 text-stone-100 sm:px-6 lg:px-8">
                <div className="mx-auto grid max-w-7xl gap-5 lg:grid-cols-[280px_minmax(0,1fr)]">
                    <aside className="flex flex-col gap-4">
                        <div className="rounded-2xl border border-amber-200/15 bg-black/20 p-5 shadow-2xl shadow-black/20 backdrop-blur">
                            <div className="mb-6 flex items-start justify-between gap-3">
                                <div>
                                    <p className="text-[10px] font-semibold tracking-[0.28em] text-amber-200/70 uppercase">
                                        Oficina IA
                                    </p>
                                    <h1 className="mt-2 text-2xl font-semibold tracking-tight text-white">
                                        Assistente
                                    </h1>
                                </div>
                                <div className="rounded-xl border border-amber-200/20 bg-amber-200/10 p-2.5 text-amber-200">
                                    <Bot
                                        className="size-5"
                                        aria-hidden="true"
                                    />
                                </div>
                            </div>
                            <p className="text-sm leading-6 text-stone-300">
                                Configure o catálogo e a presença online com
                                ajuda contextual para administradores.
                            </p>
                            <Form
                                {...assistant.conversations.store.form()}
                                className="mt-5"
                            >
                                <Button
                                    type="submit"
                                    className="w-full bg-amber-200 text-stone-950 hover:bg-amber-100"
                                >
                                    <Plus
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Nova conversa
                                </Button>
                            </Form>
                        </div>

                        <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-3">
                            <div className="flex items-center justify-between px-2 pb-2">
                                <span className="text-xs font-semibold tracking-[0.2em] text-stone-400 uppercase">
                                    Conversas recentes
                                </span>
                                <Clock3
                                    className="size-4 text-stone-500"
                                    aria-hidden="true"
                                />
                            </div>
                            <div className="space-y-1">
                                {conversations.length === 0 ? (
                                    <p className="px-2 py-5 text-sm text-stone-500">
                                        Comece uma conversa para ver seu
                                        histórico aqui.
                                    </p>
                                ) : (
                                    conversations.map((item) => (
                                        <Link
                                            key={item.id}
                                            href={assistant.show(item)}
                                            className={`block rounded-xl px-3 py-3 text-sm transition ${item.id === conversation?.id ? 'bg-amber-200/15 text-amber-100' : 'text-stone-300 hover:bg-white/[0.06] hover:text-white'}`}
                                        >
                                            <span className="block truncate font-medium">
                                                {item.title}
                                            </span>
                                            <span className="mt-1 block text-xs text-stone-500">
                                                {item.lastMessageAt
                                                    ? new Date(
                                                          item.lastMessageAt,
                                                      ).toLocaleDateString(
                                                          'pt-BR',
                                                      )
                                                    : 'Agora'}
                                            </span>
                                        </Link>
                                    ))
                                )}
                            </div>
                        </div>
                    </aside>

                    <main className="flex min-h-[72vh] flex-col overflow-hidden rounded-3xl border border-amber-200/15 bg-[#f5eee5] text-stone-900 shadow-2xl shadow-black/30">
                        <header className="flex flex-col gap-4 border-b border-stone-900/10 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-8">
                            <div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge
                                        variant="outline"
                                        className={
                                            limits.available
                                                ? 'border-emerald-700/20 bg-emerald-100 text-emerald-800'
                                                : 'border-amber-700/20 bg-amber-100 text-amber-800'
                                        }
                                    >
                                        <span
                                            className={`size-1.5 rounded-full ${limits.available ? 'bg-emerald-600' : 'bg-amber-600'}`}
                                        />
                                        {limits.available
                                            ? 'Assistente disponível'
                                            : 'Assistente indisponível'}
                                    </Badge>
                                    <span className="text-xs text-stone-500">
                                        {workspace.unitName ??
                                            workspace.tenantName}
                                    </span>
                                </div>
                                <h2 className="mt-3 text-2xl font-semibold tracking-tight text-stone-950 sm:text-3xl">
                                    {conversation?.title ??
                                        'O que vamos organizar hoje?'}
                                </h2>
                                <p className="mt-1 max-w-2xl text-sm text-stone-600">
                                    Catálogo, setup e propostas de configuração.
                                    Toda alteração fica pendente para revisão
                                    humana.
                                </p>
                            </div>
                            {conversation && (
                                <Form {...assistant.destroy.form(conversation)}>
                                    <Button
                                        type="submit"
                                        variant="ghost"
                                        size="sm"
                                        className="self-start text-stone-500 hover:bg-red-100 hover:text-red-700 sm:self-center"
                                    >
                                        <Trash2
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Excluir
                                    </Button>
                                </Form>
                            )}
                        </header>

                        {(flash.error ||
                            flash.warning ||
                            (flash.proposalIds?.length ?? 0) > 0) && (
                            <div className="grid gap-2 border-b border-stone-900/10 bg-amber-50 px-5 py-4 sm:px-8">
                                {flash.error && (
                                    <p className="text-sm font-medium text-red-700">
                                        {flash.error}
                                    </p>
                                )}
                                {flash.warning && (
                                    <p className="text-sm text-amber-800">
                                        {flash.warning}
                                    </p>
                                )}
                                {flash.proposalIds?.length ? (
                                    <div className="rounded-xl border border-amber-700/20 bg-white/75 p-3">
                                        <p className="text-sm font-semibold text-stone-900">
                                            Proposta pendente de revisão
                                        </p>
                                        <p className="mt-1 text-xs leading-5 text-stone-600">
                                            A alteração não foi aplicada. Revise
                                            os detalhes e confirme com sua
                                            passkey.
                                        </p>
                                        <div className="mt-2 flex flex-wrap gap-2">
                                            {flash.proposalIds.map(
                                                (proposalId) => (
                                                    <Link
                                                        key={proposalId}
                                                        href={integrationProposals.show(
                                                            proposalId,
                                                        )}
                                                        className="inline-flex items-center rounded-lg bg-stone-900 px-3 py-2 text-xs font-semibold text-amber-100 transition hover:bg-stone-800"
                                                    >
                                                        Revisar proposta
                                                    </Link>
                                                ),
                                            )}
                                        </div>
                                    </div>
                                ) : null}
                            </div>
                        )}

                        <section className="flex flex-1 flex-col gap-6 overflow-y-auto px-5 py-6 sm:px-8">
                            {conversation?.messages.length ? (
                                conversation.messages.map((message) => (
                                    <article
                                        key={message.id}
                                        className={`flex gap-3 ${message.role === 'user' ? 'justify-end' : 'justify-start'}`}
                                    >
                                        {message.role === 'assistant' && (
                                            <div className="mt-1 flex size-8 shrink-0 items-center justify-center rounded-xl bg-stone-900 text-amber-200 shadow-sm">
                                                <Sparkles
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                            </div>
                                        )}
                                        <div
                                            className={`max-w-2xl rounded-2xl px-4 py-3 text-sm leading-6 shadow-sm ${message.role === 'user' ? 'rounded-br-md bg-stone-900 text-stone-100' : 'rounded-bl-md border border-stone-900/10 bg-white text-stone-700'}`}
                                        >
                                            <p className="whitespace-pre-wrap">
                                                {message.content}
                                            </p>
                                            {message.redacted && (
                                                <p className="mt-2 flex items-center gap-1 text-[11px] text-amber-700">
                                                    <ShieldCheck
                                                        className="size-3"
                                                        aria-hidden="true"
                                                    />
                                                    Um dado sensível foi
                                                    ocultado antes do envio.
                                                </p>
                                            )}
                                        </div>
                                    </article>
                                ))
                            ) : (
                                <div className="m-auto grid max-w-xl gap-5 text-center">
                                    <div className="mx-auto flex size-16 items-center justify-center rounded-3xl bg-stone-900 text-amber-200 shadow-xl shadow-stone-900/20">
                                        <Sparkles
                                            className="size-7"
                                            aria-hidden="true"
                                        />
                                    </div>
                                    <div>
                                        <p className="text-lg font-semibold text-stone-950">
                                            Seu copiloto de configuração
                                        </p>
                                        <p className="mt-2 text-sm leading-6 text-stone-600">
                                            Pergunte quais serviços estão
                                            ativos, confira a prontidão do
                                            agendamento ou peça uma proposta de
                                            ajuste.
                                        </p>
                                    </div>
                                    <div className="grid gap-2 text-left sm:grid-cols-3">
                                        {[
                                            'Quais serviços estão ativos?',
                                            'Como está o setup?',
                                            'Proponha um novo serviço',
                                        ].map((prompt) => (
                                            <div
                                                key={prompt}
                                                className="rounded-xl border border-stone-900/10 bg-white/70 p-3 text-xs text-stone-600"
                                            >
                                                “{prompt}”
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </section>

                        <footer className="border-t border-stone-900/10 bg-white/45 px-5 py-4 sm:px-8">
                            {conversation ? (
                                <Form
                                    {...assistant.messages.store.form(
                                        conversation,
                                    )}
                                    options={{ preserveScroll: true }}
                                    resetOnSuccess
                                    onSuccess={() => setDraft('')}
                                    className="relative"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <Textarea
                                                name="content"
                                                value={draft}
                                                onChange={(event) =>
                                                    setDraft(event.target.value)
                                                }
                                                maxLength={
                                                    limits.maxPromptLength
                                                }
                                                placeholder="Ex.: confira se o catálogo está pronto para o agendamento online..."
                                                className="min-h-24 resize-none border-stone-900/15 bg-white pr-16 text-stone-900 placeholder:text-stone-400 focus-visible:ring-amber-700/30"
                                                aria-label="Mensagem para o assistente"
                                                disabled={
                                                    processing ||
                                                    !limits.available
                                                }
                                            />
                                            <Button
                                                type="submit"
                                                size="icon"
                                                disabled={
                                                    processing ||
                                                    !limits.available ||
                                                    draft.trim().length === 0
                                                }
                                                className="absolute right-3 bottom-3 size-10 rounded-xl bg-stone-900 text-amber-200 hover:bg-stone-800"
                                            >
                                                <Send
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                                <span className="sr-only">
                                                    Enviar mensagem
                                                </span>
                                            </Button>
                                            {errors.content && (
                                                <p className="mt-2 text-xs text-red-700">
                                                    {errors.content}
                                                </p>
                                            )}
                                        </>
                                    )}
                                </Form>
                            ) : (
                                <div className="flex items-center justify-between gap-4 rounded-xl border border-dashed border-stone-900/20 bg-white/50 px-4 py-3 text-sm text-stone-600">
                                    <span>
                                        Abra uma nova conversa para começar.
                                    </span>
                                    <LockKeyhole
                                        className="size-4 shrink-0 text-stone-500"
                                        aria-hidden="true"
                                    />
                                </div>
                            )}
                            {!limits.available && (
                                <p className="mt-3 text-xs text-amber-800">
                                    O assistente está indisponível até a
                                    configuração do provedor pela equipe de
                                    implantação.
                                </p>
                            )}
                            <section
                                aria-labelledby="assistant-privacy-heading"
                                className="mt-4 rounded-xl border border-amber-800/20 bg-amber-50 px-4 py-3 text-stone-700"
                            >
                                <h3
                                    id="assistant-privacy-heading"
                                    className="text-xs font-semibold text-stone-900"
                                >
                                    Privacidade e uso de dados
                                </h3>
                                <p className="mt-1 text-xs leading-5">
                                    O conteúdo desta conversa e o contexto
                                    aprovado do catálogo e do setup são
                                    transmitidos à Groq para inferência. O
                                    histórico fica retido na aplicação Caldas
                                    por até {limits.retentionDays} dias; esse
                                    prazo é separado da retenção da Groq, que
                                    depende da conta, dos controles disponíveis
                                    e dos termos do provedor. Consulte as{' '}
                                    <a
                                        href="https://console.groq.com/docs/your-data"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="font-medium underline underline-offset-2"
                                    >
                                        informações oficiais da Groq sobre uso
                                        de dados
                                    </a>
                                    .
                                </p>
                                <p className="mt-1 text-xs leading-5">
                                    A ocultação automática identifica apenas
                                    alguns padrões comuns e não é completa. Não
                                    informe dados de clientes, dados pessoais ou
                                    informações sensíveis.
                                </p>
                            </section>
                            <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-stone-500">
                                <span className="flex items-center gap-1">
                                    <LockKeyhole
                                        className="size-3"
                                        aria-hidden="true"
                                    />{' '}
                                    Dados limitados ao catálogo e setup
                                </span>
                                <span className="flex items-center gap-1">
                                    <Check
                                        className="size-3"
                                        aria-hidden="true"
                                    />{' '}
                                    Histórico na aplicação Caldas retido por{' '}
                                    {limits.retentionDays} dias
                                </span>
                            </div>
                        </footer>
                    </main>
                </div>
            </div>
        </>
    );
}
