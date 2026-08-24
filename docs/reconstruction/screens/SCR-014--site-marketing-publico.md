# SCR-014 — Site público e jornada de marketing

## Contexto

- **Rotas:** home, recursos, segmentos, preços, suporte, institucional, blog e conversão
- **Papel:** visitante anônimo
- **Viewports:** 1710 × 985 e 390 × 844
- **Evidência:** EV-009; UX-039 a UX-046

## Objetivo do visitante

Entender rapidamente para quem é o produto, quais resultados promete, o que oferece, quanto custa e qual próximo passo reduz melhor seu risco: testar, falar com vendas ou agendar demonstração.

## Arquitetura da jornada

```text
Busca / conteúdo / segmento
          ↓
  Home ou landing segmentada
          ↓
 Recursos ↔ Preços ↔ Suporte/prova
          ↓
  Criar conta | Falar com vendas | Demonstração
```

## Hierarquia observada

1. navegação global;
2. promessa de crescimento;
3. produto integrado e capacidades;
4. redução de risco: migração, suporte e confiança;
5. extensibilidade e adicionais;
6. prova social e conteúdo;
7. FAQ;
8. CTA final e rodapé.

## Estados observados

| Estado | Resultado | Evidência |
|---|---|---|
| home desktop | narrativa longa e dois CTAs primários | EV-009 |
| menu Segmentos aberto | quatro landing pages e item de redes/franquias | EV-009 |
| catálogo | cards por grupo e links para detalhes | EV-009 |
| preços | três planos, alternância e comparação | EV-009 |
| conversão | conteúdo da página + formulário externo incorporado | EV-009 |
| mobile | header compacto, coluna única e CTA fixo | EV-009 |
| formulário enviado/erro/sucesso | não testado | — |

## Proposta independente

### Navegação

- Produto, Soluções, Preços, Conteúdo e Empresa;
- Entrar como ação discreta;
- Começar grátis como CTA primário;
- Falar com especialista como alternativa consultiva;
- páginas de segmento acessíveis por submenu nomeado e teclado.

### Home

1. hero com problema, resultado e demonstração visual verdadeira;
2. prova curta e verificável;
3. três fluxos centrais do produto, não lista de dezenas de features;
4. resultados por papel/segmento;
5. visão integrada do ciclo cliente → agenda → atendimento → recebimento → retenção;
6. diferenciais de confiança e implantação;
7. planos resumidos;
8. conteúdo e CTA final.

### Segmentos

Cada landing precisa mudar mais que o substantivo:

- job principal;
- fluxo operacional específico;
- requisitos e riscos do segmento;
- prova/case correspondente;
- conjunto de módulos recomendado;
- onboarding e migração pertinentes.

### Recursos

Cada recurso deve apresentar problema, fluxo, interface própria, integrações, pré-requisitos, plano/entitlement, privacidade e próximos passos. Claims comerciais devem ligar a evidência verificável ou ser escritos como benefício potencial.

### Preços

- preço e unidade inequívocos;
- mensal/anual com economia calculada;
- legenda clara para incluso, adicional e indisponível;
- comparação mobile por seleção de dois planos ou accordion por categoria;
- custos variáveis, limites e onboarding explícitos;
- CTA coerente com cada plano;
- FAQ totalmente localizada.

## Critérios de aceitação

- Em cinco segundos, o hero comunica público, problema, resultado e próxima ação.
- Todos os CTAs equivalentes usam a mesma linguagem e destino previsível.
- Landing segmentada contém pelo menos um fluxo e prova específicos do segmento.
- Nenhuma promessa quantitativa é publicada sem fonte, data e responsável.
- Plano informa preço-base, periodicidade, limites, adicionais e condições relevantes.
- Comparação funciona por teclado e em 390 px sem recorte ou gesto oculto.
- Formulário informa finalidade, campos, consentimento, privacidade, sucesso e alternativa de contato.
- Conteúdo acima da dobra não depende de animação para existir visualmente.
- `prefers-reduced-motion` preserva toda a informação e ordem de leitura.
- Cada página possui title/H1 únicos, idioma consistente e revisão editorial.
- Core Web Vitals e SEO técnico têm orçamento e monitoramento.

## Desconhecidos

- campos, validação e integrações dos formulários externos;
- funil e conversão reais;
- regras de trial, cobrança, cancelamento e upgrade;
- disponibilidade regional e por plano;
- fonte dos números de adoção e depoimentos;
- comportamento dos downloads e aplicativos;
- landing própria de redes/franquias;
- performance em rede móvel real.
