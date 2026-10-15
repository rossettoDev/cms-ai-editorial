<?php

namespace Drupal\Tests\cms_ai_editorial\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\cms_ai_editorial\Service\UrlFetchService;

/**
 * Testa o serviço UrlFetchService.
 *
 * @group cms_ai_editorial
 */
class UrlFetchServiceTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['cms_ai_editorial'];

  /**
   * O serviço de fetch de URL.
   *
   * @var \Drupal\cms_ai_editorial\Service\UrlFetchService
   */
  protected $urlFetchService;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->urlFetchService = $this->container->get('cms_ai_editorial.url_fetch');
  }

  /**
   * Testa bloqueio de localhost.
   */
  public function testBlocksLocalhost() {
    $result = $this->urlFetchService->fetchUrl('http://localhost/test');
    $this->assertFalse($result['success']);
    $this->assertEquals('blocked_host', $result['error_code']);
  }

  /**
   * Testa bloqueio de IPs privados.
   */
  public function testBlocksPrivateIps() {
    $private_ips = [
      'http://10.0.0.1',
      'http://172.16.0.1',
      'http://192.168.1.1',
      'http://127.0.0.1',
    ];

    foreach ($private_ips as $url) {
      $result = $this->urlFetchService->fetchUrl($url);
      $this->assertFalse($result['success'], "Deveria bloquear: $url");
      $this->assertContains($result['error_code'], ['blocked_host', 'blocked_ip']);
    }
  }

  /**
   * Testa bloqueio de IP de metadados.
   */
  public function testBlocksMetadataIp() {
    $result = $this->urlFetchService->fetchUrl('http://169.254.169.254/latest/meta-data');
    $this->assertFalse($result['success']);
    $this->assertContains($result['error_code'], ['blocked_host', 'blocked_ip']);
  }

  /**
   * Testa bloqueio de esquemas inválidos.
   */
  public function testBlocksInvalidSchemes() {
    $invalid_schemes = [
      'ftp://example.com',
      'file:///etc/passwd',
      'data:text/html,<script>alert(1)</script>',
    ];

    foreach ($invalid_schemes as $url) {
      $result = $this->urlFetchService->fetchUrl($url);
      $this->assertFalse($result['success'], "Deveria bloquear: $url");
      $this->assertEquals('invalid_scheme', $result['error_code']);
    }
  }

  /**
   * Testa validação de URL inválida.
   */
  public function testRejectsInvalidUrls() {
    $invalid_urls = [
      'not a url',
      'http://',
      'http://.',
      '',
    ];

    foreach ($invalid_urls as $url) {
      $result = $this->urlFetchService->fetchUrl($url);
      $this->assertFalse($result['success'], "Deveria rejeitar: $url");
      $this->assertContains($result['error_code'], ['invalid_url', 'invalid_scheme']);
    }
  }

  /**
   * Testa verificação de IP dentro de range CIDR IPv4.
   */
  public function testIpv4InRange() {
    $reflection = new \ReflectionClass($this->urlFetchService);
    $method = $reflection->getMethod('ipv4InRange');
    $method->setAccessible(TRUE);

    // IP dentro do range
    $this->assertTrue($method->invoke($this->urlFetchService, '192.168.1.100', '192.168.1.0', 24));
    
    // IP fora do range
    $this->assertFalse($method->invoke($this->urlFetchService, '192.168.2.100', '192.168.1.0', 24));
    
    // Testes adicionais
    $this->assertTrue($method->invoke($this->urlFetchService, '10.0.0.1', '10.0.0.0', 8));
    $this->assertTrue($method->invoke($this->urlFetchService, '172.16.0.1', '172.16.0.0', 12));
  }

  /**
   * Testa verificação de IP dentro de range CIDR IPv6.
   */
  public function testIpv6InRange() {
    $reflection = new \ReflectionClass($this->urlFetchService);
    $method = $reflection->getMethod('ipv6InRange');
    $method->setAccessible(TRUE);

    // Loopback
    $this->assertTrue($method->invoke($this->urlFetchService, '::1', '::1', 128));
    
    // Link-local
    $this->assertTrue($method->invoke($this->urlFetchService, 'fe80::1', 'fe80::', 10));
    
    // Fora do range
    $this->assertFalse($method->invoke($this->urlFetchService, '2001:db8::1', 'fe80::', 10));
  }

}
