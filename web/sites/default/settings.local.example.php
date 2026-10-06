<?php

/**
 * @file
 * Local development override configuration.
 *
 * IMPORTANTE: Este arquivo NÃO deve ser versionado no Git!
 * Copie settings.local.example.php para settings.local.php e customize.
 *
 * Use este arquivo para sobrescrever configurações específicas do seu
 * ambiente local de desenvolvimento.
 */

/**
 * Assertions.
 *
 * Habilitar assertions do PHP para debugging.
 */
assert_options(ASSERT_ACTIVE, TRUE);
\Drupal\Component\Assertion\Handle::register();

/**
 * Show all error messages, with backtrace information.
 *
 * Mostrar todos os erros com stack trace completo.
 */
$config['system.logging']['error_level'] = 'verbose';

/**
 * Disable CSS and JS aggregation.
 *
 * Desabilitar agregação para facilitar debugging.
 */
$config['system.performance']['css']['preprocess'] = FALSE;
$config['system.performance']['js']['preprocess'] = FALSE;

/**
 * Disable the render cache.
 *
 * Desabilitar cache de renderização para ver mudanças imediatamente.
 */
$settings['cache']['bins']['render'] = 'cache.backend.null';

/**
 * Disable caching for migrations.
 *
 * Desabilitar cache de descoberta de plugins.
 */
$settings['cache']['bins']['discovery_migration'] = 'cache.backend.memory';

/**
 * Disable Internal Page Cache.
 *
 * Desabilitar page cache interno.
 */
$settings['cache']['bins']['page'] = 'cache.backend.null';

/**
 * Disable Dynamic Page Cache.
 *
 * Desabilitar dynamic page cache.
 */
$settings['cache']['bins']['dynamic_page_cache'] = 'cache.backend.null';

/**
 * Allow test modules and themes to be installed.
 *
 * Permitir instalação de módulos/temas de teste.
 */
$settings['extension_discovery_scan_tests'] = TRUE;

/**
 * Enable access to rebuild.php.
 *
 * Permitir acesso ao rebuild.php para rebuild de cache.
 */
$settings['rebuild_access'] = TRUE;

/**
 * Skip file system permissions hardening.
 *
 * Útil em ambientes de desenvolvimento.
 */
$settings['skip_permissions_hardening'] = TRUE;

/**
 * Configuração de banco de dados local alternativa (se necessário).
 *
 * Descomente e ajuste se precisar sobrescrever a configuração do .env:
 */
// $databases['default']['default'] = [
//   'database' => 'drupal',
//   'username' => 'drupal',
//   'password' => 'drupal',
//   'host' => 'database',
//   'port' => '3306',
//   'driver' => 'mysql',
//   'prefix' => '',
//   'collation' => 'utf8mb4_general_ci',
// ];

/**
 * Trusted host patterns para desenvolvimento local.
 *
 * Descomente se precisar adicionar mais padrões:
 */
// $settings['trusted_host_patterns'] = [
//   '^localhost$',
//   '^127\.0\.0\.1$',
//   '^.*\.lndo\.site$',
//   '^.*\.local$',
// ];

/**
 * Hash salt alternativo para desenvolvimento (se necessário).
 *
 * IMPORTANTE: Use um valor único! Gere com:
 * drush php-eval 'echo \Drupal\Component\Utility\Crypt::randomBytesBase64(55);'
 */
// $settings['hash_salt'] = 'COLE_AQUI_UM_HASH_GERADO';

/**
 * Enable local development services.
 *
 * Carregar services locais para Twig debug, etc.
 */
$settings['container_yamls'][] = DRUPAL_ROOT . '/sites/development.services.yml';
