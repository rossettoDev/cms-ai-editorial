# US05 — Registrar Solicitação de Briefing para Geração Assistida

## Resumo

Implementa formulário e entidade persistente para registro de solicitações de briefing que orientarão a geração de artigos assistida por IA.

## Decisões de Implementação

### Entidade Content Entity

**Escolha**: Usar Content Entity em vez de Config Entity ou tabela custom.

**Justificativa**:
- Content Entities oferecem sistema completo de revisões e versionamento
- Integração nativa com Entity API, views, campos e permissões
- Suporte a campos configuráveis sem necessidade de schema manual
- Facilita auditoria e histórico de solicitações

### Estados da Solicitação

Definidos como constantes na classe `BriefingRequest`:

- `pending`: Aguardando processamento (estado inicial)
- `processing`: Em processamento pela IA
- `completed`: Processamento concluído com sucesso
- `failed`: Processamento falhou

**Fluxo previsto** (implementação futura):
1. Usuário submete formulário → `pending`
2. Worker pega da fila → `processing`
3. IA gera artigo → `completed` (ou `failed` se erro)

### ID de Correlação

Formato: `BR-YYYYMMDDHHmmss-[16 caracteres hex aleatórios]`

Exemplo: `BR-20261013213045-a1b2c3d4e5f6g7h8`

**Propósito**:
- Rastreamento único da solicitação em logs
- Referência cruzada entre solicitação e artigo gerado
- Identificação amigável para usuário

**Implementação**: Gerado automaticamente via `BaseFieldDefinition::setDefaultValueCallback()`.

### Campos do Briefing

| Campo | Tipo | Obrigatório | Limite | Descrição |
|-------|------|-------------|--------|-----------|
| `objective` | string_long | Sim | 1000 chars | Objetivo do artigo |
| `audience` | string | Sim | 255 chars | Público-alvo |
| `tone` | list_string | Sim | N/A | Tom do artigo (formal, informal, technical, etc.) |
| `language` | list_string | Sim | N/A | Idioma (pt-BR, en-US, es-ES) |
| `instructions` | text_long | Sim | 2000 chars | Instruções específicas |
| `reference_text` | text_long | Não | 5000 chars | Material de referência opcional |

### Snapshot do Briefing

Armazenado como JSON serializado no campo `briefing_snapshot`.

**Propósito**:
- Registro imutável dos dados no momento da submissão
- Permite auditoria e replay da geração
- Preserva histórico mesmo se campos forem alterados posteriormente

**Criação**: Método `createBriefingSnapshot()` chamado antes do primeiro save.

### Controle de Acesso

**Access Control Handler**: `BriefingRequestAccessControlHandler`

**Regras**:
- **Criar**: Usuários com permissão `create briefing request`
- **Visualizar**: Usuário autor OU usuários com `view any briefing request`
- **Editar**: Apenas autor E somente se status = `pending`
- **Deletar**: Apenas autor E somente se status = `pending` ou `failed`
- **Admin**: Usuários com `administer briefing requests` têm acesso total

**Permissões**:
- `create briefing request` → AI Editor
- `view own briefing request` → AI Editor
- `view any briefing request` → Editorial Reviewer
- `edit own briefing request` → AI Editor
- `delete own briefing request` → AI Editor
- `administer briefing requests` → Apenas admin

### Validação

**Validação server-side** no `BriefingRequestForm::validateForm()`:

1. Tamanho máximo de campos:
   - `objective`: 1000 caracteres
   - `audience`: 255 caracteres
   - `instructions`: 2000 caracteres
   - `reference_text`: 5000 caracteres

2. Campos obrigatórios:
   - `objective`
   - `audience`
   - `instructions`
   - `tone`
   - `language`

3. Validação no nível de campo via BaseFieldDefinition (required, max_length)

### Proteção CSRF

Implementada nativamente pelo Drupal Form API:
- Token `form_token` automático
- `form_build_id` para validação
- Validação no `FormBase::validateForm()`

### Rotas

