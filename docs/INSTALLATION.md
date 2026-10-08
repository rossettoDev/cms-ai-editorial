# Guia de Instalação — CMS AI Editorial

Este guia descreve como configurar e instalar o CMS AI Editorial em um ambiente de desenvolvimento local usando Lando.

## Pré-requisitos

Antes de iniciar, certifique-se de ter instalado:

- [Docker Desktop](https://www.docker.com/products/docker-desktop) (v20.10+)
- [Lando](https://lando.dev/) (v3.21+)
- [Git](https://git-scm.com/)

### Verificar instalação

```bash
# Verificar versões instaladas
docker --version
lando version
git --version
```

## 1. Clone do Repositório

```bash
# Clone o repositório
git clone https://github.com/rossettoDev/cms-ai-editorial.git
cd cms-ai-editorial
```

## 2. Configuração de Variáveis de Ambiente

```bash
# Copie o arquivo de exemplo
cp .env.example .env

# Edite o arquivo .env e configure as variáveis necessárias
nano .env  # ou use seu editor preferido
```

### Variáveis do ambiente local

O `.env.example` já aponta para o MariaDB do Lando (`DB_HOST=database`, usuário e senha `drupal`). Para desenvolvimento local, copiar o arquivo é suficiente.

Gere um `DRUPAL_HASH_SALT` próprio antes de usar o site fora da sua máquina:

```bash
lando drush php-eval 'echo \Drupal\Component\Utility\Crypt::randomBytesBase64(55);'
```

## 3. Iniciar Ambiente Lando

```bash
# Iniciar containers do Lando
# Este comando irá:
# - Criar containers Docker (PHP 8.3, Apache 2.4, MariaDB 10.6)
# - Instalar dependências via Composer
# - Configurar rede e portas
lando start
```

**Aguarde**: A primeira inicialização pode levar alguns minutos enquanto o Lando baixa as imagens Docker e instala as dependências.

### URLs de Acesso

Após o `lando start`, você verá as URLs disponíveis:

- **Site**: https://cms-ai-editorial.lndo.site
- **Database**: Use `lando mysql` para acessar o MariaDB

## 4. Instalação do Drupal

### Opção A: Instalação Limpa (Primeira Vez)

Para instalar o Drupal do zero:

```bash
# Instalar Drupal com perfil padrão
lando drush site:install standard \
  --account-name=admin \
  --account-pass=admin \
  --account-mail=admin@example.com \
  --site-name="CMS AI Editorial" \
  --site-mail=admin@example.com \
  -y

# Limpar cache
lando drush cache:rebuild
```

### Opção B: Importar Configuração Existente

Se já existem configurações exportadas no diretório `config/sync/`:

```bash
# Instalar Drupal mínimo
lando drush site:install minimal \
  --account-name=admin \
  --account-pass=admin \
  -y

# Importar configurações
lando drush config:import -y

# Limpar cache
lando drush cache:rebuild
```

## 5. Configuração Local (Opcional)

Para customizar configurações locais sem modificar o `settings.php` principal:

```bash
# Copiar template de configuração local
cp web/sites/default/settings.local.example.php web/sites/default/settings.local.php

# Editar conforme necessário
nano web/sites/default/settings.local.php
```

**Importante**: O arquivo `settings.local.php` é ignorado pelo Git e não será versionado.

## 6. Acessar o Site

Abra seu navegador e acesse:

- **URL**: https://cms-ai-editorial.lndo.site
- **Usuário**: admin
- **Senha**: admin

**Importante**: Troque a senha do admin imediatamente!

## 7. Comandos Úteis do Lando

### Gerenciamento de Containers

```bash
# Parar containers (mantém dados)
lando stop

# Reiniciar containers
lando restart

# Destruir completamente (remove dados)
lando destroy
```

### Composer

```bash
# Instalar dependências
lando composer install

# Adicionar módulo contrib
lando composer require drupal/nome_do_modulo

# Atualizar dependências
lando composer update
```

### Drush

```bash
# Status do sistema
lando drush status

# Limpar cache
lando drush cache:rebuild

# Exportar configurações
lando drush config:export -y

# Importar configurações
lando drush config:import -y

# Atualizar banco de dados
lando drush updatedb -y

# Login one-time link
lando drush user:login
```

### Banco de Dados

```bash
# Acessar MariaDB
lando mysql

# Exportar banco de dados
lando db-export

# Importar banco de dados
lando db-import backup.sql.gz
```

## 8. Desenvolvimento

### Exportar Configurações

Sempre que fizer alterações de configuração via interface do Drupal, exporte-as:

```bash
# Exportar configurações
lando drush config:export -y

# Versionar no Git
git add config/sync/
git commit -m "Exportar configurações: [descrição]"
```

### Workflow de Desenvolvimento

1. **Fazer alterações** no código ou configuração
2. **Testar localmente** via browser
3. **Exportar configurações** se houver mudanças via UI
4. **Commitar** no Git com mensagem descritiva
5. **Criar Pull Request** contra a branch `main`

## 9. Troubleshooting

### Problema: "Connection refused" ao acessar o site

**Causa**: Containers não estão rodando.

**Solução**:
```bash
lando start
```

### Problema: Erros de permissão de arquivos

**Causa**: Permissões incorretas nos diretórios de arquivos.

**Solução**:
```bash
# Dentro do container
lando ssh -c "chmod -R 775 /app/web/sites/default/files"
lando ssh -c "chmod -R 775 /app/private"
```

### Problema: Composer out of memory

**Causa**: Falta memória PHP.

**Solução**: Já configurado no `.lando.yml` (PHP_MEMORY_LIMIT=512M)

Se persistir:
```bash
lando ssh
php -d memory_limit=-1 /usr/local/bin/composer [comando]
```

### Problema: Certificado SSL não confiável

**Causa**: Certificado auto-assinado do Lando.

**Solução**: 
- Aceite a exceção no navegador, ou
- [Configure certificado do Lando](https://docs.lando.dev/core/v3/security.html#trusting-the-ca)

### Problema: Módulo não encontrado após `composer require`

**Causa**: Cache do Drupal desatualizado.

**Solução**:
```bash
lando drush cache:rebuild
```

### Problema: Mudanças de configuração não aparecem

**Causa**: Configuração não foi exportada ou importada.

**Solução**:
```bash
# Se você fez mudanças via UI, exporte:
lando drush config:export -y

# Se alguém commitou configurações, importe:
git pull
lando drush config:import -y
lando drush cache:rebuild
```

## 10. Reinstalação Limpa

Para reinstalar o Drupal do zero (apaga todos os dados!):

```bash
# Parar Lando
lando stop

# Destruir containers e dados
lando destroy -y

# Remover arquivos gerados
rm -rf web/sites/default/files/*
rm -rf private/*
rm -f .env

# Recriar ambiente
cp .env.example .env
# Edite .env conforme necessário

# Reiniciar
lando start

# Reinstalar Drupal (Opção A do passo 4)
lando drush site:install standard \
  --account-name=admin \
  --account-pass=admin \
  --account-mail=admin@example.com \
  --site-name="CMS AI Editorial" \
  --site-mail=admin@example.com \
  -y
```

## Próximos Passos

- Leia [AGENTS.md](../AGENTS.md) para contexto do projeto
- Consulte [implementation-plan.md](implementation-plan.md) para roadmap
- Verifique issues abertas no [GitHub](https://github.com/rossettoDev/cms-ai-editorial/issues)

## Suporte

Para problemas ou dúvidas:

1. Verifique a seção [Troubleshooting](#9-troubleshooting) acima
2. Consulte [documentação do Lando](https://docs.lando.dev/)
3. Consulte [documentação do Drupal](https://www.drupal.org/docs)
4. Abra uma issue no [GitHub](https://github.com/rossettoDev/cms-ai-editorial/issues)
