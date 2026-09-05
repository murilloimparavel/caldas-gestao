# ADR-008 — Pacotes, assinaturas e retenção do relacionamento

- **Status:** aceito
- **Data:** 05/09/2026
- **Decisão:** Modelar Pacotes e Assinaturas como agregados comerciais tenant-owned, preservar o histórico por snapshots e ledgers imutáveis, e separar retenção comercial (uso do produto e relacionamento) de retenção legal (LGPD, legal hold e anonimização). O MVP não processa cobranças, pagamentos ou tentativas em gateway.

## Contexto

O Caldas Gestão precisa atender dois modelos comerciais diferentes:

- **Pacotes:** uma venda pré-paga de uma quantidade finita de sessões/itens, com validade e serviços elegíveis definidos no momento da venda.
- **Assinaturas:** um contrato recorrente interno que concede uma franquia por ciclo, registra consumo e pode ser pausado, retomado, cancelado ou encerrado por inadimplência registrada fora da plataforma.

O cadastro de um pacote ou plano pode mudar depois da venda. Recalcular uma venda histórica com o nome, preço, validade ou elegibilidade atuais quebraria recibos, auditoria e o saldo do cliente. Da mesma forma, um contador mutável não explica como o saldo foi formado, não oferece reversão segura e é frágil diante de retries ou concorrência.

O produto também precisa incentivar reativação e relacionamento sem confundir isso com a obrigação de conservar ou anonimizar dados pessoais. Comunicações comerciais dependem de consentimento/preferência e devem poder ser interrompidas; retenção legal é uma restrição de governança que pode impedir a anonimização.

## Decisões

### 1. Escopo e fronteira de cobrança

`PackageTemplate`, `CustomerPackage`, `PackageUsage`, `SubscriptionPlan` e `CustomerSubscription` são dados de negócio pertencentes ao tenant e, quando aplicável, à unidade. Todo comando deve verificar tenant, unidade, cliente, estado e permissão no backend; o frontend não define o escopo.

O módulo registra a obrigação comercial, o ciclo, o consumo, a situação operacional e referências a uma venda interna quando existirem. Ele **não** cria gateway, cartão, Pix, PaymentIntent, cobrança automática, conciliação, webhook externo ou tentativa de cobrança financeira. Uma integração de cobrança futura exigirá ADR próprio, contrato de provedor, idempotência de webhook e reconciliação.

### 2. Pacotes: snapshot na venda e elegibilidade histórica

Ao vender um pacote, `CustomerPackage` deve copiar os atributos necessários para interpretar a concessão histórica:

- nome e descrição apresentados ao cliente;
- preço e moeda em unidades inteiras;
- quantidade total concedida;
- validade e regra de expiração;
- snapshot dos serviços/itens elegíveis e suas identificações estáveis.

O vínculo com uma venda, quando presente, deve apontar para a mesma combinação de tenant, unidade e cliente. Uma venda avulsa é permitida somente quando o caso de uso explicitamente não possui venda de origem e deve continuar auditável.

O template original continua editável/inativável para novas vendas, mas não reescreve snapshots de pacotes já vendidos. Pacotes históricos não são apagados fisicamente.

### 3. Pacotes: ledger de uso, reversão e expiração

Cada concessão ou consumo relevante deve ser explicável por registros de uso. O saldo operacional pode ser materializado para leitura rápida, mas o ledger é a fonte de auditoria e deve conter ator, origem, quantidade, data, referência à venda/item quando houver e chave de idempotência.

Um consumo válido precisa:

1. pertencer ao mesmo tenant/unidade e ao pacote correto;
2. respeitar o estado do pacote e sua validade na data da operação;
3. respeitar a elegibilidade congelada na venda;
4. não exceder o saldo sob lock transacional;
5. ser repetível com a mesma chave sem duplicar o efeito.

Uma reversão é uma operação explícita, autorizada e auditada. Ela referencia um uso específico, só pode produzir efeito uma vez e não reativa um pacote cancelado ou cuja validade já terminou. O saldo restaurado permanece explicável por um evento de reversão; não se apaga o uso original.

A expiração é uma transição idempotente executada por comando agendado em lotes pequenos. Ela considera `expires_at` e o estado atual, respeita tenant/unidade e registra auditoria/outbox na mesma transação. Não há exclusão de histórico.

### 4. Assinaturas: ciclo, franquia e ledger de consumo

`CustomerSubscription` representa o contrato do cliente, enquanto cada ciclo representa uma concessão temporal independente. O sistema deve preservar:

- período do ciclo (`starts_at`, `ends_at` e timezone/regra usada);
- franquia concedida e serviços elegíveis do plano naquele ciclo;
- consumo e saldo derivados do ledger;
- estado do ciclo (`pending`, `active`, `exhausted`, `expired`, `cancelled` ou equivalente documentado);
- motivo e ator das transições.

Alterar o plano não altera ciclos históricos. A utilização é registrada como ledger imutável e vinculada ao ciclo; um retry com a mesma chave de idempotência não cria segundo consumo. O caminho concorrente bloqueia o ciclo em ordem determinística, verifica a franquia e falha de forma segura quando não há saldo.

O limite é aplicado por ciclo, não por um contador global da assinatura. A criação do ciclo seguinte e o fechamento do anterior são operações idempotentes e devem ser seguros para duas execuções concorrentes. A renovação interna significa apenas criar o próximo ciclo segundo a política contratada; não significa cobrar o cliente.

### 5. Pausa, cancelamento e situação de inadimplência

Pausar impede novas concessões/consumos conforme a política do plano e conserva o histórico. Retomar cria ou reabre somente o período permitido pela política, sem reescrever ciclos encerrados. Cancelar encerra a concessão futura e mantém o ledger.

