# AGENTS.md — Contexto para Cloud Agents e Automações

## Visão Geral do Projeto

**cms-ai-editorial** é um CMS Drupal customizado para gerenciamento editorial assistido por IA.

## Stack Técnica

- **CMS**: Drupal 11.x (drupal/recommended-project)
- **PHP**: 8.3+ (requisito mínimo do Drupal 11)
- **Gerenciamento de dependências**: Composer 2.x
- **CLI**: Drush 13.x (compatível com Drupal 11)
- **Versionamento**: Git (GitHub)
- **Ambiente Local**: Lando 3.21+ com Docker (PHP 8.3, Apache 2.4, MariaDB 10.6)
- **Gerenciamento de Variáveis**: vlucas/phpdotenv
- **Módulos Contrib**:
  - Pathauto (^1.15) - Geração automática de alias únicos
  - Metatag (^2.2) - Gerenciamento de meta tags SEO
  - Token (^1.17) - Sistema de substituição de tokens
  - CTools (^4.1) - Ferramentas de construção de interfaces

## Estrutura do Projeto

```
/
├── composer.json          # Dependências do projeto
├── composer.lock          # Lock de versões (versionado)
├── .lando.yml             # Configuração do ambiente Lando
├── .env.example           # Template de variáveis de ambiente (versionado)
├── .env                   # Variáveis de ambiente (NÃO versionado)
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
│       ├── default/
│       │   ├── settings.php              # Configuração principal (versionado)
│       │   ├── settings.local.example.php # Template local (versionado)
│       │   └── settings.local.php        # Configuração local (NÃO versionado)
│       └── development.services.yml      # Serviços de dev (versionado)
├── vendor/                # Dependências Composer (não versionado)
├── drush/                 # Configurações do Drush
├── config/                # Exportação de configurações Drupal
│   └── sync/              # Diretório de sync de configurações (versionado)
├── private/               # Arquivos privados Drupal (não versionado, protegido)
├── backups/               # Backups de banco (não versionado)
└── docs/                  # Documentação do projeto
    ├── implementation-plan.md
    └── INSTALLATION.md
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
- Usar .env para variáveis de ambiente (nunca versionar .env, apenas .env.example)
- settings.local.php para overrides locais (nunca versionar)

### Git e Branches

- **main**: branch principal, sempre estável
- **rossettoDev/[nome-descritivo]-[hash]**: branches de feature/desenvolvimento
- Formato de commit: mensagens claras em português descrevendo a mudança
- Usar PRs com referência a issues (`Fixes #N`)

## Comandos Úteis

### Setup Inicial (Lando)

```bash
# Iniciar ambiente Lando
lando start

# Instalar Drupal (primeira vez)
lando drush site:install standard \
  --account-name=admin \
  --account-pass=admin \
  --site-name="CMS AI Editorial" \
  -y

# Limpar cache
lando drush cache:rebuild
```

### Desenvolvimento (Lando)

```bash
# Adicionar módulo contrib
lando composer require drupal/[module_name]

# Atualizar dependências
lando composer update

# Exportar configurações
lando drush config:export -y

# Importar configurações
lando drush config:import -y

# Verificar status do sistema
lando drush status

# Acessar shell do container
lando ssh

# Acessar MySQL
lando mysql

# Exportar banco de dados
lando db-export

# Importar banco de dados
lando db-import arquivo.sql.gz
```

### Setup Sem Lando (CI/CD, Cloud Agents)

```bash
# Instalar dependências
composer install

# Instalar Drupal com SQLite (para testes)
cd web
../vendor/bin/drush site:install standard \
  --db-url=sqlite://sites/default/files/.ht.sqlite \
  --account-name=admin \
  --account-pass=admin \
  -y

# Limpar cache
../vendor/bin/drush cache:rebuild
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

### US02 — Executar e instalar o ambiente local [P0]

Status: ✅ Implementado

- [x] T02.1 Criar .lando.yml e settings locais
- [x] T02.2 Definir diretório de configuração e mecanismo de variáveis/segredos
- [x] T02.3 Criar procedimento de instalação e exportar configuração ao longo do projeto
- [x] T02.4 Validar reinstalação limpa (validação final após US03, US04, US14)

### US03 — Modelar artigos e metadados editoriais [P0]

Status: ✅ Implementado (PR #26)

- [x] T03.1 Criar tipo, campos, vocabulário e displays
- [x] T03.2 Definir formatos permitidos e limites dos campos
- [x] T03.3 Escolher mecanismo de alias e metadados compatível, documentando dependências
- [x] T03.4 Exportar configuração e verificar edição manual

**Implementação:**
- Tipo de conteúdo `editorial_article` com título, resumo, corpo, tags, meta título, meta descrição e referências
- Revisões habilitadas por padrão
- Pathauto (^1.15) para alias únicos e validados
- Metatag (^2.2) para SEO e Open Graph
- Configuração exportada para `config/sync/`
- Documentação em `docs/us03-decisoes-implementacao.md`

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
