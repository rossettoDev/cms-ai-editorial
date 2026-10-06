# AGENTS.md — Contexto para Cloud Agents e Automações

## Visão Geral do Projeto

**cms-ai-editorial** é um CMS Drupal customizado para gerenciamento editorial assistido por IA.

## Stack Técnica

- **CMS**: Drupal 11.x (drupal/recommended-project)
- **PHP**: 8.3+ (requisito mínimo do Drupal 11)
- **Gerenciamento de dependências**: Composer 2.x
- **CLI**: Drush 13.x (compatível com Drupal 11)
- **Versionamento**: Git (GitHub)

## Estrutura do Projeto

```
/
├── composer.json          # Dependências do projeto
├── composer.lock          # Lock de versões (versionado)
├── web/                   # Webroot do Drupal
│   ├── core/              # Core do Drupal (não versionado)
│   ├── modules/
│   │   ├── contrib/       # Módulos contrib (não versionados)
│   │   └── custom/        # Módulos customizados (versionados)
│   │       └── cms_ai_editorial/  # Módulo principal do projeto
│   ├── themes/
│   │   ├── contrib/       # Temas contrib (não versionados)
│   │   └── custom/        # Temas customizados (versionados)
│   └── sites/
│       └── default/
│           └── settings.php
├── vendor/                # Dependências Composer (não versionado)
├── drush/                 # Configurações do Drush
├── config/                # Exportação de configurações Drupal
└── docs/                  # Documentação do projeto
    └── implementation-plan.md
```

## Padrões de Desenvolvimento

### Código Customizado

- Todo código customizado deve estar em `web/modules/custom/` ou `web/themes/custom/`
- Nunca modificar `web/core/` ou módulos/temas contrib diretamente
- Usar patches quando necessário modificar contrib via `composer.json`

### Dependências

- Sempre usar Composer para gerenciar dependências
- Commitar `composer.lock` para garantir builds reproduzíveis
- Atualizar dependências via `composer update` (nunca manualmente)

### Configuração

- Usar Configuration Management do Drupal (`config/sync/`)
- Exportar configurações com `drush config:export`
- Importar com `drush config:import`

### Git e Branches

- **main**: branch principal, sempre estável
- **rossettoDev/[nome-descritivo]-[hash]**: branches de feature/desenvolvimento
- Formato de commit: mensagens claras em português descrevendo a mudança
- Usar PRs com referência a issues (`Fixes #N`)

## Comandos Úteis

### Setup Inicial

```bash
# Instalar dependências
composer install

# Instalar Drupal (primeira vez)
drush site:install --account-name=admin --account-pass=admin

# Limpar cache
drush cache:rebuild
```

### Desenvolvimento

```bash
# Adicionar módulo contrib
composer require drupal/[module_name]

# Atualizar dependências
composer update

# Exportar configurações
drush config:export

# Importar configurações
drush config:import

# Verificar status do sistema
drush status
```

## Decisões Arquiteturais

### DA-001: Drupal 11 como Base

**Contexto**: Necessidade de um CMS moderno e extensível para funcionalidades de IA editorial.

**Decisão**: Utilizar Drupal 11 (última versão estável) como base do projeto.

**Consequências**:
- Requisito de PHP 8.3+
- Uso obrigatório de Composer para gerenciamento de dependências
- Suporte completo a APIs modernas do Drupal
- Necessidade de Drush 13.x

### DA-002: Estrutura drupal/recommended-project

**Contexto**: Necessidade de separação clara entre código core, contrib e custom.

**Decisão**: Usar template `drupal/recommended-project` como base.

**Consequências**:
- Webroot em `web/` (não na raiz)
- Vendor isolado na raiz do projeto
- .gitignore pré-configurado para não versionar core/contrib
- Estrutura padrão reconhecida pela comunidade Drupal

### DA-003: Módulo Custom cms_ai_editorial

**Contexto**: Funcionalidades específicas de IA editorial precisam de código customizado.

**Decisão**: Criar módulo custom `cms_ai_editorial` em `web/modules/custom/`.

**Consequências**:
- Namespace: `Drupal\cms_ai_editorial`
- Hooks, plugins e serviços específicos do projeto
- Versionado no Git
- Facilita manutenção e evolução

## User Stories e Tarefas

As user stories seguem o formato **US[NN]** e estão documentadas no GitHub Issues.
Tarefas individuais seguem o formato **T[US].[N]**.

### US01 — Inicializar o projeto e confirmar a stack [P0]

Status: ✅ Implementado

- [x] T01.1 Inspecionar diretório, Git e AGENTS.md
- [x] T01.2 Confirmar matriz de compatibilidade e inicializar Composer
- [x] T01.3 Configurar .gitignore e estrutura web/modules/custom/cms_ai_editorial
- [x] T01.4 Criar docs/implementation-plan.md

## Troubleshooting

### Problema: Composer out of memory

**Solução**: Aumentar limite de memória
```bash
php -d memory_limit=-1 $(which composer) [comando]
```

### Problema: Permissões de arquivo

**Solução**: Ajustar permissões do diretório `web/sites/default/files`
```bash
chmod -R 775 web/sites/default/files
chown -R www-data:www-data web/sites/default/files
```

### Problema: Módulos contrib não encontrados

**Solução**: Limpar cache do Composer e reinstalar
```bash
composer clear-cache
composer install
```

## Recursos Externos

- [Drupal.org Documentation](https://www.drupal.org/docs)
- [Composer Documentation](https://getcomposer.org/doc/)
- [Drush Documentation](https://www.drush.org/)
- [Drupal API Reference](https://api.drupal.org/)

## Contato e Suporte

Para questões sobre o projeto, abrir issue no GitHub: https://github.com/rossettoDev/cms-ai-editorial/issues
