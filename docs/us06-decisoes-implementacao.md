# US06 — Importar uma fonte por URL com limites

## Decisões de Implementação

### Visão Geral

A US06 implementa a funcionalidade de importar conteúdo de URLs externas como material de referência para a geração de artigos, com rigorosas proteções contra SSRF (Server-Side Request Forgery) e outros ataques.

### Arquitetura da Solução

#### 1. Serviços Criados

##### UrlFetchService (`src/Service/UrlFetchService.php`)

Serviço isolado responsável por buscar conteúdo de URLs com múltiplas camadas de proteção:

**Proteções SSRF Implementadas:**

- **Validação de Esquema**: Apenas `http://` e `https://` são permitidos
- **Bloqueio de Hosts Conhecidos**: `localhost`, `0.0.0.0`, etc.
- **Bloqueio de Ranges de IP Privados**:
  - IPv4: `127.0.0.0/8`, `10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`, `169.254.0.0/16`
  - IPv6: `::1/128`, `fe80::/10`, `fc00::/7`, `fd00::/8`
- **Bloqueio de IPs de Metadados**: `169.254.169.254` (AWS/GCP/Azure)
- **Validação DNS**: Resolve o hostname e valida se os IPs resolvidos estão em ranges bloqueados
- **Validação de Redirects**: Cada redirect é validado antes de ser seguido
  - Máximo de 5 redirects
  - Re-validação de DNS e IP após cada redirect
  - Bloqueio de mudança de esquema em redirects

**Limites de Segurança:**

- **Timeout**: 15 segundos por requisição
- **Tamanho Máximo**: 2MB de conteúdo
- **Tipos de Conteúdo**: Apenas `text/html` e `application/xhtml+xml`
- **Redirects**: Máximo de 5

**Implementação de Validação de IP:**

- Suporte completo a IPv4 e IPv6
- Validação de ranges CIDR para ambos os protocolos
- Algoritmos otimizados para verificação de ranges

##### HtmlParserService (`src/Service/HtmlParserService.php`)

Serviço responsável por extrair título e texto relevante de HTML:

**Extração de Título:**

Prioridade de extração:
1. Tag `<title>`
2. Meta tag `og:title` (Open Graph)
3. Primeira tag `<h1>`
4. Fallback: "Sem título"

**Extração de Texto:**

- Remove tags não relevantes: `<script>`, `<style>`, `<nav>`, `<footer>`, `<header>`, `<aside>`, `<iframe>`, `<noscript>`
- Prioriza elementos semânticos: `<article>`, `<main>`
- Fallback para IDs/classes comuns: `#content`, `#main`, `.content`, `.entry-content`, etc.
- Preserva estrutura de parágrafos e elementos de bloco
- Normaliza espaços em branco e quebras de linha

**Limites:**

- Tamanho máximo do texto extraído: 50KB
- Textos maiores são truncados com indicador `...`

#### 2. Entidade SourceImport

Nova entidade de conteúdo para armazenar fontes importadas com acesso restrito.

**Campos:**

- `source_url`: URL original fornecida pelo usuário
- `final_url`: URL final após seguir redirects
- `extracted_title`: Título extraído da página
- `extracted_text`: Texto relevante extraído
- `content_hash`: Hash SHA-256 do conteúdo HTML original
- `import_date`: Timestamp da importação
- `uid`: Proprietário (autor da importação)

**Controle de Acesso:**

- Apenas o proprietário pode visualizar suas próprias importações
- Importações não podem ser editadas (imutáveis após criação)
- Apenas o proprietário pode deletar suas próprias importações
- Administradores têm acesso total

**Permissões:**

- `create source imports`: Criar novas importações
- `view own source imports`: Visualizar próprias importações
- `delete own source imports`: Deletar próprias importações
- `administer source imports`: Administração total (acesso restrito)

#### 3. Formulário de Importação

**UrlImportForm (`src/Form/UrlImportForm.php`)**

Formulário standalone para importar conteúdo de URLs:

**Fluxo de Uso:**

1. Usuário cola a URL desejada
2. Clica em "Buscar e Visualizar"
3. Sistema busca e extrai o conteúdo
4. Preview é exibido com:
   - URL final
   - Título extraído
   - Tamanho do texto
   - Texto completo (somente leitura)
5. Usuário pode:
   - "Usar Este Texto": Cria entidade SourceImport e preenche campo de referência
   - "Cancelar": Volta ao formulário inicial

**Integração com BriefingRequestForm:**

- Link "Importar de URL" adicionado na descrição do campo `reference_text`
- Preparado para abertura em modal (biblioteca `core/drupal.dialog.ajax`)
- Após importação, texto é preenchido automaticamente no campo

#### 4. Integração com BriefingRequest

**Novo Campo:**

- `source_import`: Referência opcional à entidade SourceImport
- Permite rastrear qual importação foi usada em cada briefing

