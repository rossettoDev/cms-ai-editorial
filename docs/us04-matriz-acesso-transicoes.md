# US04 - Matriz de Acesso e Transições de Workflow

## Workflow Editorial

O workflow `editorial` controla o ciclo de vida dos artigos editoriais, garantindo separação entre solicitação, revisão e publicação.

### Estados (Moderation States)

| Estado | Nome | Descrição | Publicado | Revisão Padrão |
|--------|------|-----------|-----------|----------------|
| `draft` | Draft | Rascunho em edição | Não | Não |
| `needs_review` | Needs Review | Aguardando revisão editorial | Não | Não |
| `published` | Published | Publicado e acessível ao público | Sim | Sim |

### Transições

```
┌─────────────────────────────────────────────────────────────┐
│                    Workflow Editorial                        │
└─────────────────────────────────────────────────────────────┘

    [draft] ────────────────────────────────────┐
       │                                        │
       │ submit_for_review                     │
       │                                        │
       ▼                                        │
  [needs_review] ──────────────┐               │
       │                       │               │
       │ approve_and_publish   │ return_to     │ create_new
       │                       │    _draft     │   _draft
       ▼                       │               │
  [published]                  │               │
       │                       │               │
       │ unpublish             │               │
       └───────────────────────┴───────────────┘
       │
       │ create_revision_draft
       │
       └──────────────────────► [draft]
```

#### 1. create_new_draft
- **De**: draft
- **Para**: draft
- **Quem pode usar**: ai_editor, editorial_reviewer, administrator
- **Descrição**: Cria um novo rascunho (estado inicial de novos artigos)

#### 2. submit_for_review
- **De**: draft
- **Para**: needs_review
- **Quem pode usar**: ai_editor, administrator
- **Descrição**: Editor envia artigo para revisão editorial

#### 3. approve_and_publish
- **De**: needs_review
- **Para**: published
- **Quem pode usar**: editorial_reviewer, administrator
- **Descrição**: Revisor aprova e publica o artigo

#### 4. return_to_draft
- **De**: needs_review
- **Para**: draft
- **Quem pode usar**: editorial_reviewer, administrator
- **Descrição**: Revisor devolve artigo para edição (requer justificativa)
- **Validação**: Campo `field_return_justification` é obrigatório

#### 5. unpublish
- **De**: published
- **Para**: draft
- **Quem pode usar**: editorial_reviewer, administrator
- **Descrição**: Despublica um artigo (remove do acesso público)

#### 6. create_revision_draft
- **De**: published
- **Para**: draft
- **Quem pode usar**: editorial_reviewer, administrator
- **Descrição**: Cria nova revisão de artigo publicado
- **Importante**: Versão publicada permanece acessível até nova revisão ser aprovada

## Papéis e Permissões

### anonymous (Visitante Anônimo)
**Descrição**: Usuário não autenticado.

**Permissões**:
- Visualizar conteúdo publicado

**Restrições**:
- ❌ Sem acesso a rascunhos
- ❌ Sem acesso a artigos em revisão
- ❌ Sem acesso a revisões pendentes

---

### authenticated (Usuário Autenticado)
**Descrição**: Usuário autenticado sem papéis específicos.

**Permissões**:
- Visualizar conteúdo publicado

**Restrições**:
- ❌ Sem permissões de criação ou edição
- ❌ Sem acesso a rascunhos ou revisões

---

### ai_editor (Editor de IA)
**Descrição**: Editor responsável por criar e editar artigos, com auxílio de IA.

**Permissões**:
- ✅ `create editorial_article content` — Criar artigos
- ✅ `edit own editorial_article content` — Editar seus próprios artigos
- ✅ `delete own editorial_article content` — Deletar seus próprios artigos
- ✅ `view own unpublished editorial_article content` — Ver seus rascunhos
- ✅ `use editorial transition create_new_draft` — Criar novo rascunho
- ✅ `use editorial transition submit_for_review` — Enviar para revisão
- ✅ `access content overview` — Acessar lista de conteúdo

**Restrições**:
- ❌ Não pode ver rascunhos de outros editores
- ❌ Não pode editar artigos de outros editores
- ❌ Não pode aprovar/publicar artigos
- ❌ Não pode despublicar artigos
- ❌ Não pode criar revisões de artigos publicados