| Rota | Path | Acesso |
|------|------|--------|
| `entity.briefing_request.add_form` | `/briefing-request/add` | `create briefing request` |
| `entity.briefing_request.canonical` | `/briefing-request/{id}` | `view` na entidade |
| `cms_ai_editorial.briefing_request.confirmation` | `/briefing-request/{id}/confirmation` | `view` na entidade |
| `cms_ai_editorial.briefing_request.user_list` | `/briefing-request/my-requests` | `create briefing request` |
| `entity.briefing_request.collection` | `/admin/content/briefing-requests` | `administer briefing requests` |

### Fluxo de Submissão

1. Usuário acessa `/briefing-request/add`
2. Preenche formulário com validação client-side (maxlength)
3. Submete formulário (POST com CSRF token)
4. Validação server-side (`validateForm()`)
5. Criação da entidade com status `pending`
6. Geração do correlation ID
7. Criação do snapshot JSON
8. Save da entidade
9. Redirecionamento para `/briefing-request/{id}/confirmation`
10. Exibição de confirmação com:
    - ID de correlação
    - Detalhes do briefing
    - Links: visualizar completo, minhas solicitações, nova solicitação

### Página de Listagem

**Rota**: `/briefing-request/my-requests`

**Funcionalidade**:
- Lista apenas solicitações do usuário autenticado
- Ordenadas por data de criação (mais recente primeiro)
- Exibe: ID de correlação, objetivo (truncado), estado, data de criação
- Link para visualizar cada solicitação
- Botão para criar nova solicitação

**Implementação**: `BriefingRequestController::userList()`

### Página de Administração

**Rota**: `/admin/content/briefing-requests`

**Funcionalidade**:
- Lista todas as solicitações do sistema
- Disponível apenas para administradores
- Usa `BriefingRequestListBuilder` padrão

## Testes Implementados

### Testes Kernel

**Arquivo**: `tests/src/Kernel/BriefingRequestEntityTest.php`

Casos:
- `testBriefingRequestCreation()`: Criação básica e validação de campos
- `testCorrelationIdGeneration()`: Unicidade do correlation ID
- `testBriefingSnapshot()`: Criação e estrutura do snapshot JSON
- `testStatusChange()`: Transições de estado
- `testRequiredFields()`: Validação de campos obrigatórios

### Testes Funcionais

**Arquivo**: `tests/src/Functional/BriefingRequestFormTest.php`

Casos:
- `testFormAccess()`: Controle de acesso ao formulário
- `testValidFormSubmission()`: Fluxo completo de submissão válida
- `testRequiredFieldsValidation()`: Validação de campos obrigatórios
- `testMaxLengthValidation()`: Validação de tamanho máximo
- `testOptionalReferenceText()`: Campo opcional funciona
- `testReferenceTextMaxLength()`: Validação de limite do texto de referência
- `testCsrfProtection()`: Presença de tokens CSRF
- `testUserRequestsList()`: Listagem de solicitações do usuário
- `testCompleteWorkflow()`: Fluxo completo end-to-end

**Arquivo**: `tests/src/Functional/BriefingRequestAccessTest.php`

Casos:
- `testUserCanOnlyViewOwnRequests()`: Isolamento entre usuários
- `testReviewerCanViewAllRequests()`: Acesso de reviewer
- `testUserCanOnlyEditOwnPendingRequests()`: Restrição de edição
- `testUserCanOnlyDeleteOwnPendingOrFailedRequests()`: Restrição de deleção

## Integração com US04

A US05 complementa a US04 (workflow editorial) preparando o terreno para a geração assistida por IA:

- US04: Define workflow de artigos (draft → review → published)
- US05: Define entrada de briefing para geração de artigos

**Integração futura** (outras US):
1. Usuário cria briefing request (US05) → estado `pending`
2. Worker consome fila e chama provedor IA (US futura)
3. IA gera artigo baseado no briefing
4. Sistema cria `editorial_article` em estado `draft` (US04)
5. Editor revisa e submete para review (US04)
6. Reviewer aprova e publica (US04)

## Comandos para Teste Local (Lando)

