# Fix: Validação Booleana de `reminder_enabled` no Agendamento da Agenda

Este documento registra a investigação, causa-raiz, arquitetura de correção, testes automatizados e aprendizados derivados via análise de grafo (Graphify) para o bug de validação do campo `reminder_enabled` na criação e edição de agendamentos no Caldas Gestão.

---

## 1. Contexto e Declaração do Problema

### Sintoma
Ao tentar salvar ou atualizar um agendamento através do modal de agendamento na página da Agenda (`/calendar`), com a opção **"Enviar lembrete ao cliente"** selecionada (ou alternada), a requisição falhava com erro de validação HTTP 422:
```text
The reminder enabled field must be true or false.
```

### Causa-Raiz (Root Cause)
O comportamento decorre de duas características fundamentais da submissão padrão de formulários HTML via `FormData`:

1. **Envio de `"on"` quando marcado**:
   Um elemento HTML `<input type="checkbox" name="reminder_enabled" />` sem um atributo `value` explícito envia por padrão a string `"on"` quando marcado. No backend, a regra de validação do Laravel configurada em `AppointmentRequest`:
   ```php
   'reminder_enabled' => ['sometimes', 'boolean']
   ```
   aceita valores como `true`, `false`, `1`, `0`, `"1"`, `"0"` — porém, em certas configurações ou versões do validador e cast de requisições, a validação estrita falha para `"on"`, resultando no erro de validação informado pelo usuário.

2. **Omissão completa no FormData quando desmarcado**:
   Pela especificação HTML/HTTP, checkboxes desmarcados **não são incluídos** no payload serializado via `FormData`.
   Como a regra continha `'sometimes'`, quando o usuário desmarcava o checkbox, o campo `reminder_enabled` simplesmente não era enviado na requisição HTTP. Consequentemente:
   - `$request->validated()` não continha a chave `reminder_enabled`;
   - O Eloquent não atualizava o campo para `false`;
   - O agendamento permanecia com `reminder_enabled = true` no banco de dados, impossibilitando desativar o lembrete na edição.

---

## 2. Arquitetura e Solução Implementada

Para garantir resiliência ponta a ponta (tanto no nível de interface do usuário quanto na camada de integridade da API/backend), foi implementada uma solução em duas camadas:

### 2.1 Backend (`app/Http/Requests/AppointmentRequest.php`)
No Form Request, implementou-se o hook `prepareForValidation()` para normalizar valores booleanos antes que as regras de validação sejam avaliadas:

```php
protected function prepareForValidation(): void
{
    if ($this->has('reminder_enabled')) {
        $this->merge([
            'reminder_enabled' => $this->boolean('reminder_enabled'),
        ]);
    }

    if ($this->has('fit_in')) {
        $this->merge([
            'fit_in' => $this->boolean('fit_in'),
        ]);
    }
}
```

- `$this->boolean('reminder_enabled')` utiliza o helper nativo do Laravel (`Illuminate\Http\Request::boolean`), que converte com segurança `"on"`, `"1"`, `1`, `"true"`, `true` para `true`, e `"off"`, `"0"`, `0`, `"false"`, `false` para `false`.
- A mesma normalização foi estendida preventivamente ao campo `fit_in` ("Encaixe"), eliminando potenciais erros análogos.

### 2.2 Frontend (`resources/js/pages/calendar/index.tsx`)
No formulário do modal de agendamento (`AppointmentForm`), substituiu-se o checkbox não-controlado por um estado controlado e um input oculto explícito:

1. **Estado controlado do React**:
   ```tsx
   const [reminderEnabled, setReminderEnabled] = useState(
       appointment?.reminder_enabled ?? true,
   );
   ```

2. **Input oculto (`type="hidden"`) e Checkbox desacoplado do `name`**:
   ```tsx
   <div className="flex items-start gap-3 rounded-xl border border-border bg-muted/20 px-3.5 py-3 md:col-span-2">
       <input
           type="hidden"
           name="reminder_enabled"
           value={reminderEnabled ? '1' : '0'}
       />
       <input
           id="reminder_enabled"
           type="checkbox"
           checked={reminderEnabled}
           onChange={(e) =>
               setReminderEnabled(e.target.checked)
           }
           className="size-4 accent-primary"
       />
       <label htmlFor="reminder_enabled" className="text-xs text-muted-foreground">
           Enviar lembrete ao cliente
       </label>
   </div>
   ```