**Fluxo de Trabalho**:
1. Cria rascunho (draft)
2. Edita o artigo
3. Envia para revisão (submit_for_review → needs_review)
4. Aguarda aprovação ou devolução do revisor
5. Se devolvido, recebe justificativa e edita novamente

---

### editorial_reviewer (Revisor Editorial)
**Descrição**: Revisor responsável por aprovar, devolver ou despublicar artigos.

**Permissões**:
- ✅ `view any unpublished editorial_article content` — Ver todos os artigos não publicados
- ✅ `edit any editorial_article content` — Editar qualquer artigo
- ✅ `use editorial transition approve_and_publish` — Aprovar e publicar
- ✅ `use editorial transition return_to_draft` — Devolver para edição
- ✅ `use editorial transition unpublish` — Despublicar
- ✅ `use editorial transition create_revision_draft` — Criar revisão de publicado
- ✅ `view all revisions` — Ver todas as revisões
- ✅ `access content overview` — Acessar lista de conteúdo

**Restrições**:
- ❌ Não cria artigos novos (apenas revisa e gerencia existentes)

**Fluxo de Trabalho**:
1. Recebe artigo em needs_review
2. Revisa o conteúdo
3. Decide:
   - **Aprovar**: approve_and_publish → published
   - **Devolver**: return_to_draft → draft (com justificativa obrigatória)
4. Pode despublicar artigos se necessário: unpublish
5. Pode criar revisões de artigos publicados: create_revision_draft

---

### administrator (Administrador)
**Descrição**: Acesso total ao sistema.

**Permissões**:
- ✅ Todas as permissões acima
- ✅ `administer nodes` — Gerenciar todos os nós
- ✅ `administer workflows` — Gerenciar workflows
- ✅ Outras permissões administrativas

## Matriz de Acesso Completa

| Operação | anonymous | authenticated | ai_editor | editorial_reviewer | administrator |
|----------|-----------|---------------|-----------|-------------------|---------------|
| **VISUALIZAÇÃO** |
| Ver artigo publicado | ✅ | ✅ | ✅ | ✅ | ✅ |
| Ver rascunho próprio | ❌ | ❌ | ✅ (só próprio) | ✅ (todos) | ✅ |
| Ver rascunho de outro editor | ❌ | ❌ | ❌ | ✅ | ✅ |
| Ver artigo em needs_review | ❌ | ❌ | ✅ (só próprio) | ✅ (todos) | ✅ |
| Ver revisões pendentes | ❌ | ❌ | ❌ | ✅ | ✅ |
| **CRIAÇÃO** |
| Criar artigo | ❌ | ❌ | ✅ | ❌ | ✅ |
| **EDIÇÃO** |
| Editar rascunho próprio | ❌ | ❌ | ✅ | ✅ | ✅ |
| Editar rascunho de outro | ❌ | ❌ | ❌ | ✅ | ✅ |
| Editar artigo publicado | ❌ | ❌ | ❌ | ✅ (cria revisão) | ✅ |
| **DELEÇÃO** |
| Deletar artigo próprio | ❌ | ❌ | ✅ | ❌ | ✅ |
| Deletar artigo de outro | ❌ | ❌ | ❌ | ❌ | ✅ |
| **TRANSIÇÕES** |
| Criar rascunho (create_new_draft) | ❌ | ❌ | ✅ | ❌ | ✅ |
| Enviar para revisão (submit_for_review) | ❌ | ❌ | ✅ | ❌ | ✅ |
| Aprovar e publicar (approve_and_publish) | ❌ | ❌ | ❌ | ✅ | ✅ |
| Devolver para edição (return_to_draft) | ❌ | ❌ | ❌ | ✅ | ✅ |
| Despublicar (unpublish) | ❌ | ❌ | ❌ | ✅ | ✅ |
| Criar revisão de publicado (create_revision_draft) | ❌ | ❌ | ❌ | ✅ | ✅ |

## Justificativa de Devolução

### Campo: field_return_justification

**Tipo**: Text (long)  
**Obrigatório**: Sim, quando transição é `return_to_draft` de `needs_review` para `draft`  
**Visibilidade**: 
- Oculto por padrão
- Visível para revisor ao devolver artigo
- Visível para editor após devolução (somente leitura)