```bash
# Reconstruir cache e importar configurações
lando drush cr
lando drush config:import -y

# Rodar testes Kernel
lando php web/core/scripts/run-tests.sh \
  --verbose \
  --class 'Drupal\Tests\cms_ai_editorial\Kernel\BriefingRequestEntityTest'

# Rodar testes Funcionais
lando php web/core/scripts/run-tests.sh \
  --verbose \
  --url https://cms-ai-editorial.lndo.site \
  --class 'Drupal\Tests\cms_ai_editorial\Functional\BriefingRequestFormTest'

lando php web/core/scripts/run-tests.sh \
  --verbose \
  --url https://cms-ai-editorial.lndo.site \
  --class 'Drupal\Tests\cms_ai_editorial\Functional\BriefingRequestAccessTest'

# Exportar configurações
lando drush config:export -y
```

## Teste Manual no Lando

1. Criar usuário editor:
```bash
lando drush user:create editor_teste --mail="editor@test.com" --password="test123"
lando drush user:role:add ai_editor editor_teste
```

2. Acessar formulário:
   - Login como `editor_teste`
   - Navegar para `/briefing-request/add`

3. Preencher e submeter:
   - Objetivo: "Criar artigo sobre Drupal 11"
   - Público-alvo: "Desenvolvedores PHP"
   - Tom: "Técnico"
   - Idioma: "Português (Brasil)"
   - Instruções: "Focar nas principais novidades e melhorias"
   - Referência (opcional): Material sobre Drupal 11

4. Verificar confirmação:
   - ID de correlação exibido
   - Detalhes corretos
   - Links funcionais

5. Verificar listagem:
   - Acessar `/briefing-request/my-requests`
   - Solicitação aparece na lista
   - Link "Visualizar" funciona

6. Testar isolamento:
   - Criar segundo usuário editor
   - Verificar que cada editor vê apenas suas próprias solicitações

## Arquivos Modificados/Criados

### Novos arquivos:

- `src/Entity/BriefingRequest.php` - Entidade principal
- `src/Entity/BriefingRequestAccessControlHandler.php` - Controle de acesso
- `src/Entity/BriefingRequestListBuilder.php` - List builder admin
- `src/Form/BriefingRequestForm.php` - Formulário
- `src/Controller/BriefingRequestController.php` - Controller
- `cms_ai_editorial.routing.yml` - Rotas
- `cms_ai_editorial.permissions.yml` - Permissões
- `cms_ai_editorial.links.menu.yml` - Links de menu
- `tests/src/Kernel/BriefingRequestEntityTest.php` - Testes Kernel
- `tests/src/Functional/BriefingRequestFormTest.php` - Testes funcionais
- `tests/src/Functional/BriefingRequestAccessTest.php` - Testes de acesso

### Arquivos modificados:

- `config/sync/user.role.ai_editor.yml` - Adicionadas permissões de briefing
- `config/sync/user.role.editorial_reviewer.yml` - Adicionada permissão view any

## Pendências Futuras (Outras US)

- US06+: Integração com provedor de IA (Claude, OpenAI, etc.)
- US07+: Fila assíncrona para processamento
- US08+: Worker para consumir fila e chamar IA
- US09+: Criação automática de `editorial_article` a partir do briefing
- US10+: Notificações de progresso ao usuário
- US11+: Retry em caso de falha
- US12+: Dashboard de monitoramento

## Critérios de Aceite - Status

✅ Formulário recebe objetivo, público, tom, idioma, instruções e texto de referência opcional
✅ Solicitação persistida registra autor, entradas e estado
✅ Usuário só acessa solicitações autorizadas (controle de acesso)
✅ Limites de tamanho e campos obrigatórios são validados antes do enfileiramento
✅ Submissão tem proteção CSRF (Form API nativo)
✅ Não dispara geração longa na requisição web (apenas cria entidade)
✅ Snapshot do briefing e ID de correlação registrados
✅ Página de confirmação e link de acompanhamento

## Referências

- [Drupal Content Entity API](https://www.drupal.org/docs/drupal-apis/entity-api/creating-a-custom-content-entity-type-in-drupal)
- [Form API](https://api.drupal.org/api/drupal/core!core.api.php/group/form_api)
- [Access Control](https://www.drupal.org/docs/drupal-apis/entity-api/entity-access-api)
