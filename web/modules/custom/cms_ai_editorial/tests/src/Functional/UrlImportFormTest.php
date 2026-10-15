<?php

namespace Drupal\Tests\cms_ai_editorial\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\Role;

/**
 * Testa o formulário de importação de URL.
 *
 * @group cms_ai_editorial
 */
class UrlImportFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['cms_ai_editorial', 'node'];

  /**
   * Um usuário com permissão para criar importações.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $authorizedUser;

  /**
   * Um usuário sem permissão.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $unauthorizedUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Instalar schema das entidades
    $this->installEntitySchema('source_import');
    $this->installEntitySchema('briefing_request');

    // Configurar permissões para authenticated users
    $role = Role::load('authenticated');
    $role->grantPermission('create source imports');
    $role->grantPermission('view own source imports');
    $role->grantPermission('delete own source imports');
    $role->save();

    // Criar usuários
    $this->authorizedUser = $this->drupalCreateUser([
      'create source imports',
      'view own source imports',
    ]);

    $this->unauthorizedUser = $this->drupalCreateUser([]);
  }

  /**
   * Testa acesso negado para usuário não autorizado.
   */
  public function testAccessDeniedForUnauthorizedUser() {
    $this->drupalLogin($this->unauthorizedUser);
    $this->drupalGet('/briefing-request/import-url');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Testa acesso permitido para usuário autorizado.
   */
  public function testAccessGrantedForAuthorizedUser() {
    $this->drupalLogin($this->authorizedUser);
    $this->drupalGet('/briefing-request/import-url');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Importar de URL');
  }

  /**
   * Testa validação de URL vazia.
   */
  public function testValidatesEmptyUrl() {
    $this->drupalLogin($this->authorizedUser);
    $this->drupalGet('/briefing-request/import-url');
    
    $this->submitForm([], 'Buscar e Visualizar');
    
    $this->assertSession()->pageTextContains('URL');
  }

  /**
   * Testa bloqueio de URL localhost.
   */
  public function testBlocksLocalhostUrl() {
    $this->drupalLogin($this->authorizedUser);
    $this->drupalGet('/briefing-request/import-url');
    
    $this->submitForm([
      'url' => 'http://localhost/test',
    ], 'Buscar e Visualizar');
    
    $this->assertSession()->pageTextContains('bloqueado');
  }

  /**
   * Testa bloqueio de IP privado.
   */
  public function testBlocksPrivateIpUrl() {
    $this->drupalLogin($this->authorizedUser);
    $this->drupalGet('/briefing-request/import-url');
    
    $this->submitForm([
      'url' => 'http://192.168.1.1/test',
    ], 'Buscar e Visualizar');
    
    $this->assertSession()->pageTextContains('bloqueado');
  }

  /**
   * Testa bloqueio de esquema inválido.
   */
  public function testBlocksInvalidScheme() {
    $this->drupalLogin($this->authorizedUser);
    $this->drupalGet('/briefing-request/import-url');
    
    $this->submitForm([
      'url' => 'ftp://example.com/file.txt',
    ], 'Buscar e Visualizar');
    
    $this->assertSession()->pageTextContains('Erro');
  }

}
