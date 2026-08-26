# Plano de Implementação — Mídias e Fotos de Serviços & Produtos

- **Status:** Proposto
- **Data:** 26/08/2026
- **Alvo:** Upload, armazenamento local e exibição de fotos em Serviços (`Service`) e Produtos (`Product`).

---

## 💡 Estratégia de Armazenamento (Local com Fallback S3)

Conforme estabelecido no [ADR-007](file:///Users/murilloalves/Projects/caldas-gestao/docs/adr/ADR-007--storage-e-uploads-midia.md), a aplicação utilizará a abstração `Storage::disk(...)` do Flysystem.
- **Ambiente Inicial (Local):** Usará o disk `public` (`storage/app/public` com link simbólico `public/storage`), permitindo desenvolvimento e teste sem dependências externas.
- **Transição Futura (S3 / MinIO / Supabase Storage):** Alternar a variável `FILESYSTEM_DISK=s3` no `.env` virará a chave de armazenamento para S3 sem necessidade de alterar código da aplicação.

---

## 📍 Estrutura de Arquivos e Armazenamento

Os arquivos locais serão salvos com a seguinte estrutura de diretórios:
```text
storage/app/public/{tenant_id}/services/{service_id}/{hash}.{ext}
storage/app/public/{tenant_id}/products/{product_id}/{hash}.{ext}
```

---

## 🛠️ Fases de Execução

### Fase 1: Schema do Banco de Dados e Modelos
1. **Migration:**
   - Criar `database/migrations/xxxx_xx_xx_add_image_path_to_services_and_products_tables.php`.
   - Adicionar `image_path` (nullable string) nas tabelas `services` e `products`.
2. **Modelos (`Service.php` e `Product.php`):**
   - Adicionar `'image_path'` ao array `$fillable`.
   - Criar accessor `image_url` que resolve `Storage::disk(config('filesystems.default', 'public'))->url($this->image_path)`.
   - Adicionar método de limpeza da foto antiga no disco ao deletar ou substituir a mídia.

---

### Fase 2: Form Requests, Actions e Controllers Backend
1. **Validação nos Request Classes:**
   - `ServiceRequest` e `ProductRequest`: Adicionar regra `'image' => ['nullable', 'file', 'image', 'mimes:jpeg,png,webp', 'max:5120']`.
2. **Actions de Negócio:**
   - [`CreateService`](file:///Users/murilloalves/Projects/caldas-gestao/app/Actions/Catalog/Services/CreateService.php) e [`UpdateService`](file:///Users/murilloalves/Projects/caldas-gestao/app/Actions/Catalog/Services/UpdateService.php): Processar o arquivo enviado via `$request->file('image')`, salvar com nome hash no disk configurado e persistir `image_path`.
   - [`CreateProduct`](file:///Users/murilloalves/Projects/caldas-gestao/app/Actions/Catalog/Products/CreateProduct.php) e [`UpdateProduct`](file:///Users/murilloalves/Projects/caldas-gestao/app/Actions/Catalog/Products/UpdateProduct.php): Processar upload e substituir a foto anterior em updates.
3. **Controllers (`ServiceController` e `ProductController`):**
   - Passar a propriedade `image_url` no payload retornado para as views Inertia.

---

### Fase 3: Interface Frontend (React / Inertia)
1. **Componente Reutilizável `ImageUploader`:**
   - Criar `resources/js/components/ui/image-uploader.tsx` com suporte a seleção de arquivo, preview da imagem atual e botão de remoção.
2. **Modais e Páginas de Cadastro:**
   - Atualizar a página de Serviços (`resources/js/pages/services/index.tsx` e `show.tsx`) incluindo o upload e miniatura do serviço.
   - Atualizar a página de Produtos (`resources/js/pages/products/index.tsx` e `show.tsx`) incluindo o upload e miniatura do produto.
3. **Public Booking:**
   - Exibir a foto do serviço no card de seleção do portal de agendamento público (`resources/js/pages/public-booking/`).

---

### Fase 4: Testes de Feature & Qualidade
1. **Suíte de Testes (`tests/Feature/CatalogImageUploadTest.php`):**
   - Teste de upload com sucesso em `Service` usando `UploadedFile::fake()->image('service.jpg')`.
   - Teste de upload com sucesso em `Product` usando `UploadedFile::fake()->image('product.png')`.
   - Teste de substituição da imagem antiga ao enviar uma nova foto.
   - Teste de rejeição de formatos não suportados (ex: PDF ou imagens > 5MB).
   - Teste de isolamento de caminho por `tenant_id`.

---

### Fase 5: Validação Geral & Documentação
1. Formatação de código PHP via `vendor/bin/pint --format agent`.
2. Verificação de tipos TypeScript (`npm run types:check`) e ESLint (`npm run lint:check`).
3. Execução completa dos testes Pest (`php artisan test --compact`).
4. Reconstrução do grafo do Graphify (`graphify update .`).
