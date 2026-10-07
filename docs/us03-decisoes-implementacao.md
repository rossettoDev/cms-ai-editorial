# US03 - Decisões de Implementação

## T03.3 - Mecanismo de Alias e Metadados

### Dependências Escolhidas

#### 1. Pathauto (^1.15)

**Por quê?**
- Módulo padrão da comunidade Drupal para geração automática de aliases de URL
- Garante aliases únicos automaticamente através do sistema de token e numeração incremental
- Permite padrões personalizados por tipo de conteúdo
- Integra-se nativamente com o Token module

**Como funciona:**
- Criamos o padrão `/artigo/[node:title]` para editorial_article
- O Pathauto converte o título em URL segura (transliteração, lowercase, hífens)
- Se houver conflito, adiciona sufixo numérico automaticamente (-1, -2, etc.)
- **Importante**: O Pathauto NUNCA aceita aliases sugeridos externamente sem validação
- Aliases são sempre gerados no servidor baseado em regras validadas

**Configuração:**
```
Padrão: /artigo/[node:title]
Tipo: editorial_article
Peso: -5 (processa antes de padrões genéricos)
```

#### 2. Metatag (^2.2)

**Por quê?**
- Módulo padrão para gerenciamento de meta tags (SEO)
- Suporta meta título, meta descrição, Open Graph, Twitter Cards
- Interface amigável para editores
- Valida e sanitiza automaticamente os valores

**Como funciona:**
- Adicionamos o campo `field_metatag` ao tipo editorial_article
- Editores podem preencher:
  - Meta título (usado em `<title>` e `og:title`)
  - Meta descrição (usado em `<meta name="description">` e `og:description`)
  - Outras tags Open Graph para redes sociais
- Se não preenchido, usa valores padrão (título e resumo do artigo)

**Módulos habilitados:**
- `metatag` (core)
- `metatag_open_graph` (tags para Facebook, LinkedIn, etc.)

#### 3. Token (^1.17)

**Por quê?**
- Dependência do Pathauto
- Fornece sistema de substituição de tokens ([node:title], etc.)
- Usado para gerar os aliases dinamicamente

### Dependências Adicionais Instaladas

#### CTools (^4.1)

- Dependência técnica do Metatag
- Fornece ferramentas de construção de interfaces
- Instalado automaticamente pelo Composer

## Segurança e Validação

### Alias Únicos
- Pathauto garante unicidade através de:
  1. Busca no banco por alias existente
  2. Adição de sufixo numérico se necessário
  3. Validação antes de salvar
- **Nunca** aceitamos alias sugerido por IA como identificador confiável
- O módulo `cms_ai_editorial.module` normaliza o título antes de gerar o alias

### Formatos de Texto Seguros
- Campo `body` usa formato `basic_html` (filtrado)
- Permite apenas tags seguras: `<p>, <br>, <strong>, <em>, <ul>, <ol>, <li>, <a>`
- XSS é prevenido automaticamente pelo sistema de filtros do Drupal

### Limites de Campos
- Título: 255 caracteres (validado no form e banco)
- Referências: 2000 caracteres por entrada, múltiplas entradas permitidas
- Body: ilimitado (text_long) mas com formato filtrado
- Tags: ilimitadas, auto-criação habilitada

### Revisões
- Habilitadas por padrão no tipo editorial_article (`new_revision: TRUE`)
- Todas as edições são versionadas
- Possível reverter para versões anteriores

## Estrutura Final

### Tipo de Conteúdo: editorial_article

**Campos:**
1. `title` (base field) - Título do artigo
   - Máximo: 255 caracteres
   - Obrigatório
   
2. `body` (text_with_summary) - Corpo do artigo
   - Com resumo separado
   - Formato: basic_html
   - Obrigatório
   
3. `field_tags` (entity_reference) - Tags de categorização
   - Referencia vocabulário: editorial_tags
   - Múltiplas tags permitidas
   - Auto-criação habilitada
   - Opcional
   
4. `field_references` (text_long) - Referências de origem
   - Múltiplas entradas permitidas
   - Máximo: 2000 caracteres por entrada
   - Opcional
   
5. `field_metatag` (metatag) - Meta tags SEO
   - Meta título
   - Meta descrição
   - Open Graph tags
   - Opcional (usa padrão se não preenchido)

**Configurações:**
- Revisões: habilitadas
- Workflow: rascunho → publicado (padrão Drupal)
- Alias: gerado automaticamente via Pathauto

### Vocabulário: editorial_tags

- Machine name: `editorial_tags`
- Descrição: Tags para categorização de artigos editoriais
- Auto-criação de termos: habilitada

## Como Verificar

### Via Drush
```bash
cd web
../vendor/bin/drush cex -y  # Exportar configuração
../vendor/bin/drush cr       # Limpar cache
```

### Criar Artigo Manualmente

1. Acessar /node/add/editorial_article
2. Preencher:
   - Título: "Meu Primeiro Artigo Editorial"
   - Resumo: "Este é um resumo do artigo"
   - Corpo: Conteúdo completo
   - Tags: adicionar algumas tags
   - Referências: URLs das fontes
   - Meta título: Título SEO customizado
   - Meta descrição: Descrição SEO customizada
3. Salvar
4. Verificar alias gerado: /artigo/meu-primeiro-artigo-editorial
5. Ver meta tags no HTML da página

### Testar Unicidade de Alias

1. Criar artigo com título "Teste"
2. Criar outro artigo com título "Teste"
3. Verificar que o segundo recebe alias "/artigo/teste-1"

## Referências

- [Pathauto Module](https://www.drupal.org/project/pathauto)
- [Metatag Module](https://www.drupal.org/project/metatag)
- [Token Module](https://www.drupal.org/project/token)
- [Drupal Content Entity API](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Entity%21ContentEntityInterface.php/interface/ContentEntityInterface/11)
