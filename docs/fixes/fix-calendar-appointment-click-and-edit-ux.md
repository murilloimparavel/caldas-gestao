# Fix: Correção do Clique em Agendamentos e Reformulação da UX/UI de Edição na Agenda

Este documento registra a investigação detalhada, diagnóstico de causa-raiz, arquitetura da solução em duas sprints (correção técnica e reformulação de experiência do usuário), testes automatizados e atualização do grafo de conhecimento do projeto Caldas Gestão.

---

## 1. Contexto e Declaração do Problema

### 1.1 Sintoma Relatado
Ao utilizar a Agenda (`/calendar`) nos modos de visualização semanal e diária, usuários relataram inconsistências graves ao tentar interagir com agendamentos existentes:
- Ao clicar sobre um card de agendamento já existente (`AppointmentCard`), a janela modal abria, porém o formulário não carregava os dados do agendamento (cliente vazio, serviço não selecionado, observações em branco).
- O horário inicial ficava preso ao slot clicado da grade horária de fundo, ignorando o horário real do agendamento.
- Não era possível alterar horários ou atualizar dados com precisão, induzindo o operador a criar agendamentos duplicados ou registrar informações corrompidas.

---

### 1.2 Diagnóstico Técnico da Causa-Raiz

A investigação no código de `resources/js/components/calendar/index.tsx` e `resources/js/pages/calendar/index.tsx` identificou quatro falhas estruturais combinadas:

#### A. Vazamento de Eventos de Ponteiro (Event Bubbling)
A grade do calendário organiza os slots por coluna (`col`), onde cada coluna monitora cliques para criação rápida via `onSlotClick(date, time)`.
- O card de agendamento (`AppointmentCard`) interceptava apenas o evento `onClick` com `e.stopPropagation()`.
- Contudo, os eventos nativos de ponteiro (`onMouseDown`, `onPointerDown`, `onTouchStart`) borbulhavam (bubbling) diretamente para o contêiner da coluna de grade.
- Consequentemente, a coluna disparava a sequência:
  ```ts
  onSlotClick -> handleSlotClick({ date, time, ... }) -> setPrefilledSlot(...) -> setCreateOpen(true)
  ```
- Essa sequência configurava o estado da página como **modo de criação**, abrindo o modal com `appointment = null` e os dados do slot clicado na grade.
- Milissegundos depois, o evento `onClick` do card disparava `handleOpenAppointment(appointment) -> setEditing(appointment)`. No entanto, a corrida de eventos causava conflitos no estado do React.

#### B. Reutilização de Estado Local por Ausência de `key` Única no React
O componente `<AppointmentForm>` gerenciava seus próprios estados locais (`useState`) para campos como:
```tsx
const [selectedCustomer, setSelectedCustomer] = useState(appointment?.customer_id ?? '');
const [selectedService, setSelectedService] = useState(appointment?.service_id ?? '');
const [selectedStartsAt, setSelectedStartsAt] = useState(appointment ? ... : defaultStartsAt);
```
Como o componente `<AppointmentForm>` era renderizado sem uma propriedade `key` atrelada à entidade:
- Quando o modal transitava de `appointment = null` para `editing`, o algoritmo de reconciliação do React **não recriava** o componente, mantendo os valores originais do primeiro render nos hooks `useState`.
- Os campos de formulário permaneciam vazios ou travados no horário pré-preenchido do slot clicado.

#### C. Fragilidade no Tratamento de Datas e Timezones
As funções de manipulação de datas (`asInstant`, `partsFor`, `dateOnlyParts`, `dateTimeValue`) em `resources/js/components/calendar/index.tsx` apresentavam fragilidades:
- Strings de data enviadas pelo backend em formatos alternativos como `"YYYY-MM-DD HH:mm:ss"` (com espaço em vez de `"T"`) ou sem indicação explícita de timezone não eram parseadas adequadamente por `asInstant`, resultando em `Invalid Date` ou distorções de fuso horário.
- Em inputs HTML do tipo `datetime-local`, a falta de normalização no formato `YYYY-MM-DDTHH:mm` gerava valores incompatíveis com os seletores nativos de data e hora do navegador.

