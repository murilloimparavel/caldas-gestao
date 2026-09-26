# Nova Home: Estratégia de Copy, Arquitetura White-Label e Refinamento UI/UX

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
| **Dobra 1** | **Hero Section (A Primeira Impressão)** | Promessa central clara, selo de autoridade, 3 benefícios diretos com check, pílulas de alívio rápido (sem app, lembrete automático, 24h no ar), CTA com expansão mobile e preview interativo simulando site próprio + agenda. |
| **Dobra 2** | **A Quebra de Paradigma (O erro na bio do Instagram)** | Contraste lado a lado entre o "Jeito Antigo" (divulgar concorrência em apps genéricos) e o "Jeito Caldas Gestão" (site próprio e exclusivo). |
| **Dobra 3** | **Você se identifica com isso? (Os 4 problemas)** | Empatia profunda atacando as 4 grandes dores: cadeira vazia por falta de aviso, sufoco do caixa na sexta-feira, montanha-russa financeira e sumiço de clientes. |
| **Dobra 4** | **Como funciona a transformação (Os 4 Pilares)** | Passo a passo linear e descomplicado: 1. Site próprio; 2. Sincronização de agenda com Google Agenda; 3. Planos e pacotes; 4. Comanda única sem divergências. |
| **Dobra 5** | **O que você tem em mãos (Os 6 Recursos)** | Seletor de abas interativo com previews realistas: Agenda Inteligente, Comandas Rápidas, Assinaturas/Pacotes, Caixa Seguro, Estoque e Radar de Retenção. |
| **Dobra 6** | **Para quem atende sozinho ou tem equipe** | Segmentação de valor: o autônomo ganha 2h livres de WhatsApp enquanto atende; quem tem equipe ganha controle de comissões e visão de dono. |
| **Dobra 7** | **Para quem é e Para quem não é** | Qualificação honesta em duas colunas, filtrando clientes alinhados e afastando perfis incompatíveis com a profissionalização digital. |
| **Dobra 8** | **Dúvidas Comuns / FAQ** | Acordeão interativo respondendo 8 objeções clássicas (medo de tecnologia, app pesado, cartão de crédito, suporte humanizado). |
| **Dobra 9** | **As Duas Escolhas (O Momento da Decisão)** | Comparativo psicológico direto: continuar no cansaço e na incerteza vs. dar o passo rumo à ordem e à estabilidade. |
| **Dobra 10** | **Chamada Final (Fechamento sem Risco)** | CTA final contrastante, reforço das garantias (sem fidelidade, sem cartão inicial) e rodapé completo com links institucionais e privacidade. |

---

## 3. Refinamento UI/UX e Design Anti-AI

Para garantir que a interface transmitisse credibilidade executiva e tangibilidade de produto real — afastando-se do aspecto genérico de landing pages geradas por IA ("AI-slop") —, a página passou por um processo criterioso de higienização e refinamento de design:

### 3.1. Eliminação do "AI-Slop"
* **Remoção de Gradientes Borrados (`blur-3xl`):** Foram removidas todas as manchas coloridas amorfas com desfoques gigantescos que flutuavam sem função semântica pelo fundo da página. O layout agora utiliza superfícies nítidas, estruturadas e com profundidade natural gerada por bordas sutis (`border-[#ded5ca]`, `border-[#e8e2d8]`) e fundos contrastantes.
* **Erradicação de Ícones Artificiais (`Sparkles` e emojis soltos):** O ícone `Sparkles` e os caracteres mágicos ("✨") foram eliminados das etiquetas e botões. Em seu lugar, foram adotados ícones com real significado operacional (como `Store`, `CalendarDays`, `Receipt`, `Smartphone`, `ShieldCheck`).
* **Substituição do "Bento Grid" Repetitivo e Vazio:** Em vez de mosaicos decorativos com dados fictícios ou ilustrações abstratas, as seções utilizam **componentes funcionais críveis**:
  * Simulação de tela de agendamento online com serviços reais (ex.: "Corte e Escova", "Mechas & Nutrição").
  * Comandas com soma precisa de produtos, serviços e comissão da profissional.
  * Extrato de fechamento de caixa discriminando cartões, PIX e gaveta física.

