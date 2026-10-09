# US04 - Decisões de Implementação

## Visão Geral

Implementação de separação de responsabilidades entre solicitação, revisão e publicação de artigos editoriais através de workflow Content Moderation e papéis distintos.

## T04.1 - Workflow, Transições, Papéis e Matriz de Acesso

### Workflow: editorial

**Estados:**
- `draft` — Rascunho em edição pelo autor
- `needs_review` — Aguardando revisão
- `published` — Publicado e acessível ao público

**Transições:**
- `create_new_draft` — Criar novo rascunho (estado inicial)
- `submit_for_review` — Enviar para revisão (draft → needs_review)
- `approve_and_publish` — Aprovar e publicar (needs_review → published)
- `return_to_draft` — Devolver para edição (needs_review → draft)
- `unpublish` — Despublicar (published → draft)
- `create_revision_draft` — Criar revisão de artigo publicado (published → draft)

### Papéis

#### ai_editor
**Responsabilidades:**
- Criar rascunhos de artigos
- Editar seus próprios rascunhos
- Enviar para revisão
- Visualizar seus próprios artigos (independente do estado)

**Permissões:**
- `create editorial_article content`
- `edit own editorial_article content`
- `delete own editorial_article content`
- `view own unpublished editorial_article content`
- `use editorial transition create_new_draft`
- `use editorial transition submit_for_review`
- `use editorial transition return_to_draft` (para reverter envio acidental)
- `access content overview` (lista de conteúdo)

#### editorial_reviewer
**Responsabilidades:**
- Visualizar todos os artigos em revisão
- Aprovar e publicar artigos
- Devolver artigos para edição com justificativa
- Despublicar artigos quando necessário
- Criar revisões de artigos publicados

**Permissões:**
- `view any unpublished editorial_article content`
- `edit any editorial_article content`
- `use editorial transition approve_and_publish`
- `use editorial transition return_to_draft`
- `use editorial transition unpublish`
- `use editorial transition create_revision_draft`
- `access content overview`

#### anonymous / authenticated (visitantes)
**Restrições:**
- Apenas visualizam conteúdo publicado
- Sem acesso a rascunhos, revisões pendentes ou auditoria

### Matriz de Acesso

| Operação | anonymous | authenticated | ai_editor | editorial_reviewer | administrator |
|----------|-----------|---------------|-----------|-------------------|---------------|
| Visualizar publicado | ✓ | ✓ | ✓ | ✓ | ✓ |
| Visualizar rascunho próprio | ✗ | ✗ | ✓ (próprio) | ✓ (todos) | ✓ |
| Criar rascunho | ✗ | ✗ | ✓ | ✗ | ✓ |
| Editar rascunho próprio | ✗ | ✗ | ✓ | ✗ | ✓ |
| Editar qualquer artigo | ✗ | ✗ | ✗ | ✓ | ✓ |
| Enviar para revisão | ✗ | ✗ | ✓ | ✗ | ✓ |
| Aprovar/Publicar | ✗ | ✗ | ✗ | ✓ | ✓ |
| Devolver para edição | ✗ | ✗ | ✗ | ✓ | ✓ |
| Despublicar | ✗ | ✗ | ✗ | ✓ | ✓ |
| Criar revisão de publicado | ✗ | ✗ | ✗ | ✓ | ✓ |

## T04.2 - Acesso por Autoria e Papel

### Implementação via Módulo Custom

O módulo `cms_ai_editorial` implementará hooks para controle fino de acesso:

1. **hook_node_access()** — Controla acesso a nós individuais baseado em:
   - Estado do workflow (draft, needs_review, published)
   - Autoria (uid do node)
   - Papel do usuário atual

2. **hook_entity_access()** — Validação adicional para operações específicas

3. **Access Checks Customizados** — Para rotas administrativas e formulários

### Regras de Acesso

- **Editor (ai_editor):**
  - Pode ver apenas seus próprios rascunhos e artigos em revisão
  - Não pode ver rascunhos de outros editores
  - Pode ver todos os artigos publicados

- **Revisor (editorial_reviewer):**
  - Pode ver todos os artigos em qualquer estado
  - Pode editar qualquer artigo
  - Pode gerenciar workflow de todos os artigos

## T04.3 - Justificativa de Devolução

### Campo: field_return_justification

**Tipo:** Text (long)
**Entidade:** Node (editorial_article)
**Configuração:**
- Obrigatório: Não (preenchido apenas na devolução)
- Formato: Plain text
- Display: Oculto por padrão, visível apenas para revisores

