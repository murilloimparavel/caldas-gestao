# EV-012 — Identificadores DOM em listagens autenticadas

**Classificação:** Observado  
**Data:** 08/09/2026  
**Contexto:** Chrome autenticado, conta operacional autorizada, leitura somente  
**Escopo:** listagens de Clientes, Serviços e Comandas

## Evidência

As linhas renderizadas nas listagens de Clientes, Serviços e Comandas expõem o atributo DOM `data-row-key`. Os valores foram usados somente para reconciliar registros visíveis durante a paginação autorizada.

Foi observado que:

- Clientes possuem chaves estáveis nas linhas renderizadas;
- Serviços possuem chaves estáveis nas linhas renderizadas;
- Comandas possuem chaves estáveis nas linhas renderizadas;
- Profissionais não expuseram `data-row-key` ou outro identificador estável na listagem inspecionada;
- a chave DOM não prova o nome, formato ou rota do endpoint de backend que alimenta a tela.

## Implicação

Exportadores podem preservar a chave como `source_id` quando ela estiver presente, mas devem validar unicidade e contagem por página. A origem técnica da chave permanece desconhecida até uma captura de rede sanitizada.

## Limitações

Não foram lidos cookies, tokens, headers de autorização, armazenamento local ou payloads brutos. Nenhuma introspecção GraphQL foi executada.
