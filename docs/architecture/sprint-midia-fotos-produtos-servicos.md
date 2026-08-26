# Sprint — Implementação de Mídias e Fotos (Serviços e Produtos)

## 🎯 Objetivo
Executar o plano definido em [`plano-implementacao-midia-produtos-servicos.md`](file:///Users/murilloalves/Projects/caldas-gestao/docs/architecture/plano-implementacao-midia-produtos-servicos.md) para upload, armazenamento e exibição de fotos em **Serviços** (`Service`) e **Produtos** (`Product`), com armazenamento inicial em disco `public` (local) e compatibilidade com S3/MinIO/Supabase Storage via Flysystem.

---

## 📦 Alterações Efetuadas

### 1. Banco de Dados & Modelos
- **Migration:** `2026_08_26_110000_add_image_path_to_services_and_products_tables.php` adicionou a coluna `image_path` (nullable string) em `services` e `products`.
- **Modelos (`Service.php` e `Product.php`):**
  - Adicionado `image_path` em `$fillable`.
  - Accessor virtual `image_url` resolvendo via `Storage::disk(config('filesystems.default', 'public'))->url($this->image_path)`.
  - `$appends = ['image_url']`.

### 2. Actions & Form Requests Backend
- **Form Requests:** `ServiceRequest` e `ProductRequest` com validação de imagem (`nullable|file|image|mimes:jpeg,jpg,png,webp|max:5120`).
- **Actions:**
  - `CreateService` / `UpdateService`: Armazenamento em `{tenant_id}/services/{service_id}/{hash}.{ext}`.
  - `CreateProduct` / `UpdateProduct`: Armazenamento em `{tenant_id}/products/{product_id}/{hash}.{ext}`.
  - Exclusão do arquivo anterior no storage ao substituir a imagem ou deletar o registro.

### 3. Componentes e Páginas Frontend
- **Componente Reutilizável:** [`image-uploader.tsx`](file:///Users/murilloalves/Projects/caldas-gestao/resources/js/components/ui/image-uploader.tsx) com preview da imagem atual, seleção de arquivo, botão de remoção e integração com `useForm`.
- **Formulários e Tabelas:**
  - `resources/js/pages/services/index.tsx` & `show.tsx`
  - `resources/js/pages/products/index.tsx` & `show.tsx`
- **Portal de Agendamento Público:**
  - `resources/js/pages/public-booking/show.tsx` exibe as fotos nos cards dos serviços oferecidos pela unidade.

### 4. Testes de Feature (`tests/Feature/CatalogImageUploadTest.php`)
- Cobertura em Pest validando upload com `Storage::fake('public')`, substituição de fotos, exclusão e rejeição de arquivos não permitidos.

---

## 🧪 Suíte de Validação
- **Pest:** 305 testes executados (**280 aprovados**, 25 skipped, **0 falhas**).
- **PHPStan:** **0 erros**.
- **ESLint & TypeScript:** **0 erros**.
- **Vite Build:** Compilação de produção efetuada com sucesso.