### Implementação

1. **Form Alter** — Adicionar campo apenas quando revisor usa transição "return_to_draft"
2. **Validação Server-Side** — Verificar que justificativa foi fornecida ao devolver
3. **Node Save** — Armazenar justificativa no campo e registrar no log de revisões
4. **Display** — Mostrar última justificativa para o editor quando artigo é devolvido

### Código de Validação

```php
function cms_ai_editorial_node_validate(NodeInterface $node, FormStateInterface $form_state) {
  // Se transição é "return_to_draft", exigir justificativa
  if ($form_state->getValue('moderation_state') == 'draft' && 
      $node->moderation_state->value == 'needs_review') {
    if (empty($form_state->getValue('field_return_justification'))) {
      $form_state->setErrorByName('field_return_justification', 
        t('Justificativa é obrigatória ao devolver artigo para edição.'));
    }
  }
}
```

## T04.4 - Revisão Pendente sobre Artigo Publicado

### Comportamento Esperado

1. Artigo está no estado `published` (versão N pública)
2. Revisor cria nova revisão usando `create_revision_draft` (versão N+1 em draft)
3. Versão N continua pública e acessível
4. Versão N+1 é rascunho, invisível para visitantes
5. Ao aprovar versão N+1, ela substitui a versão N como pública

### Implementação

O módulo Content Moderation do Drupal 11 já implementa esse comportamento nativamente:
- Revisões pendentes não afetam a última revisão publicada
- `latest_revision` e `published_revision` são rastreadas separadamente
- Views filtram automaticamente por `published = 1`

### Teste Manual

1. Criar artigo e publicar
2. Editar artigo publicado (criar nova revisão)
3. Salvar como draft
4. Verificar que versão pública continua acessível
5. Publicar nova revisão
6. Verificar que nova versão substituiu a anterior

## Módulos Necessários

### Core
- `workflows` — Sistema de workflow base
- `content_moderation` — Moderação de conteúdo com revisões

### Contrib
- Nenhum adicional necessário

## Segurança

### Validação Server-Side

- Todas as transições de workflow são validadas no servidor
- Permissões são verificadas antes de qualquer mudança de estado
- Justificativa de devolução é obrigatória (validação no hook_validate)

### Proteção contra Escalação de Privilégios

- Editores não podem se atribuir papel de revisor
- Workers (usuários API futuros) não terão permissão de publicação
- Transições são restritas por papel

### Auditoria

- Todas as mudanças de estado são registradas pelo Drupal
- Log de revisões mantém histórico completo
- Justificativas de devolução ficam armazenadas no campo

## Configuração Exportada

Arquivos que serão criados/modificados em `config/sync/`:

- `workflows.workflow.editorial.yml` — Definição do workflow
- `user.role.ai_editor.yml` — Papel de editor
- `user.role.editorial_reviewer.yml` — Papel de revisor
- `field.storage.node.field_return_justification.yml` — Campo de justificativa
- `field.field.node.editorial_article.field_return_justification.yml` — Instância do campo
- `core.extension.yml` — Adicionar workflows e content_moderation
- `node.type.editorial_article.yml` — Habilitar moderação

## Testes

### Testes Funcionais (PHPUnit)

1. **WorkflowTransitionsTest**
   - Testar todas as transições de workflow
   - Verificar estados iniciais e finais
   - Validar restrições de transição

2. **RolePermissionsTest**
   - Testar permissões de ai_editor
   - Testar permissões de editorial_reviewer
   - Verificar isolamento de acesso (editor não vê rascunhos alheios)

3. **ReturnJustificationTest**
   - Testar que devolução sem justificativa falha
   - Testar que devolução com justificativa funciona
   - Verificar armazenamento da justificativa

4. **PendingRevisionTest**
   - Criar artigo publicado
   - Criar revisão pendente
   - Verificar que versão pública permanece acessível
   - Publicar revisão
   - Verificar que nova versão substituiu

### Testes Manuais

Documentados em `docs/us04-testes-manuais.md`

## Referências

- [Content Moderation Module](https://www.drupal.org/docs/8/core/modules/content-moderation)
- [Workflows API](https://api.drupal.org/api/drupal/core%21modules%21workflows%21workflows.api.php/group/workflows_api/11)
- [Node Access System](https://api.drupal.org/api/drupal/core%21modules%21node%21node.api.php/group/node_access/11)
