<?php

namespace Drupal\cms_ai_editorial\Service;

use Drupal\Core\Http\ClientFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use Psr\Http\Message\ResponseInterface;

/**
 * Serviço para buscar conteúdo de URLs com proteção SSRF.
 *
 * Este serviço implementa as seguintes proteções:
 * - Bloqueio de URLs localhost, redes privadas, link-local e metadados
 * - Validação de DNS antes da conexão
 * - Re-validação após cada redirect
 * - Limites de timeout, redirects e tamanho de resposta
 * - Validação de tipo de conteúdo
 */
class UrlFetchService {

  /**
   * O cliente HTTP.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * O logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Timeout padrão em segundos.
   */
  const DEFAULT_TIMEOUT = 15;

  /**
   * Número máximo de redirects.
   */
  const MAX_REDIRECTS = 5;

  /**
   * Tamanho máximo da resposta em bytes (2MB).
   */
  const MAX_RESPONSE_SIZE = 2097152;

  /**
   * Tipos de conteúdo permitidos.
   */
  const ALLOWED_CONTENT_TYPES = [
    'text/html',
    'application/xhtml+xml',
  ];

  /**
   * Ranges de IP bloqueados (SSRF protection).
   */
  const BLOCKED_IP_RANGES = [
    // Loopback IPv4
    '127.0.0.0/8',
    // Private IPv4
    '10.0.0.0/8',
    '172.16.0.0/12',
    '192.168.0.0/16',
    // Link-local IPv4
    '169.254.0.0/16',
    // AWS metadata
    '169.254.169.254/32',
    // GCP metadata
    '169.254.169.254/32',
    // Azure metadata
    '169.254.169.254/32',
    // Loopback IPv6
    '::1/128',
    // Link-local IPv6
    'fe80::/10',
    // Unique local IPv6
    'fc00::/7',
    // Private IPv6
    'fd00::/8',
  ];

  /**
   * Construtor.
   */
  public function __construct(ClientFactory $http_client_factory, LoggerChannelFactoryInterface $logger_factory) {
    $this->httpClient = $http_client_factory->fromOptions([
      'timeout' => self::DEFAULT_TIMEOUT,
      'allow_redirects' => FALSE, // Controlaremos redirects manualmente
      'http_errors' => FALSE, // Trataremos erros HTTP manualmente
    ]);
    $this->logger = $logger_factory->get('cms_ai_editorial');
  }

