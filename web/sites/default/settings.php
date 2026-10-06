<?php

/**
 * @file
 * Drupal site-specific configuration file.
 *
 * CMS AI Editorial - Configuração principal
 */

/**
 * Carregamento de variáveis de ambiente via .env
 *
 * Usar vlucas/phpdotenv para carregar variáveis do arquivo .env
 * na raiz do projeto (um nível acima do webroot).
 */
$dotenv_path = $app_root . '/../';
if (file_exists($dotenv_path . '.env')) {
  if (class_exists('\Dotenv\Dotenv')) {
    $dotenv = \Dotenv\Dotenv::createImmutable($dotenv_path);
    $dotenv->safeLoad();
  }
}

/**
 * Location of the site configuration files.
 *
 * Diretório de configuração do Drupal (Configuration Management)
 */
$settings['config_sync_directory'] = '../config/sync';

/**
 * Salt for one-time login links, cancel links, form tokens, etc.
 *
 * Carregado da variável de ambiente DRUPAL_HASH_SALT.
 * IMPORTANTE: Gere um valor único para produção!
 */
if ($hash_salt = getenv('DRUPAL_HASH_SALT')) {
  $settings['hash_salt'] = $hash_salt;
}

/**
 * Database configuration.
 *
 * Configuração do banco de dados a partir de variáveis de ambiente.
 * Suporta connection string completa (DB_URL) ou parâmetros individuais.
 */
if ($db_url = getenv('DB_URL')) {
  // Connection string completa (ex: mysql://user:pass@host:port/database)
  $databases['default']['default'] = [
    'database' => '',
    'username' => '',
    'password' => '',
    'host' => '',
    'port' => '',
    'driver' => 'mysql',
    'prefix' => '',
    'collation' => 'utf8mb4_general_ci',
  ];
  
  $url = parse_url($db_url);
  $databases['default']['default']['driver'] = $url['scheme'];
  $databases['default']['default']['host'] = $url['host'] ?? 'localhost';
  $databases['default']['default']['port'] = $url['port'] ?? 3306;
  $databases['default']['default']['database'] = ltrim($url['path'] ?? '', '/');
  $databases['default']['default']['username'] = $url['user'] ?? '';
  $databases['default']['default']['password'] = $url['pass'] ?? '';
} else {
  // Parâmetros individuais
  $databases['default']['default'] = [
    'database' => getenv('DB_NAME') ?: 'drupal',
    'username' => getenv('DB_USER') ?: 'drupal',
    'password' => getenv('DB_PASSWORD') ?: 'drupal',
    'host' => getenv('DB_HOST') ?: 'localhost',
    'port' => getenv('DB_PORT') ?: '3306',
    'driver' => getenv('DB_DRIVER') ?: 'mysql',
    'prefix' => '',
    'collation' => 'utf8mb4_general_ci',
  ];
}

/**
 * Private file path configuration.
 *
 * Diretório para arquivos privados (não acessíveis via web).
 * Por padrão, ../private (um nível acima do webroot).
 */
if ($private_path = getenv('PRIVATE_FILES_PATH')) {
  $settings['file_private_path'] = $private_path;
} else {
  $settings['file_private_path'] = '../private';
}

/**
 * Trusted host security setting.
 *
 * Proteção contra HTTP Host header attacks.
 * Configure os padrões de host confiáveis via TRUSTED_HOST_PATTERNS.
 */
if ($trusted_hosts = getenv('TRUSTED_HOST_PATTERNS')) {
  $settings['trusted_host_patterns'] = explode('|', $trusted_hosts);
} else {
  // Padrões padrão para ambiente local Lando
  $settings['trusted_host_patterns'] = [
    '^localhost$',
    '^127\.0\.0\.1$',
    '^.*\.lndo\.site$',
  ];
}

/**
 * Configuração de ambiente.
 *
 * Define configurações específicas baseadas no ambiente (local, dev, staging, prod).
 */
$environment = getenv('ENVIRONMENT') ?: 'local';

// Configurações para ambiente de desenvolvimento
if ($environment === 'local' || $environment === 'development') {
  // Mostrar todos os erros
  $config['system.logging']['error_level'] = 'verbose';
  
  // Desabilitar CSS/JS aggregation para facilitar debug
  $config['system.performance']['css']['preprocess'] = FALSE;
  $config['system.performance']['js']['preprocess'] = FALSE;
  
  // Permitir rebuild de cache via UI
  $settings['rebuild_access'] = TRUE;
  
  // Configuração de desenvolvimento
  $settings['skip_permissions_hardening'] = TRUE;
}

// Configurações para produção
if ($environment === 'production') {
  // Ocultar erros em produção
  $config['system.logging']['error_level'] = 'hide';
  
  // Habilitar CSS/JS aggregation
  $config['system.performance']['css']['preprocess'] = TRUE;
  $config['system.performance']['js']['preprocess'] = TRUE;
  
  // Desabilitar rebuild access
  $settings['rebuild_access'] = FALSE;
  
  // Hardening de permissões
  $settings['skip_permissions_hardening'] = FALSE;
}

/**
 * Load local development override configuration, if available.
 *
 * settings.local.php para sobrescrever configurações em ambiente local.
 * NUNCA commite este arquivo no Git!
 */
if (file_exists($app_root . '/' . $site_path . '/settings.local.php')) {
  include $app_root . '/' . $site_path . '/settings.local.php';
}
