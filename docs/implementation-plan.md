# Plano de Implementação — CMS AI Editorial

## Informações do Projeto

**Repositório**: https://github.com/rossettoDev/cms-ai-editorial  
**Data de Início**: 06 de outubro de 2026  
**Última Atualização**: 06 de outubro de 2026

## Stack Técnica Confirmada

### Versões da Stack (US01)

| Componente | Versão | Status | Compatibilidade |
|------------|--------|--------|-----------------|
| **Drupal Core** | 11.4.x (drupal/core-recommended) | ✅ Instalado | Estável |
| **PHP** | 8.3.35 | ✅ Instalado | Compatível com Drupal 11 |
| **Composer** | 2.10.3 | ✅ Instalado | Compatível |
| **Drush** | 13.x (via Composer) | ⏳ Será instalado | Compatível com Drupal 11 |

### Matriz de Compatibilidade Drupal 11

Conforme [documentação oficial do Drupal 11](https://www.drupal.org/docs/getting-started/system-requirements):

- **PHP**: 8.3.0 ou superior (recomendado 8.3.x)
- **Banco de Dados**:
  - MySQL 8.0+ / MariaDB 10.6+
  - PostgreSQL 16+
  - SQLite 3.45+
- **Servidor Web**: Apache 2.4+ ou Nginx 1.18+
- **Composer**: 2.7.0+
- **Drush**: 13.x (gerenciado via Composer)

### Template Base

**drupal/recommended-project:^11.0**

Estrutura criada:
```
/
├── composer.json          # Gerenciamento de dependências
├── composer.lock          # Lock de versões (versionado)
├── vendor/                # Dependências Composer (não versionado)
├── web/                   # Webroot do Drupal
│   ├── core/              # Core do Drupal
│   ├── modules/
│   │   ├── contrib/       # Módulos contrib
│   │   └── custom/        # Módulos customizados (versionados)
│   │       └── cms_ai_editorial/
│   ├── themes/
│   │   ├── contrib/
│   │   └── custom/
│   └── sites/default/
├── config/                # Configuration Management
├── drush/                 # Configurações Drush
└── docs/                  # Documentação
```

## User Stories Implementadas

### ✅ US01 — Inicializar o projeto e confirmar a stack [P0]

**Status**: Completo  
**Branch**: `rossettoDev/us01-inicializar-82b6`  
**Issue**: #1  
**PR**: [A ser criado]

#### Tarefas Implementadas

- [x] **T01.1** — Inspecionar diretório, Git e AGENTS.md
  - Repositório inspecionado
  - AGENTS.md criado com contexto completo para futuros agentes
  - Arquivos existentes preservados (LICENSE, README.md, .gitignore)

- [x] **T01.2** — Confirmar matriz de compatibilidade e inicializar Composer
  - PHP 8.3.35 instalado e verificado
  - Composer 2.10.3 instalado e configurado
  - Drupal 11.4 inicializado via `drupal/recommended-project`
  - `composer.lock` criado e versionado

- [x] **T01.3** — Configurar .gitignore e estrutura do módulo custom
  - .gitignore mantido com padrões Drupal
  - Diretório `web/modules/custom/cms_ai_editorial/` criado
  - Arquivo `cms_ai_editorial.info.yml` criado
  - Estrutura `src/` criada para código do módulo

- [x] **T01.4** — Criar docs/implementation-plan.md
  - Este documento criado
  - Matriz de compatibilidade documentada
  - Versões confirmadas

#### Decisões Técnicas

1. **Drupal 11.4** escolhido como versão base por ser a última versão estável disponível
2. **PHP 8.3.35** instalado (requisito mínimo do Drupal 11)
3. **Template drupal/recommended-project** usado para separação adequada de código
4. **composer.lock** versionado para garantir builds reproduzíveis

#### Estrutura de Arquivos Criados

```
/workspace/
├── AGENTS.md                                          [NOVO]
├── composer.json                                      [NOVO]
├── composer.lock                                      [NOVO]
├── .editorconfig                                      [NOVO]
├── .gitattributes                                     [NOVO]
├── .gitignore                                         [PRESERVADO]
├── LICENSE                                            [PRESERVADO]
├── README.md                                          [PRESERVADO]
├── vendor/                                            [NOVO, não versionado]
├── web/                                               [NOVO]
│   ├── core/                                          [não versionado]
│   ├── modules/
│   │   └── custom/
│   │       └── cms_ai_editorial/                      [NOVO, versionado]
│   │           ├── cms_ai_editorial.info.yml          [NOVO]
│   │           └── src/                               [NOVO]
│   ├── themes/
│   │   └── custom/                                    [futuro]
│   └── sites/default/
├── config/                                            [NOVO]
├── drush/                                             [NOVO]
└── docs/
    └── implementation-plan.md                         [NOVO]
```

## Próximas User Stories

### US02 — [A ser definida]

Status: Pendente  
Branch: [A ser criada]

*Detalhes serão adicionados quando a próxima user story for planejada.*

## Comandos de Setup para Novos Agentes

### Instalar Dependências

```bash
cd /workspace
composer install
```

### Verificar Status

```bash
# Versões instaladas
php --version
composer --version

# Status do Drupal
cd /workspace/web
../vendor/bin/drush status
```

### Instalar Drupal (Primeira Vez)

```bash
cd /workspace/web
../vendor/bin/drush site:install \
  --account-name=admin \
  --account-pass=admin \
  --db-url=sqlite://sites/default/files/.ht.sqlite \
  -y
```

*Nota: Será necessário configurar banco de dados (MySQL/PostgreSQL) para produção.*

## Convenções do Projeto

### Branches

- **main**: Branch principal, sempre estável
- **rossettoDev/[nome-descritivo]-[hash]**: Branches de desenvolvimento
  - Exemplo: `rossettoDev/us01-inicializar-82b6`

### Commits

- Mensagens em português brasileiro
- Formato: `[US##] Descrição clara da mudança`
- Exemplo: `[US01] Adicionar estrutura base do Drupal 11`

### Pull Requests

- Título referencia a US: `US## — Título da User Story`
- Descrição inclui `Fixes #N` para fechar issue automaticamente
- Revisão obrigatória antes de merge

## Configuração de Ambiente

### Requisitos Mínimos

- **SO**: Linux (Ubuntu 20.04+), macOS, ou Windows com WSL2
- **PHP**: 8.3+
- **Composer**: 2.7+
- **Banco de Dados**: MySQL 8.0+ / PostgreSQL 16+ / SQLite 3.45+
- **Memória**: Mínimo 2GB RAM (recomendado 4GB+)
- **Espaço**: Mínimo 500MB livre

### Variáveis de Ambiente

*A ser configurado em user stories futuras*

## Testes

### Tipos de Teste

*A ser definido em user stories futuras*

### Execução

```bash
# Placeholder para comandos de teste
# Será adicionado quando testes forem implementados
```

## Deploy

### Ambientes

- **Desenvolvimento**: Local (SQLite)
- **Staging**: [A ser configurado]
- **Produção**: [A ser configurado]

*Detalhes de deploy serão definidos em user stories futuras.*

## Troubleshooting

### Problema: Composer out of memory

**Solução**:
```bash
php -d memory_limit=-1 $(which composer) [comando]
```

### Problema: Permissões de arquivo no Drupal

**Solução**:
```bash
chmod -R 775 web/sites/default/files
```

### Problema: Módulos não encontrados após git pull

**Solução**:
```bash
composer install
cd web && ../vendor/bin/drush cache:rebuild
```

## Referências

- [Drupal 11 Release Notes](https://www.drupal.org/about/11)
- [Drupal System Requirements](https://www.drupal.org/docs/getting-started/system-requirements)
- [Composer Documentation](https://getcomposer.org/doc/)
- [Drush Documentation](https://www.drush.org/)

## Changelog

### 2026-10-06 — US01 Implementado

- Projeto inicializado com Drupal 11.4
- PHP 8.3.35 e Composer 2.10.3 instalados
- Estrutura base criada
- Módulo custom `cms_ai_editorial` scaffolded
- Documentação inicial criada