  /**
   * Busca o conteúdo de uma URL com proteção SSRF.
   *
   * @param string $url
   *   A URL a ser buscada.
   *
   * @return array
   *   Array com as chaves:
   *   - 'success': bool indicando sucesso
   *   - 'content': string com o conteúdo HTML (se sucesso)
   *   - 'final_url': string com a URL final após redirects (se sucesso)
   *   - 'error': string com a mensagem de erro (se falha)
   *   - 'error_code': string com código de erro (se falha)
   */
  public function fetchUrl($url) {
    // Validar esquema da URL
    if (!$this->isValidScheme($url)) {
      return [
        'success' => FALSE,
        'error' => 'Apenas URLs HTTP e HTTPS públicas são permitidas.',
        'error_code' => 'invalid_scheme',
      ];
    }

    // Parse da URL
    $parsed = parse_url($url);
    if (!$parsed || empty($parsed['host'])) {
      return [
        'success' => FALSE,
        'error' => 'URL inválida.',
        'error_code' => 'invalid_url',
      ];
    }

    // Validar que não é um IP bloqueado diretamente
    if ($this->isBlockedHost($parsed['host'])) {
      $this->logger->warning('Tentativa de acesso a host bloqueado: @host', ['@host' => $parsed['host']]);
      return [
        'success' => FALSE,
        'error' => 'Acesso a este host é bloqueado por políticas de segurança.',
        'error_code' => 'blocked_host',
      ];
    }

    // Resolver DNS e validar IP
    $validation = $this->validateDns($parsed['host']);
    if (!$validation['valid']) {
      return [
        'success' => FALSE,
        'error' => $validation['error'],
        'error_code' => $validation['error_code'],
      ];
    }

    // Seguir redirects manualmente com validação
    $current_url = $url;
    $redirect_count = 0;
    $response = NULL;

    while ($redirect_count <= self::MAX_REDIRECTS) {
      try {
        $response = $this->httpClient->get($current_url, [
          'stream' => TRUE, // Permitir leitura controlada
        ]);

        $status_code = $response->getStatusCode();

        // Se não é redirect, sair do loop
        if ($status_code < 300 || $status_code >= 400) {
          break;
        }

        // É um redirect, validar o destino
        $redirect_count++;
        if ($redirect_count > self::MAX_REDIRECTS) {
          return [
            'success' => FALSE,
            'error' => 'Número máximo de redirects excedido.',
            'error_code' => 'too_many_redirects',
          ];
        }

        $location = $response->getHeaderLine('Location');
        if (empty($location)) {
          return [
            'success' => FALSE,
            'error' => 'Redirect sem header Location.',
            'error_code' => 'invalid_redirect',
          ];
        }

        // Resolver URL relativa
        if (strpos($location, '://') === FALSE) {
          $base = parse_url($current_url);
          $location = $base['scheme'] . '://' . $base['host'] . $location;
        }

        // Validar o destino do redirect
        $redirect_parsed = parse_url($location);
        if (!$redirect_parsed || empty($redirect_parsed['host'])) {
          return [
            'success' => FALSE,
            'error' => 'URL de redirect inválida.',
            'error_code' => 'invalid_redirect_url',
          ];
        }

        if (!$this->isValidScheme($location)) {
          return [
            'success' => FALSE,
            'error' => 'Redirect para esquema não permitido.',
            'error_code' => 'invalid_redirect_scheme',
          ];
        }

        if ($this->isBlockedHost($redirect_parsed['host'])) {
          $this->logger->warning('Tentativa de redirect para host bloqueado: @host', ['@host' => $redirect_parsed['host']]);
          return [
            'success' => FALSE,
            'error' => 'Redirect para host bloqueado.',
            'error_code' => 'blocked_redirect',
          ];
        }

        // Validar DNS do redirect
        $redirect_validation = $this->validateDns($redirect_parsed['host']);
        if (!$redirect_validation['valid']) {
          return [
            'success' => FALSE,
            'error' => 'Redirect para destino inválido: ' . $redirect_validation['error'],
            'error_code' => $redirect_validation['error_code'],
          ];
        }

        $current_url = $location;
      }
      catch (ConnectException $e) {
        $this->logger->error('Erro de conexão ao buscar URL: @message', ['@message' => $e->getMessage()]);
        return [
          'success' => FALSE,
          'error' => 'Não foi possível conectar ao servidor.',
          'error_code' => 'connection_error',
        ];
      }
      catch (RequestException $e) {
        $this->logger->error('Erro de requisição ao buscar URL: @message', ['@message' => $e->getMessage()]);
        return [
          'success' => FALSE,
          'error' => 'Erro ao fazer requisição HTTP.',
          'error_code' => 'request_error',
        ];
      }
      catch (\Exception $e) {
        $this->logger->error('Erro inesperado ao buscar URL: @message', ['@message' => $e->getMessage()]);
        return [
          'success' => FALSE,
          'error' => 'Erro inesperado ao processar requisição.',
          'error_code' => 'unexpected_error',
        ];
      }
    }

    if (!$response) {
      return [
        'success' => FALSE,
        'error' => 'Falha ao obter resposta.',
        'error_code' => 'no_response',
      ];
    }

    // Verificar código de status
    $status_code = $response->getStatusCode();
    if ($status_code < 200 || $status_code >= 300) {
      return [
        'success' => FALSE,
        'error' => "Código de status HTTP inválido: {$status_code}.",
        'error_code' => 'invalid_status_code',
      ];
    }

    // Validar tipo de conteúdo
    $content_type = $response->getHeaderLine('Content-Type');
    if (!$this->isAllowedContentType($content_type)) {
      return [
        'success' => FALSE,
        'error' => 'Tipo de conteúdo não suportado. Apenas HTML é permitido.',
        'error_code' => 'invalid_content_type',
      ];
    }

    // Ler conteúdo com limite de tamanho
    try {
      $body = $response->getBody();
      $content = '';
      $bytes_read = 0;

      while (!$body->eof() && $bytes_read < self::MAX_RESPONSE_SIZE) {
        $chunk = $body->read(8192);
        $content .= $chunk;
        $bytes_read += strlen($chunk);
      }

      if ($bytes_read >= self::MAX_RESPONSE_SIZE) {
        $this->logger->warning('Resposta excedeu tamanho máximo: @url', ['@url' => $current_url]);
        return [
          'success' => FALSE,
          'error' => 'Conteúdo da página excede o tamanho máximo permitido (2MB).',
          'error_code' => 'content_too_large',
        ];
      }

      return [
        'success' => TRUE,
        'content' => $content,
        'final_url' => $current_url,
      ];
    }
    catch (\Exception $e) {
      $this->logger->error('Erro ao ler conteúdo da resposta: @message', ['@message' => $e->getMessage()]);
      return [
        'success' => FALSE,
        'error' => 'Erro ao ler conteúdo da página.',
        'error_code' => 'content_read_error',
      ];
    }
  }

