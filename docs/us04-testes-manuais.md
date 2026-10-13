# US04 - Guia de Testes Manuais

Este documento descreve os testes manuais para validar as funcionalidades da US04.

## Pré-requisitos

Antes de iniciar os testes, certifique-se de que:

1. O ambiente Lando está rodando: `lando start`
2. As configurações foram importadas: `lando drush config:import -y`
3. O cache foi limpo: `lando drush cache:rebuild`
4. Você tem acesso ao site: https://cms-ai-editorial.lndo.site

## Teste 1: Criar Usuários de Teste

### Objetivo
Criar usuários para cada papel e verificar que os papéis existem.

### Passos

1. Acesse como administrador (admin/admin)
2. Navegue para **Pessoas** (People) em `/admin/people`
3. Clique em **Adicionar usuário** (Add user)

**Criar Editor 1:**
- Nome de usuário: `editor1`
- Email: `editor1@example.com`
- Senha: `editor1pass`
- Papéis: Marque **AI Editor**
- Clique em **Criar nova conta**

**Criar Editor 2:**
- Nome de usuário: `editor2`
- Email: `editor2@example.com`
- Senha: `editor2pass`
- Papéis: Marque **AI Editor**
- Clique em **Criar nova conta**

**Criar Revisor:**
- Nome de usuário: `reviewer`
- Email: `reviewer@example.com`
- Senha: `reviewerpass`
- Papéis: Marque **Editorial Reviewer**
- Clique em **Criar nova conta**

### Resultado Esperado
✅ Três usuários criados com papéis corretos

---

## Teste 2: Fluxo Completo de Workflow (Draft → Review → Published)

### Objetivo
Testar o fluxo completo de criação, revisão e publicação de um artigo.

### Passos

**Como Editor (editor1):**

1. Faça logout e login como `editor1` / `editor1pass`
2. Navegue para **Conteúdo** → **Adicionar conteúdo** → **Artigo Editorial**
3. Preencha:
   - Título: "Meu Primeiro Artigo"
   - Resumo: "Este é um resumo do artigo"
   - Corpo: "Conteúdo completo do artigo com detalhes interessantes."
   - Tags: "tecnologia, ia" (pressione Enter após cada tag)
   - Referências: "https://example.com/fonte1"
4. No campo **Estado de Moderação** (Moderation State), selecione **Draft**
5. Clique em **Salvar**
6. **Verifique**: Mensagem de sucesso e artigo está em modo de visualização de rascunho
7. Clique em **Editar** (Edit)
8. No campo **Estado de Moderação**, selecione **Needs Review**
9. Clique em **Salvar**
10. **Verifique**: Artigo agora está "Awaiting review" ou similar

**Como Revisor (reviewer):**

11. Faça logout e login como `reviewer` / `reviewerpass`
12. Navegue para **Conteúdo** (Content)
13. **Verifique**: Você vê o artigo "Meu Primeiro Artigo" na lista
14. Clique no artigo para visualizá-lo
15. Clique em **Editar** (Edit)
16. Revise o conteúdo
17. No campo **Estado de Moderação**, selecione **Published**
18. Clique em **Salvar**
19. **Verifique**: Artigo está publicado

**Como Visitante (anonymous):**

20. Faça logout
21. Navegue para a página inicial ou `/node/[ID]` do artigo
22. **Verifique**: O artigo é visível para visitantes

### Resultado Esperado
✅ Fluxo completo funciona: draft → needs_review → published  
✅ Artigo visível para público após publicação

---

## Teste 3: Devolução com Justificativa

### Objetivo
Testar que revisor pode devolver artigo com justificativa obrigatória.

### Passos

**Como Editor (editor1):**

1. Login como `editor1` / `editor1pass`
2. Crie novo artigo:
   - Título: "Artigo para Devolução"
   - Corpo: "Conteúdo inicial que precisa de melhorias."
   - Estado: **Draft**
3. Salve e depois edite
4. Mude estado para **Needs Review**
5. Salve

**Como Revisor (reviewer):**