### Validação

O módulo `cms_ai_editorial` implementa validação server-side:

```php
// Em cms_ai_editorial.module
function cms_ai_editorial_node_form_validate(array &$form, FormStateInterface $form_state) {
  $node = $form_state->getFormObject()->getEntity();
  
  if (!$node->isNew() && isset($node->moderation_state)) {
    $current_state = $node->get('moderation_state')->value;
    $new_state = $form_state->getValue(['moderation_state', 0, 'state']);
    
    // Devolução requer justificativa
    if ($current_state === 'needs_review' && $new_state === 'draft') {
      $justification = $form_state->getValue('field_return_justification');
      
      if (empty($justification[0]['value'])) {
        $form_state->setErrorByName('field_return_justification', 
          t('Justificativa é obrigatória ao devolver o artigo para edição.'));
      }
    }
  }
}
```

### Exemplo de Uso

**Cenário**: Revisor devolve artigo

1. Artigo está em `needs_review`
2. Revisor acessa o artigo
3. Campo "Justificativa de Devolução" é exibido
4. Revisor preenche: "O artigo precisa de mais referências e correção gramatical."
5. Revisor seleciona transição "Return to Draft"
6. Sistema valida que justificativa foi fornecida
7. Artigo volta para `draft`
8. Editor vê a justificativa quando acessar o artigo

## Revisão Pendente sobre Publicado

### Comportamento

Quando um revisor cria uma revisão de um artigo já publicado:

1. **Versão Publicada (V1)** permanece acessível ao público
2. **Nova Revisão (V2)** é criada em estado `draft`
3. V2 é invisível para visitantes e editores (visível apenas para revisores)
4. V2 pode passar pelo workflow normal: draft → needs_review → published
5. Ao publicar V2, ela substitui V1 como versão pública

### Diagrama

```
Estado Inicial:
┌─────────────────┐
│ Artigo V1       │
│ Estado: published│ ← Visível ao público
│ ID: 123, Rev: 1 │
└─────────────────┘

Revisor cria revisão:
┌─────────────────┐     ┌─────────────────┐
│ Artigo V1       │     │ Artigo V2       │
│ Estado: published│     │ Estado: draft   │ ← Invisível ao público
│ ID: 123, Rev: 1 │     │ ID: 123, Rev: 2 │
└─────────────────┘     └─────────────────┘
     Visível                 Visível apenas
   ao público              para revisores

Após aprovação:
┌─────────────────┐
│ Artigo V2       │
│ Estado: published│ ← Agora visível ao público
│ ID: 123, Rev: 2 │
└─────────────────┘
(V1 permanece no histórico)
```

### Implementação

O módulo Content Moderation do Drupal 11 implementa isso nativamente:

- `latest_revision` — Última revisão criada (pode ser draft)
- `default_revision` — Revisão padrão exibida (a publicada)
- Views filtram automaticamente por `status = 1` (publicado)

### Teste Manual

Ver `docs/us04-testes-manuais.md` para procedimento completo.

## Segurança

### Proteções Implementadas

1. **Validação Server-Side**: Todas as transições validadas no servidor
2. **Controle de Acesso**: hook_node_access() restringe acesso baseado em autoria e papel
3. **Justificativa Obrigatória**: Validação em hook_validate garante justificativa ao devolver
4. **Isolamento de Editores**: Editor não vê rascunhos de outros editores
5. **Proteção de Publicação**: Apenas revisores podem publicar
6. **Auditoria**: Drupal registra todas as mudanças de estado e revisões

### Prevenção de Escalação de Privilégios

- Editores não podem se auto-atribuir papel de revisor
- Transições são restritas por permissões específicas
- Workers (futuros usuários API) não terão permissão de publicação
- Administradores não podem ser bloqueados do sistema

## Referências

- [Content Moderation Documentation](https://www.drupal.org/docs/8/core/modules/content-moderation)
- [Workflows API](https://api.drupal.org/api/drupal/core%21modules%21workflows%21workflows.api.php/group/workflows_api/11)
- [Node Access System](https://api.drupal.org/api/drupal/core%21modules%21node%21node.api.php/group/node_access/11)