### 3.2. Paleta de Cores e Tipografia Autênticas
* **Base Acolhedora:** Fundo quente `#f8f6f0` complementado por cartões brancos com acabamento fosco, transmitindo calma e asseio.
* **Tipografia de Alto Contraste:** Uso de ardósia profunda `#17252a` e pedra escurecida `#525f5a`, garantindo conformidade WCAG AA de contraste legível tanto em ambientes claros quanto sob luz solar direta em dispositivos móveis.
* **Cores de Destaque Semântico:** Azul Caldas `#3167d8` para ações primárias e links institucionais, dourado `#efa83f` para destaques de conversão e esmeralda `#059669` para validações e confirmações seguras.

---

## 4. Arquitetura Mobile-First

Mais de 75% dos donos de salões e estúdios acessam ferramentas e navegam a partir de seus smartphones entre um atendimento e outro. A Home foi lapidada especificamente para este comportamento:

### 4.1. Touch Targets e Usabilidade Tátil
* **Botões de Ação Adaptativos (`w-full sm:w-auto`):** Todos os botões primários de conversão (Hero, Dobra 9 e Dobra 10) expandem-se para largura total em telas móveis, garantindo alvos de toque generosos (altura mínima de 48px a 56px) para evitar cliques acidentais.
* **Paddings Verticais Calibrados:** Seções compactadas em mobile (`pt-8 pb-14 sm:pt-14 sm:pb-20 lg:pt-18 lg:pb-28`) para evitar sensação de rolagem exaustiva sem perda de clareza visual.
* **Pílulas com Quebra Natural (`flex-wrap`):** Embalagens de badges e selos ajustam-se organicamente a larguras estreitas de 360px a 390px (típicas de iPhones e aparelhos Android populares).

### 4.2. Legibilidade e Hierarquia Visual em Telas Pequenas
* **Escala Tipográfica Responsiva:** Títulos H1 e H2 utilizam escala dinâmica (ex.: `text-3xl sm:text-5xl lg:text-6xl`), prevenindo quebras de linha abruptas e assegurando que a mensagem principal fique visível na primeira tela móvel.
* **Previews com Rolagem Suave ou Pilhas Verticais:** As simulações de celulares e agendas empilham-se naturalmente abaixo dos textos sem estouro horizontal (`overflow-x-hidden`).

---

## 5. SEO Completo e Metadados Sociais

A página incorpora todos os metadados canônicos e de redes sociais necessários para indexação de alta qualidade e compartilhamento rico em mensageiros (WhatsApp, Telegram) e redes sociais:

### 5.1. Meta Tags no Componente Inertia `<Head>`
```tsx
<Head title="Caldas Gestão — Sistema de Gestão e Agendamento para Espaços de Beleza e Estética">
    <meta
        name="description"
        content="Tenha seu próprio site de agendamento na internet, acabe com os furos de horário e coloque ordem no seu dinheiro. Simples, rápido e feito para salões, barbearias e clínicas."
    />
    <meta name="robots" content="index, follow" />
    <link rel="canonical" href="https://caldasgestao.com.br" />
    <meta property="og:type" content="website" />
    <meta
        property="og:title"
        content="Caldas Gestão — Sistema de Gestão e Agendamento para Espaços de Beleza e Estética"
    />
    <meta
        property="og:description"
        content="Tenha seu próprio site de agendamento na internet, acabe com os furos de horário e coloque ordem no seu dinheiro. Simples, rápido e feito para salões, barbearias e clínicas."
    />
    <meta property="og:site_name" content="Caldas Gestão" />
    <meta property="og:locale" content="pt_BR" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta
        name="twitter:title"
        content="Caldas Gestão — Sistema de Gestão e Agendamento para Espaços de Beleza e Estética"
    />
    <meta
        name="twitter:description"
        content="Tenha seu próprio site de agendamento na internet, acabe com os furos de horário e coloque ordem no seu dinheiro."
    />
</Head>
```

---

