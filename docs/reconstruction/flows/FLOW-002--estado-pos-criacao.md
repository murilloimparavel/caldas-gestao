# FLOW-002 — Estado pós-criação de entidades mestres

## Resultado

Comparar a interface de uma entidade nova com a de uma entidade persistida.

## Evidência observada

```text
Cliente novo
└── Cadastro habilitado

Cliente existente
├── Painel inicial
├── Cadastro
├── financeiro: débitos, créditos, cashback
├── operação: agenda, produtos, vendas, pacotes
└── relacionamento/prontuário: mensagens, notas, arquivos, anamneses, assinatura

Serviço novo
├── Cadastro
├── Configurações
└── Cashback

Serviço existente
├── abas iniciais
├── Cuidados e Retorno
├── Comissões/Auxiliares e Personalização por profissional
├── Produtos consumidos
└── Configuração fiscal
```

## Interpretação

- **Observed:** abas dependentes ficam desabilitadas no create e habilitadas no edit.
- **Inferred:** recursos dependentes exigem um identificador persistido e relacionamentos próprios.
- **Proposed:** criar a entidade raiz primeiro e configurar sub-recursos por comandos/API separados, com navegação por detalhe.

## Given/When/Then propostos

- Dada uma entidade ainda não persistida, então controles dependentes de ID/histórico permanecem indisponíveis e explicam por quê.
- Dada uma entidade persistida, quando a tela de detalhe abre, então áreas dependentes são carregadas sob demanda e autorizadas individualmente.
- Dada uma edição em sub-recurso, então falha nesse sub-recurso não deve corromper o cadastro raiz.
- Dado histórico financeiro/estoque, então exclusão da raiz não deve apagar lançamentos auditáveis.

## Limitações

- nenhum Salvar foi executado;
- não foi observado o redirecionamento imediatamente após criação;
- associação de algumas tabelas de cliente a abas específicas requer evidência visual adicional.
