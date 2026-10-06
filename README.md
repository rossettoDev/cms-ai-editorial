# CMS AI Editorial

Sistema de gerenciamento de conteúdo Drupal customizado para editorial assistido por IA.

## Stack Técnica

- **Drupal**: 11.4.x
- **PHP**: 8.3+
- **Composer**: 2.10+
- **Drush**: 13.x

## Setup

```bash
# Instalar dependências
composer install

# Instalar Drupal (primeira vez)
cd web
../vendor/bin/drush site:install --account-name=admin --account-pass=admin -y
```

## Documentação

- [AGENTS.md](AGENTS.md) — Contexto para Cloud Agents e automações
- [docs/implementation-plan.md](docs/implementation-plan.md) — Plano de implementação detalhado

## Estrutura

```
/workspace/
├── web/                   # Webroot do Drupal
│   └── modules/custom/    # Módulos customizados (versionados)
├── vendor/                # Dependências (não versionado)
├── config/                # Configuration Management
└── docs/                  # Documentação
```

## Licença

GPL-2.0-or-later (padrão Drupal)