  /**
   * Valida se o esquema da URL é permitido.
   */
  protected function isValidScheme($url) {
    $scheme = parse_url($url, PHP_URL_SCHEME);
    return in_array(strtolower($scheme), ['http', 'https'], TRUE);
  }

  /**
   * Valida se o tipo de conteúdo é permitido.
   */
  protected function isAllowedContentType($content_type) {
    if (empty($content_type)) {
      return FALSE;
    }

    // Extrair tipo base (ignorar charset e outros parâmetros)
    $parts = explode(';', $content_type);
    $base_type = trim($parts[0]);

    foreach (self::ALLOWED_CONTENT_TYPES as $allowed) {
      if (stripos($base_type, $allowed) === 0) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Verifica se um host está bloqueado diretamente.
   */
  protected function isBlockedHost($host) {
    // Normalizar
    $host = strtolower($host);

    // Verificar hosts conhecidos
    $blocked_hosts = [
      'localhost',
      'localhost.localdomain',
      '0.0.0.0',
      '0000',
    ];

    if (in_array($host, $blocked_hosts, TRUE)) {
      return TRUE;
    }

    // Se for um IP, validar ranges
    if (filter_var($host, FILTER_VALIDATE_IP)) {
      return $this->isBlockedIp($host);
    }

    return FALSE;
  }

  /**
   * Valida DNS e verifica se o IP resolvido é bloqueado.
   */
  protected function validateDns($host) {
    // Resolver DNS
    $records = @dns_get_record($host, DNS_A + DNS_AAAA);

    if ($records === FALSE || empty($records)) {
      return [
        'valid' => FALSE,
        'error' => 'Não foi possível resolver o DNS do host.',
        'error_code' => 'dns_resolution_failed',
      ];
    }

    // Validar cada IP resolvido
    foreach ($records as $record) {
      $ip = NULL;
      if (isset($record['ip'])) {
        $ip = $record['ip']; // IPv4
      }
      elseif (isset($record['ipv6'])) {
        $ip = $record['ipv6']; // IPv6
      }

      if ($ip && $this->isBlockedIp($ip)) {
        $this->logger->warning('Host resolve para IP bloqueado: @host -> @ip', [
          '@host' => $host,
          '@ip' => $ip,
        ]);
        return [
          'valid' => FALSE,
          'error' => 'Host resolve para endereço IP bloqueado.',
          'error_code' => 'blocked_ip',
        ];
      }
    }

    return ['valid' => TRUE];
  }

  /**
   * Verifica se um IP está em um range bloqueado.
   */
  protected function isBlockedIp($ip) {
    foreach (self::BLOCKED_IP_RANGES as $range) {
      if ($this->ipInRange($ip, $range)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Verifica se um IP está dentro de um CIDR range.
   */
  protected function ipInRange($ip, $cidr) {
    if (strpos($cidr, '/') === FALSE) {
      return $ip === $cidr;
    }

    list($subnet, $mask) = explode('/', $cidr);

    // Detectar IPv4 vs IPv6
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
      if (!filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return FALSE;
      }
      return $this->ipv4InRange($ip, $subnet, (int) $mask);
    }
    elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
      if (!filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        return FALSE;
      }
      return $this->ipv6InRange($ip, $subnet, (int) $mask);
    }

    return FALSE;
  }

  /**
   * Verifica se um IPv4 está dentro de um range CIDR.
   */
  protected function ipv4InRange($ip, $subnet, $mask) {
    $ip_long = ip2long($ip);
    $subnet_long = ip2long($subnet);
    $mask_long = -1 << (32 - $mask);
    $subnet_long &= $mask_long;
    return ($ip_long & $mask_long) === $subnet_long;
  }

  /**
   * Verifica se um IPv6 está dentro de um range CIDR.
   */
  protected function ipv6InRange($ip, $subnet, $mask) {
    $ip_bin = inet_pton($ip);
    $subnet_bin = inet_pton($subnet);

    if ($ip_bin === FALSE || $subnet_bin === FALSE) {
      return FALSE;
    }

    $bits = $mask;
    for ($i = 0; $i < strlen($ip_bin); $i++) {
      $byte_bits = min(8, $bits);
      $bits -= $byte_bits;

      if ($byte_bits === 8) {
        if ($ip_bin[$i] !== $subnet_bin[$i]) {
          return FALSE;
        }
      }
      elseif ($byte_bits > 0) {
        $mask_byte = (0xFF << (8 - $byte_bits)) & 0xFF;
        if ((ord($ip_bin[$i]) & $mask_byte) !== (ord($subnet_bin[$i]) & $mask_byte)) {
          return FALSE;
        }
        break;
      }
      else {
        break;
      }
    }

    return TRUE;
  }

}