6. Login como `reviewer` / `reviewerpass`
7. Navegue para o artigo "Artigo para Devolução"
8. Clique em **Editar**
9. **IMPORTANTE**: Tente mudar estado para **Draft** SEM preencher justificativa
10. Clique em **Salvar**
11. **Verifique**: Sistema exibe erro "Justificativa é obrigatória ao devolver o artigo para edição."
12. No campo **Justificativa de Devolução**, digite: "O artigo precisa de mais referências e correção gramatical."
13. Mantenha estado como **Draft**
14. Clique em **Salvar**
15. **Verifique**: Artigo salvo com sucesso

**Como Editor (editor1):**

16. Login como `editor1` / `editor1pass`
17. Navegue para o artigo devolvido
18. **Verifique**: Você consegue ver a justificativa "O artigo precisa de mais referências e correção gramatical."

### Resultado Esperado
✅ Devolução sem justificativa é bloqueada  
✅ Devolução com justificativa funciona  
✅ Editor vê a justificativa

---

## Teste 4: Isolamento de Editores

### Objetivo
Verificar que editor não vê rascunhos de outros editores.

### Passos

**Como Editor 1 (editor1):**

1. Login como `editor1` / `editor1pass`
2. Crie artigo:
   - Título: "Artigo Privado do Editor 1"
   - Corpo: "Conteúdo confidencial"
   - Estado: **Draft**
3. Salve
4. Faça logout

**Como Editor 2 (editor2):**

5. Login como `editor2` / `editor2pass`
6. Navegue para **Conteúdo** (Content)
7. **Verifique**: Você NÃO vê "Artigo Privado do Editor 1" na lista
8. Tente acessar diretamente `/node/[ID]` do artigo
9. **Verifique**: Acesso negado (403 Forbidden)

**Como Revisor (reviewer):**

10. Login como `reviewer` / `reviewerpass`
11. Navegue para **Conteúdo** (Content)
12. **Verifique**: Você VÊ "Artigo Privado do Editor 1" na lista
13. Clique no artigo
14. **Verifique**: Você pode visualizar o conteúdo

### Resultado Esperado
✅ Editor 2 não vê rascunho do Editor 1  
✅ Revisor vê todos os rascunhos

---

## Teste 5: Revisão Pendente sobre Artigo Publicado

### Objetivo
Verificar que versão publicada permanece acessível enquanto nova revisão está em draft.

### Passos

**Como Revisor (reviewer):**

1. Login como `reviewer` / `reviewerpass`
2. Crie e publique artigo:
   - Título: "Artigo Original Publicado"
   - Corpo: "Versão 1 do conteúdo"
   - Estado: **Published**
3. Salve
4. Anote o ID do node (ex: `/node/5`)

**Como Visitante (anonymous):**

5. Faça logout
6. Acesse `/node/[ID]` do artigo
7. **Verifique**: Você vê "Artigo Original Publicado" e "Versão 1 do conteúdo"

**Como Revisor (reviewer):**

8. Login novamente como `reviewer` / `reviewerpass`
9. Edite o artigo publicado
10. Marque **"Criar nova revisão"** (Create new revision)
11. Altere:
    - Título: "Artigo Original Publicado (Atualizado)"
    - Corpo: "Versão 2 do conteúdo - em revisão"
    - Estado: **Draft**
12. Salve

**Como Visitante (anonymous):**

13. Faça logout
14. Acesse `/node/[ID]` do artigo
15. **Verifique**: Você AINDA vê "Artigo Original Publicado" e "Versão 1 do conteúdo"
16. **Verifique**: A revisão em draft NÃO é visível

**Como Revisor (reviewer):**

17. Login como `reviewer` / `reviewerpass`
18. Edite o artigo
19. Mude estado para **Published**
20. Salve

**Como Visitante (anonymous):**

21. Faça logout
22. Acesse `/node/[ID]` do artigo
23. **Verifique**: AGORA você vê "Artigo Original Publicado (Atualizado)" e "Versão 2 do conteúdo - em revisão"

### Resultado Esperado
✅ Versão publicada (V1) permanece acessível enquanto V2 está em draft  
✅ V2 substitui V1 somente após aprovação

---

## Teste 6: Permissões de Transições

