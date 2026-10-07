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
    // createUnsafeImmutable also populates getenv(), which this file reads.
    // createImmutable (v5) only fills $_ENV and $_SERVER.
    $dotenv = \Dotenv\Dotenv::createUnsafeImmutable($dotenv_path);
    $dotenv->safeLoad();
  }
}

/**
 * Reads an environment variable from $_ENV, $_SERVER, or getenv().
 */
$cms_ai_env = static function (string $name, ?string $default = NULL): ?string {
  foreach ([$_ENV, $_SERVER] as $source) {
    if (isset($source[$name]) && is_string($source[$name]) && $source[$name] !== '') {
      return $source[$name];
    }
  }
  $value = getenv($name);
  if (is_string($value) && $value !== '') {
    return $value;
  }
  return $default;
};

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
$settings['hash_salt'] = $cms_ai_env('DRUPAL_HASH_SALT', 'cms-ai-editorial-local-dev-salt');

/**
 * Database configuration.
 *
 * Configuração do banco de dados a partir de variáveis de ambiente.
 * Suporta connection string completa (DB_URL) ou parâmetros individuais.
 */
if ($db_url = $cms_ai_env('DB_URL')) {
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
    'database' => $cms_ai_env('DB_NAME', 'drupal'),
    'username' => $cms_ai_env('DB_USER', 'drupal'),
    'password' => $cms_ai_env('DB_PASSWORD', 'drupal'),
    'host' => $cms_ai_env('DB_HOST', 'database'),
    'port' => $cms_ai_env('DB_PORT', '3306'),
    'driver' => $cms_ai_env('DB_DRIVER', 'mysql'),
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
$settings['file_private_path'] = $cms_ai_env('PRIVATE_FILES_PATH', '../private');

/**
 * Trusted host security setting.
 *
 * Proteção contra HTTP Host header attacks.
 * Configure os padrões de host confiáveis via TRUSTED_HOST_PATTERNS.
 */
if ($trusted_hosts = $cms_ai_env('TRUSTED_HOST_PATTERNS')) {
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
$environment = $cms_ai_env('ENVIRONMENT', 'local');

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