**Vantagens da abordagem**:
- O `input[type="hidden"]` garante que a chave `reminder_enabled` **sempre** estará presente no `FormData`, com valor inequívoco `'1'` ou `'0'`.
- Ao desmarcar a opção, o valor `'0'` é explicitamente enviado, permitindo ao backend atualizar a coluna para `false`.
- O checkbox visual não possui o atributo `name`, prevenindo conflito de chaves duplicadas no payload.

---

## 3. Verificação e Testes Automatizados

Foram adicionados testes de feature cobrindo a normalização em criação e atualização em `tests/Feature/CalendarAppointmentTest.php`:

```php
it('normalizes reminder_enabled boolean input on create and update', function () {
    [$owner, $tenantId, $unitId, $customer, $professional, $service] = calendarWorkspace();
    $headers = $this->withHeader('X-Tenant-Id', $tenantId)->withHeader('X-Unit-Id', $unitId)->actingAs($owner);

    // Teste 1: Criação enviando 'on' (comportamento padrão do browser)
    $payloadWithOn = [
        'customer_id' => $customer->getKey(),
        'service_id' => $service->getKey(),
        'professional_id' => $professional->getKey(),
        'starts_at' => '2030-02-10 10:00',
        'duration_minutes' => 60,
        'reminder_enabled' => 'on',
    ];

    $headers->post(route('appointments.store'), $payloadWithOn)->assertRedirect();
    $firstAppointment = Appointment::query()->firstOrFail();
    expect($firstAppointment->reminder_enabled)->toBeTrue();

    // Teste 2: Criação enviando '0'
    $payloadWithZero = [
        'customer_id' => $customer->getKey(),
        'service_id' => $service->getKey(),
        'professional_id' => $professional->getKey(),
        'starts_at' => '2030-02-10 12:00',
        'duration_minutes' => 60,
        'reminder_enabled' => '0',
    ];

    $headers->post(route('appointments.store'), $payloadWithZero)->assertRedirect();
    $secondAppointment = Appointment::query()->where('id', '!=', $firstAppointment->getKey())->firstOrFail();
    expect($secondAppointment->reminder_enabled)->toBeFalse();

    // Teste 3: Atualização alterando de true para false via '0'
    $headers->put(route('appointments.update', $firstAppointment), [
        'customer_id' => $customer->getKey(),
        'service_id' => $service->getKey(),
        'professional_id' => $professional->getKey(),
        'starts_at' => '2030-02-10 10:00',
        'duration_minutes' => 60,
        'reminder_enabled' => '0',
        'lock_version' => 0,
    ])->assertRedirect();

    expect($firstAppointment->fresh()->reminder_enabled)->toBeFalse();
});
```

### Execução da Suíte de Testes
A suíte completa de testes da agenda foi executada via Pest:
```bash
php artisan test --compact tests/Feature/CalendarAppointmentTest.php
```
**Resultado:**
```text
{"tool":"pest","result":"passed","tests":9,"passed":9,"assertions":42,"duration_ms":1069}
```
Todos os 9 testes passaram sem nenhuma regressão.

---

## 4. Insights da Análise de Grafo (Graphify)

A análise do grafo de dependências e dos nós do sistema evidenciou padrões sistêmicos de submissão de campos booleanos na aplicação:

1. **Padrão de Referência em `online-booking/index.tsx`**:
   O módulo de agendamento online já implementava o padrão recomendado para envio de booleanos via formulário web:
   ```tsx
   <input
       type="hidden"
       name="online_booking_enabled"
       value="0"
   />
   ```
   Isso assegura que o fallback `'0'` seja serializado caso o checkbox correspondente esteja desmarcado.

2. **Vulnerabilidade Semelhante Mapeada em `CommissionRuleRequest.php` / `commissions/index.tsx`**:
   A investigação revelou uma ocorrência idêntica no módulo financeiro de comissões:
   - Em `resources/js/pages/finance/commissions/index.tsx`:
     ```tsx
     <input
         type="checkbox"
         id="is_active"
         name="is_active"
         value="1"
         defaultChecked={editingRule ? editingRule.is_active : true}
     />
     ```
   - Em `app/Http/Requests/CommissionRuleRequest.php`:
     ```php
     'is_active' => ['nullable', 'boolean'],
     ```
   Ao desmarcar "Regra ativa", o navegador omite `is_active` do `FormData`. Como `CommissionRuleRequest` não possui `prepareForValidation()` com fallback booleano nem há um input hidden correspondente, a regra de comissão não é desativada na edição.
   
   **Recomendação Futura**: Replicar a mesma padronização adotada na Agenda (`prepareForValidation` + input oculto `'0'`/`'1'`) para o módulo de comissões e demais formulários com checkboxes.
