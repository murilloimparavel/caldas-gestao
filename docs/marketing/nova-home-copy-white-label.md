# Nova Home: Estratégia de Copy e Arquitetura White-Label

**Documentação Técnica e de Produto — Sprint 3**  
**Arquivo de Implementação:** [`resources/js/pages/marketing/home.tsx`](../../resources/js/pages/marketing/home.tsx)  
**Especificação de Copy:** [`docs/copy/copy-home-pagina-vendas.md`](../copy/copy-home-pagina-vendas.md)  
**Ambiente:** `gestao.caldasindica.com` (e subdomínios locais / de homologação)  
**Data:** Setembro de 2026

---

## 1. Visão Geral e Contexto

### 1.1. Por que a Home foi reconstruída?
As versões anteriores da página inicial adotavam um discurso tradicional de software genérico de gestão ("SaaS B2B", recursos descritos por menus do sistema), o que gerava alta fricção de entendimento e baixa identificação imediata para os profissionais do segmento de beleza, estética e bem-estar (donos de salões, barbearias, estúdios de sobrancelhas, manicures e clínicas).

A nova Home foi projetada a partir de uma abordagem centrada na dor real do profissional e na valorização da sua marca individual, eliminando qualquer jargão técnico corporativo e adotando uma linguagem acolhedora, humana e de profissional para profissional.

### 1.2. A Tese Central: "White-Label de Autoridade"
O ponto de virada na proposta de valor reside na **quebra do paradigma dos marketplaces e agregadores de agendamento**.

Muitos estabelecimentos divulgam no link da bio de suas redes sociais aplicativos e plataformas compartilhadas. Nesses ambientes, o cliente clica para agendar e é confrontado com uma lista de concorrentes diretos da mesma rua ou bairro, muitas vezes com promoções patrocinadas ao lado. Na prática, o estabelecimento paga anúncios e produz conteúdo para alimentar o tráfego dos seus próprios concorrentes.

No **Caldas Gestão**, a tese do **White-Label de Autoridade** estabelece:
* **Exclusividade de Marca:** Ao clicar no link do estabelecimento, o cliente acessa um **site próprio** com o nome, logo, fotos, catálogo de serviços e equipe exclusiva do espaço.
* **Foco Absoluto:** Não há concorrentes, banners de terceiros ou desvios de atenção.
* **Acesso Instantâneo Sem Download:** O cliente não precisa baixar aplicativos na App Store ou Google Play nem cadastrar senhas complexas; o agendamento é concluído diretamente no navegador mobile em menos de 1 minuto.

### 1.3. Comunicação sem Jargões
Todo o vocabulário da página foi alinhado ao dia a dia do salão ou clínica:
* Substitui-se "Gestão Multi-tenant e Ponto de Venda" por **"Comanda simples e comissão calculada na hora sem briga"**.
* Substitui-se "Métricas de Churn e Reativação de Clientes" por **"Aviso automático para quem sumiu voltar"**.
* Substitui-se "Modelos de Recorrência e MRR" por **"Dinheiro garantido no dia 1º com planos mensais e pacotes"**.

---

## 2. Estrutura da Página (10 Dobras Implementadas)

A página foi estruturada em 10 dobras sequenciais de alta conversão, mapeadas em [`resources/js/pages/marketing/home.tsx`](../../resources/js/pages/marketing/home.tsx):

| Dobra | Nome | Objetivo de Conversão & Experiência |
|---|---|---|
| **Dobra 1** | **Hero Section (A Primeira Impressão)** | Promessa central clara, selo de autoridade, 3 benefícios diretos com check, CTA com gatilho sem cartão de crédito e preview interativo simulando site próprio + agenda. |
| **Dobra 2** | **A Quebra de Paradigma (O erro na bio do Instagram)** | Contraste lado a lado entre o "Jeito Tradicional" (divulgar concorrência em apps genéricos) e o "Jeito Caldas Gestão" (site próprio e exclusivo). |
| **Dobra 3** | **Você se identifica com isso? (Os 4 problemas)** | Empatia profunda atacando as 4 grandes dores: cadeira vazia por falta de aviso, sufoco do caixa na sexta-feira, montanha-russa financeira e sumiço de clientes. |
| **Dobra 4** | **Como funciona a transformação (Os 4 Pilares)** | Passo a passo linear e descomplicado: 1. Site próprio; 2. Sincronização de agenda com Google Agenda; 3. Planos e pacotes; 4. Comanda única sem divergências. |
| **Dobra 5** | **O que você tem em mãos (Os 6 Recursos)** | Seletor de abas interativo com previews realistas: Agenda Inteligente, Comandas Rápidas, Assinaturas/Pacotes, Caixa Seguro, Estoque e Radar de Retenção. |
| **Dobra 6** | **Para quem atende sozinho ou tem equipe** | Segmentação de valor: o autônomo ganha 2h livres de WhatsApp enquanto atende; quem tem equipe ganha controle de comissões e visão de dono. |
| **Dobra 7** | **Para quem é e Para quem não é** | Qualificação honesta em duas colunas, filtrando clientes alinhados e afastando perfis incompatíveis com a profissionalização digital. |
| **Dobra 8** | **Dúvidas Comuns / FAQ** | Acordeão interativo respondendo 8 objeções clássicas (medo de tecnologia, app pesado, cartão de crédito, suporte humanizado). |
| **Dobra 9** | **As Duas Escolhas (O Momento da Decisão)** | Comparativo psicológico direto: continuar no cansaço e na incerteza vs. dar o passo rumo à ordem e à estabilidade. |
| **Dobra 10** | **Chamada Final (Fechamento sem Risco)** | CTA final contrastante, reforço das garantias (sem fidelidade, sem cartão inicial) e rodapé completo com links institucionais e privacidade. |