### Objetivo
Verificar que apenas os papéis corretos podem usar transições específicas.

### Passos

**Transição: submit_for_review (Editor pode)**

1. Login como `editor1` / `editor1pass`
2. Crie artigo em Draft
3. Edite e mude para **Needs Review**
4. **Verifique**: Transição permitida

**Transição: approve_and_publish (Editor NÃO pode)**

5. Como `editor1`, tente encontrar opção **Published** no dropdown
6. **Verifique**: Opção NÃO está disponível (apenas Draft e Needs Review)

**Transição: approve_and_publish (Revisor pode)**

7. Login como `reviewer` / `reviewerpass`
8. Acesse artigo em Needs Review
9. Edite e mude para **Published**
10. **Verifique**: Transição permitida

**Transição: unpublish (Revisor pode)**

11. Como `reviewer`, edite artigo publicado
12. Mude estado de Published para **Draft**
13. **Verifique**: Transição permitida

### Resultado Esperado
✅ Editor pode: draft → needs_review  
✅ Editor NÃO pode: needs_review → published  
✅ Revisor pode: needs_review → published  
✅ Revisor pode: published → draft

---

## Teste 7: Visitante Sem Acesso a Rascunhos

### Objetivo
Verificar que visitantes não veem conteúdo não publicado.

### Passos

**Como Editor (editor1):**

1. Login como `editor1` / `editor1pass`
2. Crie artigo:
   - Título: "Artigo Não Publicado"
   - Estado: **Draft**
3. Salve e anote o ID

**Como Visitante (anonymous):**

4. Faça logout
5. Tente acessar `/node/[ID]`
6. **Verifique**: Acesso negado (403)
7. Navegue para `/node` ou página inicial
8. **Verifique**: Artigo não aparece na listagem

### Resultado Esperado
✅ Visitante não acessa artigos em draft  
✅ Visitante não acessa artigos em needs_review  
✅ Visitante vê apenas artigos publicados

---

## Teste 8: Múltiplas Devoluções

### Objetivo
Testar múltiplas rodadas de revisão e devolução.

### Passos

1. Como `editor1`, crie artigo "Artigo Multi-Revisão"
2. Envie para revisão (draft → needs_review)
3. Como `reviewer`, devolva com justificativa "Primeira devolução: adicione introdução"
4. Como `editor1`, edite e reenvie para revisão
5. Como `reviewer`, devolva novamente com justificativa "Segunda devolução: corrija conclusão"
6. Como `editor1`, edite e reenvie
7. Como `reviewer`, aprove e publique

### Resultado Esperado
✅ Múltiplas devoluções funcionam  
✅ Justificativas são armazenadas e visíveis  
✅ Artigo pode ser aprovado após devoluções

---

## Checklist Final

Após executar todos os testes, verifique:

- [ ] Workflow funciona: draft → needs_review → published
- [ ] Devolução sem justificativa é bloqueada
- [ ] Devolução com justificativa funciona
- [ ] Editores não veem rascunhos uns dos outros
- [ ] Revisores veem todos os artigos
- [ ] Visitantes veem apenas conteúdo publicado
- [ ] Revisão pendente não altera versão publicada
- [ ] Transições são restritas por papel
- [ ] Múltiplas devoluções funcionam

---

## Troubleshooting

### Problema: Campo "Estado de Moderação" não aparece

**Solução**: 
- Limpe o cache: `lando drush cache:rebuild`
- Verifique se content_moderation está habilitado: `lando drush pm:list | grep content_moderation`

### Problema: Papéis não aparecem

**Solução**:
- Importe configurações: `lando drush config:import -y`
- Limpe cache: `lando drush cache:rebuild`

### Problema: Justificativa não é validada

**Solução**:
- Verifique se módulo cms_ai_editorial está habilitado: `lando drush pm:list | grep cms_ai_editorial`
- Limpe cache: `lando drush cache:rebuild`

### Problema: Acesso negado inesperado

**Solução**:
- Verifique permissões do papel: `/admin/people/roles`
- Reconstrua permissões de node: `lando drush php:eval "node_access_rebuild();"`
- Limpe cache: `lando drush cache:rebuild`
