# US06 — Testes Manuais

## Pré-requisitos

1. Ambiente Lando iniciado: `lando start`
2. Módulo cms_ai_editorial habilitado
3. Schema de banco atualizado (reinstalar ou update)
4. Usuário com papel `ai_editor` ou `editorial_reviewer`

## Instalação/Atualização do Módulo

```bash
# Limpar cache
lando drush cache:rebuild

# Reinstalar módulo (se necessário)
lando drush pm:uninstall cms_ai_editorial -y
lando drush pm:enable cms_ai_editorial -y

# Ou apenas atualizar
lando drush updb -y

# Verificar schema
lando drush sqlq "SHOW TABLES LIKE 'source_import'"
lando drush sqlq "DESCRIBE source_import"
```

## Cenários de Teste

### 1. Acesso ao Formulário

**Como Editor AI:**

```
1. Login com usuário ai_editor
2. Acessar /briefing-request/import-url
3. ✅ Deve exibir formulário de importação
4. ✅ Deve exibir campo "URL da página"
5. ✅ Deve exibir botão "Buscar e Visualizar"
```

**Como Usuário Sem Permissão:**

```
1. Login com usuário sem permissões
2. Acessar /briefing-request/import-url
3. ✅ Deve retornar 403 Forbidden
```

### 2. Proteções SSRF

#### 2.1. Bloqueio de Localhost

```
URL: http://localhost/test
✅ Deve exibir erro: "Acesso a este host é bloqueado"
```

```
URL: http://127.0.0.1/test
✅ Deve exibir erro: "Host resolve para endereço IP bloqueado"
```

#### 2.2. Bloqueio de IPs Privados

```
URL: http://192.168.1.1/test
✅ Deve exibir erro: "Host resolve para endereço IP bloqueado"
```

```
URL: http://10.0.0.1/test
✅ Deve exibir erro: "Host resolve para endereço IP bloqueado"
```

```
URL: http://172.16.0.1/test
✅ Deve exibir erro: "Host resolve para endereço IP bloqueado"
```

#### 2.3. Bloqueio de IP de Metadados

```
URL: http://169.254.169.254/latest/meta-data
✅ Deve exibir erro: "Host resolve para endereço IP bloqueado"
```

#### 2.4. Bloqueio de Esquemas Inválidos

```
URL: ftp://example.com/file.txt
✅ Deve exibir erro: "Apenas URLs HTTP e HTTPS públicas são permitidas"
```

```
URL: file:///etc/passwd
✅ Deve exibir erro: "Apenas URLs HTTP e HTTPS públicas são permitidas"
```

### 3. Redirects

#### 3.1. Redirect Simples (Válido)

**Setup:**
Criar arquivo PHP em servidor de teste:

```php
<?php
// redirect-simple.php
header('Location: https://example.com/final');
exit;
```

```
URL: http://seu-servidor/redirect-simple.php
✅ Deve seguir redirect
✅ Deve exibir URL final: https://example.com/final
```

#### 3.2. Redirect para Localhost (Malicioso)

**Setup:**

```php
<?php
// redirect-malicious.php
header('Location: http://localhost/test');
exit;
```

```
URL: http://seu-servidor/redirect-malicious.php
✅ Deve exibir erro: "Redirect para host bloqueado"
```

#### 3.3. Redirect em Cadeia (Excede Limite)

**Setup:**

```php
<?php
// redirect-1.php
header('Location: /redirect-2.php');
exit;
```

```php
<?php
// redirect-2.php
header('Location: /redirect-3.php');
exit;
```

(Criar até redirect-6.php)

```
URL: http://seu-servidor/redirect-1.php
✅ Deve exibir erro: "Número máximo de redirects excedido"
```

### 4. Validação de Conteúdo

#### 4.1. HTML Válido

**Setup:**

```html
<!DOCTYPE html>
<html>
<head>
    <title>Artigo de Teste</title>
</head>
<body>
    <article>
        <h1>Título Principal</h1>
        <p>Primeiro parágrafo do artigo.</p>
        <p>Segundo parágrafo do artigo.</p>
    </article>
</body>
</html>
```

```
URL: http://seu-servidor/article.html
✅ Deve exibir preview
✅ Título extraído: "Artigo de Teste"
✅ Texto deve conter: "Primeiro parágrafo" e "Segundo parágrafo"
✅ Botão "Usar Este Texto" visível
```

#### 4.2. Tipo de Conteúdo Inválido

**Setup:**
Servir arquivo PDF ou imagem

```
URL: http://seu-servidor/document.pdf
✅ Deve exibir erro: "Tipo de conteúdo não suportado"
```

#### 4.3. Arquivo Grande (> 2MB)

**Setup:**
Criar HTML com > 2MB de conteúdo

```php
<?php
echo '<!DOCTYPE html><html><body>';
echo str_repeat('<p>Lorem ipsum...</p>', 100000);
echo '</body></html>';
```

```
URL: http://seu-servidor/large.php
✅ Deve exibir erro: "Conteúdo da página excede o tamanho máximo"
```

