# EV-011 — Superfícies de arquivos, imagens e mídia

- **Data do registro:** 26/08/2026
- **Alvo:** Belasis, em reconstrução clean-room para o Caldas Gestão
- **Fontes locais:** SCR-001, SCR-002, SCR-004, SCR-005 e SCR-006; EV-002, EV-007 e EV-009; `domain/contexts.md`; ADR-007
- **Classificação:** evidência observada de superfície e menção de catálogo; não é especificação de upload

## Contexto e limites da evidência

Este registro consolida somente o que as fontes locais tornam observável sobre lugares do produto relacionados a arquivos, imagens ou mídia. As fontes registram navegação, abas, conteúdo de tela e menções do catálogo. Elas não comprovam, por si só, a existência de um campo `input[type=file]`, os tipos aceitos, limites, validação server-side, envio, persistência ou remoção.

Os batches autenticados foram somente leitura: o EV-002 registra que nenhum campo foi preenchido e nenhum upload, Enviar ou ação equivalente foi acionado. Portanto, “superfície” abaixo significa uma aba, capacidade, elemento exibido ou menção de produto — não um fluxo de upload validado.

## Observed

### Cliente: arquivos, imagens e anamneses após criação

- Em um cliente novo, **Imagens e Arquivos** e **Anamneses** aparecem entre as abas desabilitadas até que a identidade seja criada (SCR-004).
- Em um cliente existente, essas abas tornam-se navegáveis (SCR-004).
- O contexto de Relacionamento e prontuário trata `Arquivo` e `Anamnese` como donos separados, ao lado de Mensagem, Anotação e Consentimento, com autorização e retenção reforçadas por LGPD (`domain/contexts.md`).
- A evidência disponível não mostra o conteúdo dessas abas, um campo de seleção, formatos, limites, botão de envio ou resultado de persistência. Não há prova local de que a aba Imagens e Arquivos contenha um upload específico, nem de que Anamneses aceite anexos.

### Profissional: assinatura digital

- **Assinatura digital** aparece como aba inicialmente disponível no cadastro de novo profissional (SCR-005).
- A tela é descrita como uma superfície/capacidade do profissional; a fonte não registra se a assinatura é digitada, desenhada, capturada como imagem, anexada como documento ou produzida por outro mecanismo.
- Não foi observado campo de arquivo, tipo MIME, limite, submissão, validade jurídica implementada ou armazenamento. A validade jurídica e a estrutura da assinatura permanecem desconhecidas (SCR-005).

### Avatares exibidos no shell e na agenda

- O shell autenticado exibe avatar/perfil e saudação (SCR-001).
- A agenda exibe avatares na grade/lista de profissionais (SCR-002).
- Essas fontes comprovam exibição de avatar, não um ponto de upload. Não há prova local de que o shell, a agenda ou a conta permitam selecionar, substituir ou enviar uma imagem.

### Marketing: catálogo público

- O catálogo de marketing menciona uma superfície/página de **Arquivos** e também **Importação XML**, entre páginas e adicionais do produto (EV-009).
- Trata-se de uma menção do catálogo público/posicionamento, não de uma tela operacional autenticada inspecionada. Não há prova local sobre entidade, ator, formato de arquivo, finalidade, validação ou submissão de qualquer uma dessas capacidades.

## Unknowns

- Onde, exatamente, ocorre a seleção ou submissão de arquivos em cada superfície, se ocorre.
- Quais entidades possuem mídia: cliente, profissional, produto, serviço, unidade, usuário ou outras.
- Se “Imagens e Arquivos” separa imagens publicáveis de documentos privados e quais regras de acesso se aplicam.
- Se Anamneses e assinatura digital aceitam anexos, imagens, PDFs, desenho/captura ou somente dados estruturados.
- Tipos MIME, extensões, tamanho, quantidade, dimensões, compressão, antivírus, normalização de nomes, deduplicação e retenção.
- Se avatares exibidos são gerenciados no shell/conta, no cadastro da pessoa ou em outra superfície.
- Escopo por tenant/unidade, papéis autorizados, URLs públicas versus temporárias, auditoria, substituição, exclusão e recuperação após falha.
- Escopo funcional e contrato da página de Arquivos e da Importação XML anunciadas no marketing.

## Impactos propostos para ADR-007

O ADR-007 deve continuar sendo tratado como decisão arquitetural do Caldas Gestão, não como descrição comprovada da implementação interna do Belasis. Os achados acrescentam requisitos de classificação e deixam explícitas as lacunas:

1. **Distinguir mídia publicável de documento/sensível.** Imagens de catálogo e outras imagens explicitamente destinadas à exibição pública podem ter uma política de mídia publicável, com derivados/URLs adequados. Arquivos de cliente, anexos de anamnese e materiais ligados à assinatura digital devem ser classificados como potencialmente sensíveis por padrão, com acesso autenticado, autorização tenant-scoped/unit-scoped quando aplicável, URLs temporárias e trilha de acesso.
2. **Não generalizar a política de imagens para documentos.** A allowlist de imagens e o limite definidos no ADR-007 não devem ser reutilizados automaticamente para Arquivos, Anamneses, assinatura digital ou Importação XML. Cada classe precisa de MIME, tamanho, retenção, inspeção e política de exposição próprios antes da implementação.
3. **Modelar o vínculo funcional sem presumir campos.** O desenho pode prever um registro de mídia/anexo com tenant, proprietário/contexto, classificação de sensibilidade, caminho relativo, metadados, estado e auditoria; a existência de um upload só deve ser habilitada quando o requisito do domínio e a superfície forem confirmados.
4. **Manter mediação e autorização no backend.** Qualquer fluxo futuro de seleção/submissão deve obedecer à mediação Laravel, validação real do conteúdo, isolamento por tenant e persistência de metadados já decididos no ADR-007. A ausência de um campo observado não reduz esses requisitos.
5. **Tratar capacidades de marketing como hipótese de produto.** Arquivos e Importação XML exigem descoberta funcional independente antes de criar buckets, domínios, extensões ou endpoints específicos; a menção pública não prova um contrato operacional.

## Critérios de aceitação derivados

- O produto documenta, por superfície, se a mídia é publicável, interna ou sensível antes de aceitar upload.
- Imagens públicas e documentos/sensíveis usam políticas distintas de autorização, URL, retenção e auditoria.
- Nenhum endpoint de upload é inferido apenas pela presença de uma aba, avatar exibido ou menção no marketing.
- Tipos, limites, quantidade, processamento e deleção são testados e registrados quando o fluxo de submissão for disponibilizado.
- Acesso a arquivos de cliente, anamneses e assinatura digital é negado por padrão a atores sem autorização e fica auditável.