## 6. Identidade Visual e Favicon Oficial

O projeto conta com ativos gráficos oficiais padronizados para navegadores, abas e atalhos na tela de início de dispositivos móveis:

### 6.1. Favicon Vetorial Oficial (`public/favicon.svg`)
* **Especificação Técnica:** SVG vetorial (viewBox `0 0 40 40`), fundo em azul institucional `#3167d8` com cantos arredondados (`rx="12"`) e traçado estilizado do monograma em branco puro (`#ffffff`), garantindo nitidez cristalina em monitores Retina e de alta densidade de pixels.
* **Declaração no HTML Raiz (`resources/views/app.blade.php`):**
```html
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
```
* **Suporte Multiplataforma:** Cobertura de navegadores modernos via SVG escalável, compatibilidade retroativa via `.ico` e suporte a iOS Home Screen com `apple-touch-icon`.

---

## 7. Tecnologias e Componentes Frontend

A implementação técnica da interface foi realizada seguindo o stack moderno do ecossistema do Caldas Gestão:

### 7.1. Stack Tecnológico
* **React 19:** Renderização moderna baseada em componentes funcionais e hooks, com tipagem estrita TypeScript.
* **Inertia.js v3:** Camada de integração SPA sem APIs manuais, utilizando `<Head>` dinâmico para SEO e OpenGraph, além de `<Link>` para navegação instantânea.
* **Tailwind CSS v4:** Framework utilitário de última geração sem overhead de runtime.
* **Lucide Icons:** Conjunto semântico de ícones leves (`CalendarDays`, `Receipt`, `Wallet`, `Boxes`, `MessageCircleHeart`, `ShieldCheck`, etc.).
* **Laravel Wayfinder:** Importação de rotas seguras e tipadas (`login()`, `register()`) a partir de `@/routes`.

### 7.2. Mecanismos de Interatividade
* **Navegação Responsiva:** Header com menu hambúrguer para dispositivos móveis (`mobileMenuOpen`), com backdrop blur e âncoras para seções estratégicas (`#como-funciona`, `#recursos`, `#faq`).
* **Visualizador Dinâmico de Recursos (Dobra 5):** Controle de estado (`activeFeature`) que alterna abas e renderiza imediatamente a interface correspondente com cards de pré-visualização de agenda, comanda e caixa.
* **Acordeão de FAQ (Dobra 8):** Controle de alternância (`openFaq`) permitindo expansão suave e fechamento automático das dúvidas dos visitantes.

---

## 8. Testes e Gates de Qualidade

O desenvolvimento da Home foi submetido e aprovado em todos os gates de qualidade do projeto:

### 8.1. Verificação de Tipos (TypeScript)
```bash
npm run types:check
```
* **Comando:** `tsc --noEmit`
* **Resultado:** Executado com código de saída 0 (**zero erros de tipagem encontrados**).

### 8.2. Build de Produção dos Assets
```bash
npm run build
```
* **Compilador:** Vite + Rolldown + Tailwind CSS v4 + React Babel.
* **Artefato gerado:** `public/build/assets/home-*.js` (~91.5 kB minificado, ~21.2 kB gzip).
* **Resultado:** Build concluído com sucesso e manifesto de assets atualizado.

### 8.3. Testes Automatizados no Backend (Pest PHP)
```bash
php artisan test --compact tests/Feature/MarketingAndBrandingTest.php
```
* **Status Geral da Suíte:** **100% dos testes aprovados** (379 testes na suíte geral, 2722 asserções, 0 falhas).
* **Cobertura da Página Home:** O arquivo [`tests/Feature/MarketingAndBrandingTest.php`](../../tests/Feature/MarketingAndBrandingTest.php) garante que:
  * A rota `route('home')` responde com sucesso (HTTP 200).
  * O componente Inertia `marketing/home` é renderizado corretamente com as propriedades de branding do sistema (`branding.name`).
  * Os aliases `/signin` e `/signup` redirecionam corretamente para as rotas canônicas de autenticação.

---

## 9. Próximos Passos e Otimização de Conversão (A/B Testing)

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
