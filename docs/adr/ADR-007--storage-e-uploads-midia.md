# ADR-007 — Storage de arquivos, uploads de mídia e integração S3 / MinIO / Supabase Storage

- **Status:** aceito
- **Data:** 26/08/2026
- **Decisão:** Driver S3 (Flysystem) como abstração padrão com MinIO em ambiente local, Supabase Storage S3 ou AWS S3 em produção, mediação via backend Laravel e organização de caminhos por tenant.

## Contexto

O Caldas Gestão exige o armazenamento e exibição de diversos tipos de mídias e arquivos de mídia, incluindo:
- Fotos de catálogo de produtos;
- Imagens representativas de serviços oferecidos;
- Logotipos das unidades de atendimento;
- Avatares de perfil dos usuários e profissionais.

À medida que o sistema é multi-tenant e opera em múltiplos ambientes (desenvolvimento local, staging e produção), faz-se necessária uma arquitetura unificada para uploads, armazenamento, validação de arquivos e geração de URLs de acesso.

## Decisão

Adotar o driver **S3** (com Flysystem do Laravel) como a camada de abstração padronizada de Storage para toda a aplicação.

### 1. Abstração e Provedores de Storage

- **Desenvolvimento Local:** Utilização do **MinIO** via container Docker, provendo uma API 100% compatível com Amazon S3 sem dependências de serviços externos.
- **Produção e Staging:** Utilização do **Supabase Storage S3-compatible API** ou **AWS S3** nativo, ajustável via variáveis de ambiente (`AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_ENDPOINT`).

### 2. Mediação Obrigatória via Backend (Upload Mediado)

- **Sem Uploads Diretos Não Autenticados:** Todos os uploads de arquivos devem ser mediados obrigatoriamente pelo backend Laravel (via Form Requests ou rotas autenticadas).
- O backend é responsável pela autenticação, autorização de acesso por tenant, sanitização, verificação real do tipo MIME e persistência dos metadados nas tabelas operacionais.
- Uploads diretos do navegador para o bucket sem autorização e validação prévia pelo Laravel são proibidos.

### 3. Organização de Arquivos por Tenant

Para isolamento e legibilidade do armazenamento, os caminhos no storage deverão seguir a convenção padronizada de diretórios:

```text
{tenant_id}/{domain}/{entity_id}/{hash}.{ext}
```

Onde:
- `{tenant_id}`: UUID v7 do tenant proprietário do arquivo.
- `{domain}`: Contexto funcional (ex: `products`, `services`, `units`, `avatars`).
- `{entity_id}`: UUID da entidade associada (ex: id do produto ou id do usuário).
- `{hash}`: Hash único de nome de arquivo (evitando colisão e sobreescrita de arquivos com mesmo nome original).
- `{ext}`: Extensão do arquivo (ex: `webp`, `png`, `jpeg`).

Exemplo: `01918a2b-3c4d-7e8f-9a0b-1c2d3e4f5a6b/products/01918a2b-89ab-7cd-ef01-23456789abcd/a1b2c3d4e5f6.webp`

### 4. Regras de Validação e Limites

- **MIME Types Permitidos:** `image/webp`, `image/png`, `image/jpeg`.
- **Tamanho Máximo por Imagem:** 5 MB (`max:5120` em validações Laravel).
- Formatos adicionais (documentos PDF, comprovantes) deverão ter regras específicas em ADR posterior caso necessário.

### 5. Atribuição de URLs e Eloquent Accessors

- Os modelos Eloquent armazenarão no banco de dados apenas o **caminho relativo** do arquivo (`path`), garantindo independência em relação ao domínio da CDN ou do bucket.
- A exposição das imagens para a API / Inertia será feita através de **Accessors Eloquent** (ex: `image_url` ou `avatar_url`), que utilizam o facade `Storage::disk('s3')->url($this->path)` ou `Storage::disk('s3')->temporaryUrl(...)` (presigned URLs para arquivos privados).

## Consequências

### Positivas

- **Portabilidade:** Código de aplicação completamente desacoplado do provedor físico de infraestrutura (MinIO local / Supabase Storage / AWS S3).
- **Segurança e Isolamento Multi-Tenant:** Garantia de que arquivos pertencem estritamente a um `tenant_id` e passam por validação de autorização antes do upload ou deleção.
- **Prevenção de Ataques:** Validação estrita de tipo MIME e hashing do nome dos arquivos impede execução ou sobreposição acidental de scripts no storage.
- **Manutenibilidade:** Armazenar apenas o caminho relativo no banco permite trocar de bucket, CDN ou provedor apenas ajustando variáveis de ambiente (`.env`).

### Custos e Riscos

- **Tráfego no Backend:** Uploads passam pelo servidor Laravel antes de ir para o storage, consumindo memória/banda do servidor em uploads de grande porte (mitigado pelo limite de 5MB).
- **Dependência do SDK/Flysystem AWS S3:** Exige manter o pacote `league/flysystem-aws-s3-v3` configurado e atualizado.

## Critérios para Revisar este ADR

Reavaliar a decisão se ocorrer uma destas condições:
- Necessidade de uploads de grandes arquivos (ex: vídeos ou backups pesados) exigir suporte a Presigned Upload URLs diretamente para o S3 bypassando o backend.
- Exigência de processamento assíncrono avançado de imagens em escala (ex: pipelines serverless de resize/redimensionamento dinâmico).