#### D. Diagnóstico de UX/UI
Além do problema funcional, a experiência da modal de edição apresentava deficiências severas:
- **Botões dispersos**: Botões para check-in de presença, abertura de comanda e cancelamento estavam posicionados abaixo do botão principal de submissão ("Salvar agendamento"), fora da ordem lógica de operação.
- **Máquina de estados não guiada**: O `<select id="status">` listava todos os status possíveis (`statusOptions`), permitindo transições ilegais para o ciclo de vida do backend (`AppointmentStatusTransition.php`), gerando erros `422 Unprocessable Content` ao salvar.
- **Ausência de bloqueio para estados terminais**: Agendamentos cancelados ou concluídos permitiam edição de campos essenciais (como serviço e profissional), gerando inconsistências contábeis e de agenda.

---

## 2. Solução Implementada

O plano de resolução foi estruturado em duas etapas complementares: **Sprint 1** (correção técnica e de ciclo de vida) e **Sprint 2** (revisão profunda de UX/UI).

---

### 2.1 Sprint 1 — Correção Técnica e Ciclo de Vida do Formulário

#### 1. Interrupção de Eventos e Camadas de Z-Index
Em `resources/js/components/calendar/index.tsx`:
- Adicionou-se `stopPropagation()` nos manipuladores `onMouseDown`, `onPointerDown` e `onTouchStart` em `AppointmentCard`, `ScheduleBlockCard` e em seus elementos contêineres nas visões semanal (`WeekCalendar`), diária (`DayAgenda`) e mensal (`MonthAgenda`).
- Elevou-se a camada de empilhamento dos agendamentos de `z-0` para `z-10`, assegurando prioridade absoluta de clique e foco sobre a malha de horários da grade.

```tsx
<div
    key={appointment.id}
    className="absolute inset-x-1 z-10 min-h-[44px]"
    style={style}
    onMouseDown={(e) => e.stopPropagation()}
    onPointerDown={(e) => e.stopPropagation()}
    onTouchStart={(e) => e.stopPropagation()}
>
    <AppointmentCard
        appointment={appointment}
        onOpen={onOpenAppointment}
        timeZone={timeZone}
    />
</div>
```

#### 2. Normalização Robusta de Datas (`asInstant` e Helpers)
Aprimorou-se a função `asInstant` para suportar tanto ISO-8601 quanto strings com espaços e datas puras:

```tsx
export function asInstant(value: string): Date {
    if (!value || typeof value !== 'string') {
        return new Date(0);
    }

    const trimmed = value.trim();

    if (/^\d{4}-\d{2}-\d{2}$/.test(trimmed)) {
        return new Date(`${trimmed}T12:00:00Z`);
    }

    const normalized = trimmed.replace(
        /^(\d{4}-\d{2}-\d{2})\s+(\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?)/,
        '$1T$2',
    );

    const hasTimezone = /(?:Z|[+-]\d{2}(?::?\d{2})?)$/i.test(normalized);
    let parsed = new Date(normalized);

    if (
        Number.isNaN(parsed.getTime()) ||
        (!hasTimezone && !normalized.includes('Z'))
    ) {
        const utcParsed = new Date(`${normalized}Z`);
        if (!Number.isNaN(utcParsed.getTime())) {
            parsed = utcParsed;
        }
    }

    return Number.isNaN(parsed.getTime()) ? new Date(0) : parsed;
}
```

Ajustou-se também `dateKey`, `dateTimeValue` e `formatDay` para reconhecer formatos mistos com regex `/\s\d{1,2}:\d{2}/`, garantindo preenchimento perfeito dos inputs `datetime-local`.

#### 3. Forçamento de Ciclo de Vida Limpo com React `key`
Em `resources/js/pages/calendar/index.tsx`, aplicou-se uma chave dinâmica que diferencia a criação de slots da edição por ID e versão de concorrência (`lock_version`):

