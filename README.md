# CMS AI Editorial

Sistema de gerenciamento de conteúdo Drupal customizado para editorial assistido por IA.

## Stack Técnica

- **Drupal**: 11.4.x
- **PHP**: 8.3+
- **Composer**: 2.10+
- **Drush**: 13.x
- **Ambiente Local**: Lando 3.21+ com Docker

## Início Rápido

### Pré-requisitos

- [Docker Desktop](https://www.docker.com/products/docker-desktop) (v20.10+)
- [Lando](https://lando.dev/) (v3.21+)
- [Git](https://git-scm.com/)

### Instalação

```bash
# 1. Clone o repositório
git clone https://github.com/rossettoDev/cms-ai-editorial.git
cd cms-ai-editorial

# 2. Configure variáveis de ambiente
cp .env.example .env
# Edite .env conforme necessário

# 3. Inicie o ambiente Lando
lando start

# 4. Instale o Drupal
lando drush site:install standard \
  --account-name=admin \
  --account-pass=admin \
  --site-name="CMS AI Editorial" \
  -y

# 5. Acesse o site
# URL: https://cms-ai-editorial.lndo.site
# Usuário: admin / Senha: admin
```

Para instruções detalhadas, consulte [docs/INSTALLATION.md](docs/INSTALLATION.md).

## Comandos Úteis

```bash
# Iniciar ambiente
lando start

# Parar ambiente
lando stop

# Acessar Drush
lando drush [comando]

# Acessar Composer
lando composer [comando]

# Exportar configurações
lando drush config:export -y

# Importar configurações
lando drush config:import -y

# Limpar cache
lando drush cache:rebuild
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