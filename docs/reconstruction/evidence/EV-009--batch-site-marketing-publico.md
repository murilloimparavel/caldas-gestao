# EV-009 — Site e páginas públicas de marketing

## Escopo

- data: 24/08/2026;
- alvo: `https://www.belasis.com.br/`;
- acesso: público, sem autenticação;
- viewports: desktop 1710 × 985 e mobile 390 × 844;
- mutações: nenhuma;
- formulários: apenas presença/estrutura externa observada; nenhum campo preenchido ou enviado;
- screenshots: usadas transitoriamente para inspeção; ativos e marca não foram persistidos.

## Rotas observadas

### Institucionais e conversão

- `/` — home;
- `/recursos` — catálogo de capacidades;
- `/precos` — planos, comparação e FAQ;
- `/suporte` — suporte, base de conhecimento e acompanhamento;
- `/sobre-nos` — narrativa institucional, diferenciais e carreira;
- `/falar-com-vendas` — venda assistida;
- `/criar-conta` — aquisição self-service;
- `/agendar-demonstracao` — demonstração guiada;
- `/downloads` — aplicativos desktop/mobile;
- `/blog` — conteúdo de aquisição/educação.

### Segmentos

- `/sistema-para-salao-de-beleza`;
- `/sistema-para-clinica-de-estetica`;
- `/sistema-para-barbearia`;
- `/sistema-para-esmalteria`.

O menu também apresentava “Redes e Franquias”, mas seu link apontava para a rota de esmalterias no momento observado. Não foi inferida uma rota própria.

### Recursos aprofundados

- `/recursos/agendamento-online`;
- `/recursos/inteligência-artificial`;
- `/recursos/relatorios`;
- `/recursos/anamneses`;
- `/recursos/gerador-de-documentos`;
- `/recursos/integracao-via-api`.

O catálogo ainda expôs páginas para CRM no Chrome, aplicativo próprio, avaliações, três modalidades fiscais, arquivos, assinatura digital, importação XML, comissões, aplicativos, agenda e outros adicionais.

## Posicionamento observado

### Categoria

O produto é apresentado como plataforma/CRM com IA para crescimento de negócios de beleza, e não somente como agenda. A narrativa combina:

1. vender, atender e fidelizar;
2. automatizar comunicação e retorno;
3. integrar agenda, clientes, equipe, financeiro e pagamentos;
4. transformar dados em decisões;
5. migrar com assistência;
6. receber suporte e acompanhamento humano;
7. ampliar a plataforma por API, aplicativos e adicionais.

### Resultados prometidos

- menos trabalho manual e retrabalho;
- mais agendamentos e ocupação;
- redução de faltas com confirmação/sinal;
- recorrência por pacotes e assinaturas;
- aumento de receita e retenção;
- previsibilidade financeira;
- profissionalização e escala.

Esses são claims comerciais. Não comprovam causalidade, disponibilidade em todos os planos nem comportamento do software.

## Funil de aquisição

### Entradas

- busca/conteúdo por blog e páginas de segmento;
- descoberta por catálogo de recursos;
- comparação por preços;
- confiança por depoimentos, presença internacional, avaliações, suporte e institucional.

### Conversões

- **self-service:** Criar conta, Testar grátis, Começar agora;
- **assistida:** Falar/Conversar com vendas;
- **consultiva:** Agendar demonstração;
- **retenção/confiança:** Suporte, base de conhecimento, status e downloads.

Um CTA fixo “Testar grátis” permanece visível em desktop e mobile. Formulários de vendas, criação e demonstração são incorporados por iframes de `hub.belasis.ai`; nenhum contrato de campos foi inspecionado e nada foi enviado.

## Conteúdo da home

A home segue uma narrativa longa:

1. promessa de crescimento + dois CTAs;
2. imagem do dashboard e presença internacional;
3. três pilares: CRM, IA e gestão integrada;
4. automação/CRM;
5. relatórios e dados;
6. migração assistida;
7. prova de confiança;
8. suporte e gerente de contas;
9. API;
10. pagamentos integrados;
11. automação e adicionais;
12. aplicativos;
13. depoimentos;
14. conteúdo editorial;
15. FAQ e CTA final.

No desktop, a página observada media aproximadamente 17.690 px; no mobile, cerca de 21.847 px. A repetição reforça a mensagem, mas aumenta custo de varredura e dilui diferenças entre seções.

## Segmentação

As quatro páginas observadas reutilizam quase toda a mesma composição. A customização principal ocorre no hero e em alguns rótulos:

| Segmento | Ênfase específica observada |
|---|---|
| salão | agenda, confirmação, operação e equipe |
| clínica de estética | prontuário digital, retorno e pacientes |
| barbearia | clube de assinaturas, retorno e espaços masculinos |
| esmalteria | recorrência e lembrete antes da perda da cliente |

