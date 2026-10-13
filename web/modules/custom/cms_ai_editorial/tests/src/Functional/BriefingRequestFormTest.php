<?php

namespace Drupal\Tests\cms_ai_editorial\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Testes funcionais para o formulário de solicitação de briefing.
 *
 * @group cms_ai_editorial
 */
class BriefingRequestFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'field',
    'text',
    'user',
    'system',
    'cms_ai_editorial',
  ];

  /**
   * Usuário com permissão para criar solicitações.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $editorUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Criar role de editor com permissões
    $role = Role::create([
      'id' => 'test_editor',
      'label' => 'Test Editor',
    ]);
    $role->grantPermission('create briefing request');
    $role->grantPermission('view own briefing request');
    $role->grantPermission('edit own briefing request');
    $role->grantPermission('delete own briefing request');
    $role->save();

    // Criar usuário editor
    $this->editorUser = User::create([
      'name' => 'editor_test',
      'mail' => 'editor@example.com',
      'status' => 1,
      'roles' => ['test_editor'],
    ]);
    $this->editorUser->save();
  }

  /**
   * Testa acesso ao formulário de criação.
   */
  public function testFormAccess() {
    // Usuário anônimo não deve ter acesso
    $this->drupalGet('/briefing-request/add');
    $this->assertSession()->statusCodeEquals(403);

    // Usuário autenticado sem permissão não deve ter acesso
    $user_without_permission = $this->drupalCreateUser();
    $this->drupalLogin($user_without_permission);
    $this->drupalGet('/briefing-request/add');
    $this->assertSession()->statusCodeEquals(403);

    // Usuário com permissão deve ter acesso
    $this->drupalLogin($this->editorUser);
    $this->drupalGet('/briefing-request/add');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Nova Solicitação de Briefing');
  }

  /**
   * Testa submissão válida do formulário.
   */
  public function testValidFormSubmission() {
    $this->drupalLogin($this->editorUser);
    $this->drupalGet('/briefing-request/add');

    $edit = [
      'objective[0][value]' => 'Criar artigo sobre Drupal 11',
      'audience[0][value]' => 'Desenvolvedores PHP',
      'tone' => 'technical',
      'language' => 'pt-BR',
      'instructions[0][value]' => 'Focar nas novidades do Drupal 11',
      'reference_text[0][value]' => 'Material de referência sobre Drupal 11',
    ];

    $this->submitForm($edit, 'Solicitar Geração de Artigo');

    // Verificar redirecionamento para página de confirmação
    $this->assertSession()->addressMatches('/\/briefing-request\/\d+\/confirmation/');
    $this->assertSession()->pageTextContains('Solicitação Criada com Sucesso!');
    $this->assertSession()->pageTextContains('ID de Correlação:');
    $this->assertSession()->pageTextContains('BR-');
  }

  /**
   * Testa validação de campos obrigatórios.
   */
  public function testRequiredFieldsValidation() {
    $this->drupalLogin($this->editorUser);
    $this->drupalGet('/briefing-request/add');

    // Tentar submeter sem preencher campos obrigatórios
    $this->submitForm([], 'Solicitar Geração de Artigo');

    // Verificar mensagens de erro
    $this->assertSession()->pageTextContains('é obrigatório');
  }

  /**
   * Testa validação de tamanho máximo dos campos.
   */
  public function testMaxLengthValidation() {
    $this->drupalLogin($this->editorUser);
    $this->drupalGet('/briefing-request/add');

    // Criar texto com mais de 1000 caracteres para objective
    $long_objective = str_repeat('a', 1001);
    
    $edit = [
      'objective[0][value]' => $long_objective,
      'audience[0][value]' => 'Público teste',
      'tone' => 'formal',
      'language' => 'pt-BR',
      'instructions[0][value]' => 'Instruções teste',
    ];

    $this->submitForm($edit, 'Solicitar Geração de Artigo');
    
    // Verificar mensagem de erro de tamanho máximo
    $this->assertSession()->pageTextContains('não pode exceder 1000 caracteres');
  }

  /**
   * Testa validação de texto de referência opcional.
   */
  public function testOptionalReferenceText() {
    $this->drupalLogin($this->editorUser);
    $this->drupalGet('/briefing-request/add');

    // Submeter sem texto de referência (campo opcional)
    $edit = [
      'objective[0][value]' => 'Objetivo sem referência',
      'audience[0][value]' => 'Público geral',
      'tone' => 'conversational',
      'language' => 'pt-BR',
      'instructions[0][value]' => 'Sem material de referência',
    ];

    $this->submitForm($edit, 'Solicitar Geração de Artigo');

    // Deve ser aceito
    $this->assertSession()->addressMatches('/\/briefing-request\/\d+\/confirmation/');
    $this->assertSession()->pageTextContains('Solicitação Criada com Sucesso!');
  }

  /**
   * Testa validação de tamanho máximo do texto de referência.
   */
  public function testReferenceTextMaxLength() {
    $this->drupalLogin($this->editorUser);
    $this->drupalGet('/briefing-request/add');

    // Criar texto com mais de 5000 caracteres
    $long_reference = str_repeat('b', 5001);

    $edit = [
      'objective[0][value]' => 'Objetivo com referência muito longa',
      'audience[0][value]' => 'Público teste',
      'tone' => 'formal',
      'language' => 'pt-BR',
      'instructions[0][value]' => 'Instruções teste',
      'reference_text[0][value]' => $long_reference,
    ];

    $this->submitForm($edit, 'Solicitar Geração de Artigo');

    // Verificar mensagem de erro
    $this->assertSession()->pageTextContains('não pode exceder 5000 caracteres');
  }

  /**
   * Testa proteção CSRF do formulário.
   */
  public function testCsrfProtection() {
    $this->drupalLogin($this->editorUser);
    
    // O Form API do Drupal automaticamente inclui token CSRF
    // Testar que o formulário possui o token
    $this->drupalGet('/briefing-request/add');
    $this->assertSession()->fieldExists('form_token');
    $this->assertSession()->fieldExists('form_build_id');
  }

  /**
   * Testa página de listagem das próprias solicitações.
   */
  public function testUserRequestsList() {
    $this->drupalLogin($this->editorUser);
    
    // Acessar página de listagem
    $this->drupalGet('/briefing-request/my-requests');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Minhas Solicitações de Briefing');

    // Criar uma solicitação
    $this->drupalGet('/briefing-request/add');
    $edit = [
      'objective[0][value]' => 'Artigo de teste para listagem',
      'audience[0][value]' => 'Público de teste',
      'tone' => 'professional',
      'language' => 'pt-BR',
      'instructions[0][value]' => 'Teste de listagem',
    ];
    $this->submitForm($edit, 'Solicitar Geração de Artigo');

    // Verificar que aparece na listagem
    $this->drupalGet('/briefing-request/my-requests');
    $this->assertSession()->pageTextContains('Artigo de teste para listagem');
    $this->assertSession()->linkExists('Visualizar');
  }

  /**
   * Testa fluxo completo: criar, visualizar e acessar confirmação.
   */
  public function testCompleteWorkflow() {
    $this->drupalLogin($this->editorUser);

    // Passo 1: Criar solicitação
    $this->drupalGet('/briefing-request/add');
    $edit = [
      'objective[0][value]' => 'Fluxo completo de teste',
      'audience[0][value]' => 'Testadores',
      'tone' => 'informal',
      'language' => 'pt-BR',
      'instructions[0][value]' => 'Validar todo o fluxo',
      'reference_text[0][value]' => 'Material completo de referência',
    ];
    $this->submitForm($edit, 'Solicitar Geração de Artigo');

    // Passo 2: Verificar página de confirmação
    $this->assertSession()->addressMatches('/\/briefing-request\/\d+\/confirmation/');
    $this->assertSession()->pageTextContains('Solicitação Criada com Sucesso!');
    $this->assertSession()->linkExists('Visualizar Solicitação Completa');
    $this->assertSession()->linkExists('Minhas Solicitações');

    // Passo 3: Clicar em "Visualizar Solicitação Completa"
    $this->clickLink('Visualizar Solicitação Completa');
    $this->assertSession()->addressMatches('/\/briefing-request\/\d+$/');

    // Passo 4: Acessar listagem de solicitações
    $this->drupalGet('/briefing-request/my-requests');
    $this->assertSession()->pageTextContains('Fluxo completo de teste');
  }

}