Uma assinatura pode ter estado operacional `past_due`/`grace`/`suspended` quando o operador registrar uma situação de cobrança resolvida fora do sistema. Esses estados não disparam chamadas financeiras nem presumem que uma cobrança tenha sido tentada. Motivo, data de início/fim, ator e referência externa opcional devem ser auditáveis. A modelagem de faturas, retries de provedor e reconciliação fica fora deste ADR.

### 6. Retenção comercial e reativação

Retenção comercial é um contexto de relacionamento, separado de retenção legal. Ela pode conter:

- preferências e consentimento por canal/finalidade, com versão, origem, timestamp e revogação;
- sinais derivados de inatividade, última visita, último consumo e última interação;
- segmentos e campanhas internas;
- tarefas de reativação e resultado de contato;
- registros de envio/outbox com idempotência, tentativas e erro sanitizado.

O consentimento não é inferido de compra, login ou ausência de revogação. Campanhas devem respeitar tenant/unidade, finalidade, canal permitido e estado de consentimento; não devem expor PII em logs ou chaves de fila. A reativação é uma ação operacional mensurável, não uma alteração retroativa do histórico de clientes.

Este contexto não deve apagar, mascarar ou alterar transações para atingir métricas de retenção. Dados agregados/derivados podem ser recalculados ou expirados segundo política própria, preservando referências auditáveis mínimas.

### 7. Retenção legal, legal hold e anonimização

Retenção legal permanece governada pelo [ADR-006](ADR-006--lgpd-retencao-e-anonimizacao.md). Ela não é substituída por uma campanha, segmento ou regra de inatividade.

Antes de anonimizar ou expurgar dados pessoais, o processo deve avaliar:

- classe do dado e prazo aplicável;
- existência de obrigação legal/regulatória;
- `legal hold` no tenant, cliente ou registro;
- solicitações e base legal registradas;
- impacto sobre auditoria, ledger e relatórios históricos.

Enquanto houver legal hold, a rotina é bloqueada e o motivo/operador ficam na auditoria. Quando permitido, a anonimização remove ou substitui PII de forma irreversível, preservando apenas os fatos agregados/transacionais necessários e sem quebrar as invariantes dos ledgers. Hard delete de fatos financeiros ou de uso histórico continua proibido salvo decisão legal e arquitetural específica.

### 8. Auditoria, outbox e idempotência

Mudanças de estado, concessões, consumos, reversões, expirações, ciclos, consentimentos, campanhas e ações de legal hold produzem auditoria com ator, tenant, unidade, agregado, ação, motivo e correlação. Eventos de integração são gravados na outbox na mesma transação do fato; consumidores usam inbox/chave de origem e podem ser reexecutados sem duplicar efeitos.

Chaves de idempotência devem ser escopadas ao tenant e à operação/agregado quando aplicável. A resposta de um retry deve refletir o resultado original, sem criar uma segunda concessão, consumo, transição ou mensagem comercial.

## Alternativas consideradas

### Recalcular pacotes e assinaturas a partir do cadastro atual

Rejeitado. Mudanças de catálogo/plano alterariam o significado de vendas e consumos históricos.

### Manter somente um contador de saldo

Rejeitado. Um contador não fornece explicação, reversão auditável ou proteção suficiente contra retries e concorrência.

### Usar o mesmo modelo para retenção comercial e legal

Rejeitado. Inatividade/consentimento são políticas de relacionamento; legal hold e anonimização são controles de governança e obrigação legal, com autoridades e prazos diferentes.

### Integrar gateway durante o MVP

Rejeitado. O produto não processa o pagamento do cliente nesta fase. A integração futura precisa de decisão própria e não deve ser escondida no ciclo da assinatura.

## Consequências

### Positivas

- Histórico de preço, franquia e elegibilidade permanece interpretável.
- Uso concorrente, reversão e retries têm invariantes explícitas.
- Renovação interna pode evoluir sem acoplar o domínio a um provedor financeiro.
- Campanhas e reativação ficam governáveis por consentimento e tenant.
- Auditoria e legal hold não são enfraquecidos por rotinas comerciais.

### Custos e riscos

- Ledgers, snapshots e ciclos exigem mais tabelas, índices e testes PostgreSQL.
- Jobs de expiração, fechamento de ciclo e outbox precisam de operação idempotente e monitoramento.
- A retenção legal exige classificação de dados, revisão jurídica e owner operacional; este ADR não substitui aconselhamento jurídico.
- Uma futura cobrança externa exigirá novo ADR, contratos de webhook e reconciliação.

## Critérios para revisar este ADR

Revisar quando ocorrer uma destas condições:

- o produto passar a cobrar, emitir fatura ou integrar gateway;
- houver necessidade de múltiplas moedas, créditos monetários ou saldo financeiro;
- o plano exigir prorrata, alteração retroativa de ciclo ou regras fiscais;
- a LGPD, obrigação regulatória ou orientação jurídica alterar prazos/base legal;
- o volume exigir particionamento de ledgers, retenção por classe ou warehouse separado.

## Referências

- [ADR-002 — Fundação de dados e tenancy](ADR-002--fundacao-de-dados-e-tenancy.md)
- [ADR-003 — Categorias de comanda e fechamento consolidado](ADR-003--categorias-de-comanda-e-checkout-consolidado.md)
- [ADR-006 — Retenção legal, anonimização e legal hold](ADR-006--lgpd-retencao-e-anonimizacao.md)
- [Arquitetura de banco de dados](../architecture/database-architecture.md)
- [Operações de outbox/inbox](../operations/outbox-inbox.md)