```tsx
<AppointmentForm
    key={
        editing
            ? `appointment-edit-${editing.id}-${editing.lock_version}`
            : `appointment-create-${prefilledSlot?.date ?? ''}-${prefilledSlot?.time ?? ''}`
    }
    appointment={editing}
    customers={customers}
    defaultDurationMinutes={prefilledSlot?.durationMinutes}
    defaultProfessionalId={prefilledSlot?.professionalId}
    defaultStartsAt={
        prefilledSlot
            ? `${prefilledSlot.date}T${prefilledSlot.time}`
            : undefined
    }
    onClose={() => {
        setCreateOpen(false);
        setEditing(null);
        setPrefilledSlot(null);
        setCancelSectionOpen(false);
    }}
    professionals={professionals}
    services={services}
    unitTimezone={unitTimezone}
/>
```

Dessa forma, ao trocar de agendamento ou abrir um agendamento após clicar em um slot, o React destrói a instância anterior e inicializa o componente com dados novos e corretos.

#### 4. Estados Controlados de Formulário
Os campos `notes` e `status` foram convertidos para estados totalmente controlados (`value` + `onChange`), permitindo integração reativa com as ações da interface.

---

### 2.2 Sprint 2 — Reformulação da UX/UI da Modal de Agendamento

#### 1. Cabeçalho Resumo do Agendamento (`AppointmentSummaryHeader`)
Criou-se um componente de resumo no topo da modal para fornecer visibilidade imediata ao operador:
- **Cliente em destaque**: Avatar com inicial, nome em negrito, número de telefone e botão de atalho para conversa direta no **WhatsApp** (`https://wa.me/55...`), sanitizado e validado com DDI brasileiro automático.
- **Status visual**: Exibição do `StatusChip` semântico.
- **Serviço e Valor**: Nome do serviço acompanhado do valor formatado em reais (`R$ xx,xx`).
- **Data e Horário formatados**: Extenso em português (ex: *"Sexta-feira, 11 de setembro • 14:00 às 15:00 (60 min)"*).

#### 2. Barra de Ações Rápidas no Topo
As operações mais frequentes da rotina do salão foram alocadas em uma barra dedicada no topo do card de resumo:
- **Presença Rápida (Check-in)**: Botão com ícone de confirmação para agendamentos agendados ou confirmados, com proteção de idempotência (`X-Idempotency-Key`).
- **Comanda & Faturamento**: Exibição do status da comanda existente com link direto para faturamento (`/sales/{id}`) ou botão estilizado *"Abrir Comanda"* quando ainda não emitida.
- **Cancelar Agendamento**: Botão com acionamento retrátil (accordion), solicitando o motivo do cancelamento e confirmação explícita antes do envio.

#### 3. Máquina de Estados Guiada
O `<select id="status">` foi restrito estritamente às transições válidas mapeadas em `app/Enums/AppointmentStatusTransition.php`:
```tsx
const STATUS_TRANSITIONS: Record<AppointmentStatus, AppointmentStatus[]> = {
    draft: ['draft', 'scheduled', 'confirmed', 'cancelled'],
    scheduled: ['scheduled', 'confirmed', 'checked_in', 'no_show', 'cancelled'],
    confirmed: ['confirmed', 'checked_in', 'no_show', 'cancelled'],
    checked_in: ['checked_in', 'in_service', 'cancelled'],
    in_service: ['in_service', 'completed'],
    completed: ['completed'],
    no_show: ['no_show'],
    cancelled: ['cancelled'],
};
```
Ao selecionar o status "Cancelado" no dropdown, a UI automaticamente abre a seção retrátil de cancelamento com campo de motivo obrigatório, impedindo requisições inválidas.

#### 4. Proteção e Bloqueio Amigável para Estados Finais
- **Cancelado**: Exibe alerta visual com ícone de bloqueio, desabilita a edição dos campos e substitui o botão de salvar por um botão simples de "Fechar".
- **Concluído**: Exibe aviso de atendimento finalizado e permite apenas a alteração e salvamento de observações complementares, mantendo os dados financeiros e horários bloqueados contra edições acidentais.
- **Preservação de Integridade**: Inputs do tipo `type="hidden"` preservam os IDs essenciais no payload do formulário quando os campos correspondentes estiverem desabilitados no DOM.