---

## 3. Tecnologias e Componentes Frontend

A implementação técnica da interface foi realizada seguindo o stack moderno do ecossistema do Caldas Gestão:

### 3.1. Stack Tecnológico
* **React 19:** Renderização moderna baseada em componentes funcionais e hooks, com tipagem estrita TypeScript.
* **Inertia.js v3:** Camada de integração SPA sem APIs manuais, utilizando `<Head>` dinâmico para SEO e OpenGraph, além de `<Link>` para navegação instantânea.
* **Tailwind CSS v4:** Framework utilitário com sistema de cores personalizado para marketing:
  * Fundo quente e elegante: `#f8f6f0`
  * Tipografia e contraste principal: `#17252a` e neutros escuros
  * Destaques de ação e atenção: Dourado quente (`#efa83f`) e esmeralda para confirmações
* **Lucide Icons:** Conjunto semântico de ícones leves (`CalendarDays`, `Receipt`, `Wallet`, `Boxes`, `MessageCircleHeart`, `ShieldCheck`, etc.).
* **Laravel Wayfinder:** Importação de rotas seguras e tipadas (`login()`, `register()`) a partir de `@/routes`.

### 3.2. Mecanismos de Interatividade
* **Navegação Responsiva:** Header com menu hambúrguer para dispositivos móveis (`mobileMenuOpen`), com backdrop blur e âncoras para seções estratégicas (`#como-funciona`, `#recursos`, `#faq`).
* **Visualizador Dinâmico de Recursos (Dobra 5):** Controle de estado (`activeFeature`) que alterna abas e renderiza imediatamente a interface correspondente com cards de pré-visualização de agenda, comanda e caixa.
* **Acordeão de FAQ (Dobra 8):** Controle de alternância (`openFaq`) permitindo expansão suave e fechamento automático das dúvidas dos visitantes.

---

## 4. Testes e Qualidade

O desenvolvimento da Home foi submetido e aprovado em todos os gates de qualidade do projeto:

### 4.1. Verificação de Tipos (TypeScript)
```bash
npm run types:check
```
* **Comando:** `tsc --noEmit`
* **Resultado:** Executado com código de saída 0 (zero erros de tipagem encontrados).

### 4.2. Build de Produção dos Assets
```bash
npm run build
```
* **Compilador:** Vite + Rolldown + Tailwind CSS v4 + React Babel.
* **Artefato gerado:** `public/build/assets/home-*.js` (~71.9 kB minificado, ~18.6 kB gzip).
* **Resultado:** Build concluído com sucesso e manifesto atualizado.

### 4.3. Testes Automatizados no Backend (Pest PHP)
```bash
php artisan test --compact
```
* **Status Geral da Suíte:** **379 testes aprovados**, 26 skipped, **2722 asserções**, 0 falhas.
* **Cobertura da Página Home:** O arquivo [`tests/Feature/MarketingAndBrandingTest.php`](../../tests/Feature/MarketingAndBrandingTest.php) garante que:
  * A rota `route('home')` responde com sucesso (HTTP 200).
  * O componente Inertia `marketing/home` é renderizado corretamente com as propriedades de branding do sistema (`branding.name`).
  * Os aliases `/signin` e `/signup` redirecionam corretamente para as rotas canônicas de autenticação.

---

## 5. Próximos Passos e Otimização de Conversão (A/B Testing)

Para as próximas sprints de marketing e produto, recomenda-se:

1. **Rastreamento e Telemetria de Conversão:**
   * Implementar disparo de eventos de telemetria analítica (ex.: PostHog / Google Tag Manager) em cada clique nos botões de CTA da Hero, Dobra 5 e Dobra 10.
   * Mensurar a taxa de scroll depth para analisar em qual dobra ocorre maior drop-off de atenção.

2. **Testes A/B na Dobra 1 (Hero Section):**
   * **Variação A (Atual):** Foco no "Próprio site de agendamento na internet".
   * **Variação B:** Foco direto no "Fim das mensagens no WhatsApp e furos de horário".

3. **Prova Social Real e Depoimentos Dinâmicos:**
   * Adicionar na Dobra 6 ou entre as Dobras 4 e 5 um carrossel com fotos reais de salões parceiros e métricas de horas economizadas.

4. **Captura Rápida de Lead para Atendimento Consultivo:**
   * Disponibilizar um link flutuante de WhatsApp opcional para donos de espaços que desejarem suporte guiado na migração de planilhas/cadernos para o sistema.