### Decisões Técnicas

#### Uso de Guzzle HTTP Client

- Cliente HTTP do Drupal (Guzzle) via `HttpClientFactory`
- Controle manual de redirects para validação incremental
- Stream mode para leitura controlada de grandes respostas

#### Parser HTML Nativo

- Uso de `DOMDocument` (PHP nativo) em vez de bibliotecas externas
- Tratamento de erros com `libxml_use_internal_errors()`
- Encoding forçado para UTF-8 via `mb_convert_encoding()`
- XPath para seletores CSS-like

#### Armazenamento de Hash

- Hash SHA-256 do conteúdo HTML original
- Permite detecção de mudanças em re-importações futuras
- Não armazena o HTML completo (apenas texto extraído)

### Testes Implementados

#### Testes de Kernel

**UrlFetchServiceTest:**

- Bloqueio de localhost
- Bloqueio de IPs privados (IPv4 e IPv6)
- Bloqueio de IP de metadados cloud (169.254.169.254)
- Bloqueio de esquemas inválidos (ftp, file, data)
- Validação de URLs inválidas
- Testes de algoritmos de range CIDR para IPv4 e IPv6

**HtmlParserServiceTest:**

- Extração de título de múltiplas fontes
- Extração de texto simples
- Remoção de scripts e estilos
- Extração de elementos semânticos
- Normalização de espaços em branco
- Truncamento de textos grandes
- Rejeição de HTML vazio ou sem texto

**SourceImportAccessTest:**

- Proprietário pode visualizar própria importação
- Outros usuários não podem visualizar importações alheias
- Importações não podem ser editadas
- Proprietário pode deletar própria importação
- Outros usuários não podem deletar importações alheias
- Administradores têm acesso total

#### Testes Funcionais

**UrlImportFormTest:**

- Acesso negado para usuários não autorizados
- Acesso permitido para usuários autorizados
- Validação de URL vazia
- Bloqueio de URL localhost
- Bloqueio de IP privado
- Bloqueio de esquema inválido

### Cenários de Teste Manual

#### T06.5: Testes de Segurança

**1. Redirects Maliciosos:**

```bash
# Testar redirect para localhost
curl http://example.com/redirect-to-localhost
# Deve ser bloqueado após seguir redirect

# Testar redirect para IP privado
curl http://example.com/redirect-to-192.168.1.1
# Deve ser bloqueado após seguir redirect

# Testar redirect em cadeia com destino malicioso
curl http://example.com/chain-redirect
# Deve ser bloqueado no primeiro redirect malicioso
```

**2. DNS/Destino:**

```bash
# Testar domínio que resolve para IP privado
dig +short localhost-resolver.example.com
# 127.0.0.1
# Deve ser bloqueado após resolução DNS

# Testar domínio que resolve para metadata IP
dig +short metadata-resolver.example.com
# 169.254.169.254
# Deve ser bloqueado após resolução DNS
```

**3. Arquivo Grande:**

```bash
# Testar arquivo > 2MB
curl http://example.com/large-file.html
# Deve ser truncado ou rejeitado ao atingir limite
```

**4. Conteúdo Inválido:**

```bash
# Testar tipo de conteúdo inválido
curl http://example.com/file.pdf
# Deve ser rejeitado (apenas text/html permitido)

# Testar HTML malformado
curl http://example.com/malformed.html
# Deve tentar parsear ou rejeitar graciosamente

# Testar HTML sem conteúdo textual
curl http://example.com/empty.html
# Deve ser rejeitado (sem texto relevante)
```

### Melhorias Futuras (Fora do Escopo)

- Suporte a múltiplas URLs por solicitação
- Cache de importações duplicadas (por hash)
- Re-importação para detectar mudanças
- Suporte a autenticação básica (opcional)
- Whitelist de domínios confiáveis
- Integração com serviços de captura (Wayback Machine)
- Detecção de charset automática avançada
- Suporte a conteúdo JavaScript-renderizado

### Compatibilidade

- **Drupal**: 11.x
- **PHP**: 8.3+
- **Guzzle**: 7.x (incluído no Drupal 11)
- **Extensões PHP Necessárias**: 
  - `dom`: Para DOMDocument
  - `mbstring`: Para manipulação UTF-8
  - `libxml`: Para parsing XML/HTML

### Referências

- [OWASP SSRF Prevention Cheat Sheet](https://cheatsheetsecurity.owasp.org/cheatsheets/Server_Side_Request_Forgery_Prevention_Cheat_Sheet.html)
- [Guzzle Documentation](https://docs.guzzlephp.org/)
- [PHP DOMDocument](https://www.php.net/manual/en/class.domdocument.php)
- [IPv6 CIDR Calculation](https://www.rfc-editor.org/rfc/rfc4291)
