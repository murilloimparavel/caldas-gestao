# Sprint 2 da Fase 6: Assinaturas Recorrentes

## Visão geral

O módulo permite criar planos de assinatura e vincular um plano a um cliente. Cada assinatura preserva o preço e o ciclo contratados, mesmo se o plano for alterado depois.

## Modelo e regras

- `subscription_plans` armazena planos por tenant e unidade, seus serviços inclusos, preço em centavos e ciclo (`monthly`, `quarterly` ou `yearly`).
- `customer_subscriptions` registra a contratação, as datas de início e próxima cobrança, status, preço e ciclo contratados.
- Um cliente pode ter apenas uma assinatura vigente (`active` ou `paused`) por unidade.
- Planos inativos não podem ser contratados. Clientes e planos são sempre validados no tenant e unidade ativos.
- Pausar, retomar e cancelar respeitam as transições de estado e o controle otimista por `lock_version`.

## Auditoria e permissões

As operações são executadas como mutações operacionais e geram eventos auditáveis. As permissões são:

- `subscription.view`
- `subscription.manage`
- `subscription.subscribe`
- `subscription.cancel`

## Interface

- `/subscriptions`: lista, filtros, métricas e criação de planos.
- `/subscriptions/{subscription_plan}`: detalhes, serviços e assinantes.
- Perfil do cliente: aba de assinaturas para contratar, pausar, retomar, cancelar e consultar histórico.

## Verificação

`tests/Feature/SubscriptionTest.php` cobre snapshot contratado, isolamento tenant/unidade, duplicidade, transições e autenticação. A entrega também foi validada com Pint e `npm run build`.