#### 4.4. HTML sem Conteúdo Textual

**Setup:**

```html
<!DOCTYPE html>
<html>
<head><title>Título</title></head>
<body>
    <img src="image.jpg" />
</body>
</html>
```

```
URL: http://seu-servidor/no-text.html
✅ Deve exibir erro: "Não foi possível extrair texto relevante"
```

### 5. Extração de Conteúdo

#### 5.1. Remoção de Scripts

**Setup:**

```html
<!DOCTYPE html>
<html>
<body>
    <p>Texto visível</p>
    <script>alert('XSS');</script>
</body>
</html>
```

```
✅ Texto extraído não deve conter "alert" ou "XSS"
✅ Texto extraído deve conter "Texto visível"
```

#### 5.2. Remoção de Navegação

**Setup:**

```html
<!DOCTYPE html>
<html>
<body>
    <nav>Menu | Home | About</nav>
    <article>
        <p>Conteúdo principal</p>
    </article>
</body>
</html>
```

```
✅ Texto extraído não deve conter "Menu | Home | About"
✅ Texto extraído deve conter "Conteúdo principal"
```

### 6. Integração com Briefing Request

#### 6.1. Link no Formulário de Briefing

```
1. Acessar /briefing-request/add
2. ✅ Deve exibir campo "Texto de referência"
3. ✅ Deve exibir link "Importar de URL" na descrição
4. Clicar no link
5. ✅ Deve abrir formulário de importação (modal ou nova página)
```

#### 6.2. Uso do Texto Importado

```
1. Importar URL válida
2. Clicar em "Usar Este Texto"
3. ✅ Formulário de importação deve fechar (se modal)
4. ✅ Campo "Texto de referência" deve ser preenchido
5. Submeter briefing request
6. ✅ Deve criar BriefingRequest com texto de referência
7. ✅ Deve criar SourceImport vinculada
```

### 7. Controle de Acesso a SourceImport

#### 7.1. Proprietário Visualiza Própria Importação

```
1. Importar URL como usuário A
2. ✅ Entidade SourceImport criada com uid = A
3. Como usuário A, visualizar importação
4. ✅ Deve permitir acesso
```

#### 7.2. Outro Usuário Não Visualiza Importação Alheia

```
1. Importar URL como usuário A
2. Como usuário B, tentar visualizar importação de A
3. ✅ Deve retornar 403 Forbidden
```

#### 7.3. Importações Não Podem Ser Editadas

```
1. Importar URL como usuário A
2. Tentar editar entidade SourceImport
3. ✅ Não deve haver formulário de edição
4. ✅ Tentativa programática deve retornar forbidden
```

#### 7.4. Admin Tem Acesso Total

```
1. Importar URL como usuário A
2. Como admin, visualizar importação de A
3. ✅ Deve permitir acesso
4. Como admin, deletar importação de A
5. ✅ Deve permitir exclusão
```

### 8. Casos Especiais

#### 8.1. URL com Charset Não-UTF-8

**Setup:**
Servir HTML com charset ISO-8859-1

```html
<!DOCTYPE html>
<html>
<head>
    <meta charset="ISO-8859-1">
    <title>Título com Acentuação</title>
</head>
<body>
    <p>Conteúdo com acentuação: à é ô ü ç</p>
</body>
</html>
```

```
✅ Deve extrair corretamente (conversão para UTF-8)
✅ Acentos devem ser preservados
```

#### 8.2. HTML Malformado

**Setup:**

```html
<html>
<p>Parágrafo sem fechamento
<div>Div sem fechamento
<p>Outro parágrafo
</html>
```

```
✅ Parser deve tentar recuperar conteúdo
✅ Não deve lançar erro fatal
✅ Deve extrair texto visível
```

#### 8.3. Timeout

**Setup:**
Criar endpoint que demora > 15 segundos

```php
<?php
sleep(20);
echo '<html><body>Conteúdo</body></html>';
```

```
URL: http://seu-servidor/slow.php
✅ Deve exibir erro: "Não foi possível conectar ao servidor" (timeout)
```

## Checklist de Validação Final

- [ ] Todas as proteções SSRF funcionando
- [ ] Limites de tamanho e timeout aplicados
- [ ] Redirects validados corretamente
- [ ] Parser HTML extrai conteúdo corretamente
- [ ] Controle de acesso funcionando
- [ ] Integração com BriefingRequest funcional
- [ ] Permissões corretas para ai_editor e editorial_reviewer
- [ ] Testes unitários passando (quando ambiente disponível)

## Comandos Úteis

```bash
# Verificar logs de erro
lando logs -f

# Verificar entidades criadas
lando drush sqlq "SELECT * FROM source_import ORDER BY id DESC LIMIT 5"

# Verificar permissões de role
lando drush role:perm:list ai_editor
lando drush role:perm:list editorial_reviewer

# Limpar cache
lando drush cache:rebuild

# Executar testes
lando drush test:run cms_ai_editorial
```