#### 5. Cálculo Dinâmico de Término Previsto
Ao selecionar ou alterar o horário inicial e a duração, a interface exibe dinamicamente o horário previsto de conclusão em tempo real (ex: *"Término previsto: 15:30"*).

#### 6. Rodapé Padronizado
O rodapé do formulário foi limpo, concentrando apenas as ações essenciais via `FormActions` com labels contextuais (*"Salvar alterações"*, *"Salvar observações"* ou *"Criar agendamento"*).

---

## 3. Verificação e Testes Automatizados

### 3.1 Testes do Backend (Pest PHP)
Foram executados os testes das suítes de Agenda e Agendamentos para certificar a conformidade e ausência de regressões:

```bash
php artisan test --compact --filter=Calendar
```
**Resultado:**
```text
{"tool":"pest","result":"passed","tests":26,"passed":26,"assertions":142,"duration_ms":1272}
```

```bash
php artisan test --compact --filter=Appointment
```
**Resultado:**
```text
{"tool":"pest","result":"passed","tests":18,"passed":18,"assertions":149,"duration_ms":896}
```

Totalizando **44 testes** automatizados de agendamento e calendário totalmente aprovados.

---

### 3.2 Checagem de Tipagem e Compilação do Frontend

```bash
npm run types:check
```
**Resultado:**
```text
npm notice run tsc --noEmit
✓ 0 erros de tipagem encontrados.
```

```bash
npm run build
```
**Resultado:**
```text
✓ built in 6.83s
public/build/assets/calendar-BEFO0Bh1.js  59.64 kB │ gzip: 17.61 kB
public/build/assets/calendar-rEmpueYt.js  38.94 kB │ gzip: 12.10 kB
```
Build gerado sem falhas ou avisos impeditivos.

---

### 3.3 Atualização do Grafo de Conhecimento (Graphify)
O grafo de dependências do repositório foi atualizado após as modificações através do utilitário `graphify`:
```bash
graphify update .
```
**Resultado:**
```text
Re-extracting code files in . (no LLM needed)...
AST extraction: 164/164 uncached files (100%)
[graphify watch] Code graph updated.
```

---

## 4. Arquivos Modificados e Criados

| Arquivo | Descrição da Modificação |
| :--- | :--- |
| `resources/js/components/calendar/index.tsx` | `stopPropagation` em eventos de ponteiro, camada `z-10`, parsing robusto de datas em `asInstant`, `dateKey`, `dateTimeValue`. |
| `resources/js/pages/calendar/index.tsx` | Prop `key` dinâmica no `AppointmentForm`, componente `AppointmentSummaryHeader`, ações rápidas, WhatsApp, máquina de estados guiada, bloqueio de estados finais. |
| `docs/fixes/fix-calendar-appointment-click-and-edit-ux.md` | Registro completo da documentação do desenvolvimento e arquitetura do fix. |

---

## 5. Diretrizes para Futuras Evoluções

1. **Gestão de Eventos em Grades Complexas**: Sempre que contêineres de grade possuírem ações de clique em slots vazios, qualquer cartão ou elemento filho clicável deve barrar não apenas `click`, mas também `onMouseDown`, `onPointerDown` e `onTouchStart` para prevenir disparos em cascata.
2. **Re-montagem de Formulários no React**: Ao reutilizar modais para criar e editar registros, utilize sempre a propriedade `key` atrelada ao ID e versão de concorrência (`lock_version`) da entidade, evitando retenção indesejada de estado entre instâncias.
3. **Máquinas de Estado Frontend-Backend**: As opções de alteração de status em formulários administrativos devem refletir as transições permitidas no domínio (como `STATUS_TRANSITIONS`), garantindo que o usuário nunca seja surpreendido por erros 422 ao tentar realizar transições não suportadas.