Implicação: a estratégia favorece SEO e identificação por segmento, mas parte do conteúdo não é realmente específico. O novo produto deve segmentar por jobs-to-be-done e prova relevante, não apenas substituir substantivos.

## Catálogo de recursos

O catálogo agrupa visualmente capacidades em Popular, Marketing e relacionamento, Automações e Gestão. Os slugs/IDs observados em inglês ou genéricos não correspondem sempre aos rótulos visíveis, indicando possível resíduo da ferramenta de publicação.

Páginas de recurso usam template recorrente:

1. hero com nome, resumo, categoria e demonstração;
2. “Sobre”;
3. “Benefícios”;
4. “Resultados esperados”;
5. aviso eventual de inclusão no plano ou contratação adicional;
6. CTA final de criação.

Elas explicam valor, mas oferecem pouca evidência concreta, interface demonstrada, casos de uso detalhados ou requisitos/pré-condições.

## Planos e monetização

Preços observados em 24/08/2026; são temporais e não devem virar regra sem decisão comercial própria.

| Plano | Preço exibido | Posicionamento | Exemplos destacados |
|---|---:|---|---|
| Lite | a partir de R$ 99/mês | profissionalizar operação | agenda, comandas, CRM, assinaturas e relatórios |
| Pro | a partir de R$ 189/mês | automatizar comunicação | Lite + comissões, financeiro, CRM completo e anamneses |
| Scale | sob consulta | escalar com IA e suporte | Pro + IA, automações/marketing, API e gerente dedicado |

- seletor observado entre cobrança recorrente e anual, com indicação de desconto;
- Scale usa venda assistida; Lite/Pro usam teste self-service;
- comparação inclui agendamento, operação, financeiro, clientes/comunicação, escala/inteligência, sucesso do cliente e redes/franquias;
- alguns itens aparecem como adicionais, mas a legenda visual não ficou semanticamente clara no snapshot;
- a página mistura FAQ em português e inglês;
- em 390 px, áreas da comparação possuíam conteúdo muito mais largo que o container e overflow oculto, sugerindo carrossel/recorte não totalmente autoexplicativo.

## Suporte e confiança

O suporte é tratado como parte central da proposta, não apenas rodapé:

- suporte contínuo;
- gerente de contas;
- treinamento;
- base de conhecimento;
- canais humanos;
- status público;
- migração assistida.

Depoimentos e números de adoção são usados como prova social. Claims quantitativos variaram por página (por exemplo, profissionais, salões e presença internacional); devem ser validados antes de qualquer uso no produto novo.

## Conteúdo e SEO

- blog cobre gestão, finanças, marketing, atendimento, tecnologia e histórias;
- páginas de segmento criam landing pages por intenção de busca;
- páginas individuais de recurso capturam buscas por capacidade;
- artigos são promovidos na home e nas páginas segmentadas;
- CTA de teste permanece transversal.

Riscos observados:

- títulos genéricos repetidos em páginas segmentadas;
- títulos de documento iguais em algumas páginas distintas;
- strings residuais em inglês, como FAQ e case studies;
- erros de revisão em alguns textos;
- páginas muito longas e repetitivas;
- CTA fixo pode competir com conteúdo no mobile.

## Linguagem visual observada

- fundo predominantemente branco;
- tipografia Inter;
- H1 desktop em torno de 64/76,8 px e H2 em 48/57,6 px;
- mobile reduz H1 para 36/43,2 px e H2 para 32/38,4 px;
- cor primária azul-violeta próxima de `rgb(80, 90, 251)`;
- CTAs e cards com raio recorrente próximo de 12 px;
- layout espaçoso, grandes áreas em branco e imagens amplas do produto;
- header fixo no mobile com 72 px;
- CTA flutuante mobile observado em 140 × 40 px.

Esses valores são evidência do alvo. O produto novo deve usar identidade própria conforme `design-system-proposed.md`.

## Mobile e acessibilidade

- header vira marca + menu hambúrguer;
- CTA “Testar grátis” fica fixo próximo à base;
- não houve overflow horizontal do documento, mas diversas seções ocultavam conteúdo interno mais largo;
- navegação expõe `navigation`, porém marca e alguns ícones não têm nome acessível;
- vários textos/elementos animados estavam presentes no DOM antes de aparecer visualmente, criando risco com reduced motion e captura inicial;
- depoimentos e carrosséis precisam de controles e anúncios acessíveis;
- a comparação de planos deve ser utilizável sem arraste oculto.

## Limitações

- nenhuma taxa de conversão, origem de tráfego ou analytics foi acessada;
- formulários em iframe não foram explorados;
- não foram abertos WhatsApp, e-mail, lojas de apps, status ou base externa;
- claims, preços e disponibilidade refletem apenas o conteúdo publicado na data;
- “IA”, “tempo real”, segurança, suporte 24x7 e resultados comerciais não foram verificados tecnicamente.
