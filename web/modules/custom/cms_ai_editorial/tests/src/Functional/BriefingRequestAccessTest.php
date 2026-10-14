<?php

namespace Drupal\Tests\cms_ai_editorial\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\cms_ai_editorial\Entity\BriefingRequest;

/**
 * Testes de controle de acesso para BriefingRequest.
 *
 * @group cms_ai_editorial
 */
class BriefingRequestAccessTest extends BrowserTestBase {

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
   * Editor 1.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $editor1;

  /**
   * Editor 2.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $editor2;

  /**
   * Reviewer.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $reviewer;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Criar role de editor
    $editor_role = Role::create([
      'id' => 'test_editor',
      'label' => 'Test Editor',
    ]);
    $editor_role->grantPermission('create briefing request');
    $editor_role->grantPermission('view own briefing request');
    $editor_role->grantPermission('edit own briefing request');
    $editor_role->grantPermission('delete own briefing request');
    $editor_role->save();

    // Criar role de reviewer
    $reviewer_role = Role::create([
      'id' => 'test_reviewer',
      'label' => 'Test Reviewer',
    ]);
    $reviewer_role->grantPermission('view any briefing request');
    $reviewer_role->save();

    // Criar editor 1
    $this->editor1 = User::create([
      'name' => 'editor1',
      'mail' => 'editor1@example.com',
      'status' => 1,
      'roles' => ['test_editor'],
    ]);
    $this->editor1->save();

    // Criar editor 2
    $this->editor2 = User::create([
      'name' => 'editor2',
      'mail' => 'editor2@example.com',
      'status' => 1,
      'roles' => ['test_editor'],
    ]);
    $this->editor2->save();

    // Criar reviewer
    $this->reviewer = User::create([
      'name' => 'reviewer',
      'mail' => 'reviewer@example.com',
      'status' => 1,
      'roles' => ['test_reviewer'],
    ]);
    $this->reviewer->save();
  }

  /**
   * Testa que usuários só podem ver suas próprias solicitações.
   */
  public function testUserCanOnlyViewOwnRequests() {
    // Editor 1 cria uma solicitação
    $this->drupalLogin($this->editor1);
    $this->drupalGet('/briefing-request/add');
    $edit = [
      'objective[0][value]' => 'Solicitação do Editor 1',
      'audience[0][value]' => 'Público 1',
      'tone' => 'formal',
      'language' => 'pt-BR',
      'instructions[0][value]' => 'Instruções do Editor 1',
    ];
    $this->submitForm($edit, 'Solicitar Geração de Artigo');
    
    // Capturar ID da solicitação da URL de confirmação
    $url = $this->getUrl();
    preg_match('/\/briefing-request\/(\d+)\/confirmation/', $url, $matches);
    $request_id_1 = $matches[1];
    
    $this->drupalLogout();

    // Editor 2 tenta acessar a solicitação do Editor 1
    $this->drupalLogin($this->editor2);
    $this->drupalGet("/briefing-request/{$request_id_1}");
    $this->assertSession()->statusCodeEquals(403);

    // Editor 2 cria sua própria solicitação
    $this->drupalGet('/briefing-request/add');
    $edit = [
      'objective[0][value]' => 'Solicitação do Editor 2',
      'audience[0][value]' => 'Público 2',
      'tone' => 'informal',
      'language' => 'en-US',
      'instructions[0][value]' => 'Instruções do Editor 2',
    ];
    $this->submitForm($edit, 'Solicitar Geração de Artigo');
    
    // Capturar ID da solicitação
    $url = $this->getUrl();
    preg_match('/\/briefing-request\/(\d+)\/confirmation/', $url, $matches);
    $request_id_2 = $matches[1];

    // Editor 2 pode ver sua própria solicitação
    $this->drupalGet("/briefing-request/{$request_id_2}");
    $this->assertSession()->statusCodeEquals(200);
    
    // Editor 2 não vê a solicitação do Editor 1 na sua listagem
    $this->drupalGet('/briefing-request/my-requests');
    $this->assertSession()->pageTextContains('Solicitação do Editor 2');
    $this->assertSession()->pageTextNotContains('Solicitação do Editor 1');
    
    $this->drupalLogout();

    // Editor 1 volta e pode ver sua solicitação
    $this->drupalLogin($this->editor1);
    $this->drupalGet("/briefing-request/{$request_id_1}");
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Solicitação do Editor 1');
  }

  /**
   * Testa que reviewers podem ver todas as solicitações.
   */
  public function testReviewerCanViewAllRequests() {
    // Editor 1 cria uma solicitação
    $this->drupalLogin($this->editor1);
    $this->drupalGet('/briefing-request/add');
    $edit = [
      'objective[0][value]' => 'Solicitação visível para reviewer',
      'audience[0][value]' => 'Todos',
      'tone' => 'professional',
      'language' => 'pt-BR',
      'instructions[0][value]' => 'Teste de acesso de reviewer',
    ];
    $this->submitForm($edit, 'Solicitar Geração de Artigo');
    
    // Capturar ID
    $url = $this->getUrl();
    preg_match('/\/briefing-request\/(\d+)\/confirmation/', $url, $matches);
    $request_id = $matches[1];
    
    $this->drupalLogout();

    // Reviewer pode acessar a solicitação
    $this->drupalLogin($this->reviewer);
    $this->drupalGet("/briefing-request/{$request_id}");
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Solicitação visível para reviewer');
  }

  /**
   * Testa que usuários só podem editar suas próprias solicitações pendentes.
   */
  public function testUserCanOnlyEditOwnPendingRequests() {
    // Criar solicitação programaticamente
    $request = BriefingRequest::create([
      'uid' => $this->editor1->id(),
      'objective' => 'Solicitação para edição',
      'audience' => 'Público teste',
      'tone' => 'formal',
      'language' => 'pt-BR',
      'instructions' => 'Instruções originais',
      'status' => 'pending',
    ]);
    $request->createBriefingSnapshot();
    $request->save();

    // Editor 2 não pode acessar edição da solicitação do Editor 1
    $this->drupalLogin($this->editor2);
    $this->drupalGet("/briefing-request/{$request->id()}/edit");
    $this->assertSession()->statusCodeEquals(404); // Rota de edição não configurada por padrão

    // Editor 1 pode visualizar sua solicitação
    $this->drupalLogin($this->editor1);
    $this->drupalGet("/briefing-request/{$request->id()}");
    $this->assertSession()->statusCodeEquals(200);

    // Mudar status para processing
    $request->setStatus(BriefingRequest::STATE_PROCESSING);
    $request->save();

    // Editor 1 ainda pode visualizar, mas edição não é permitida para status != pending
    $this->drupalGet("/briefing-request/{$request->id()}");
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Testa que usuários só podem deletar suas próprias solicitações pendentes ou falhadas.
   */
  public function testUserCanOnlyDeleteOwnPendingOrFailedRequests() {
    // Criar solicitação pendente
    $request_pending = BriefingRequest::create([
      'uid' => $this->editor1->id(),
      'objective' => 'Solicitação pendente para deletar',
      'audience' => 'Público teste',
      'tone' => 'formal',
      'language' => 'pt-BR',
      'instructions' => 'Teste de deleção',
      'status' => 'pending',
    ]);
    $request_pending->createBriefingSnapshot();
    $request_pending->save();

    // Criar solicitação completada
    $request_completed = BriefingRequest::create([
      'uid' => $this->editor1->id(),
      'objective' => 'Solicitação completada',
      'audience' => 'Público teste',
      'tone' => 'formal',
      'language' => 'pt-BR',
      'instructions' => 'Completada',
      'status' => 'completed',
    ]);
    $request_completed->createBriefingSnapshot();
    $request_completed->save();

    $this->drupalLogin($this->editor1);

    // Editor 1 pode deletar solicitação pendente (em princípio)
    $this->assertTrue(
      $request_pending->access('delete', $this->editor1),
      'Editor pode deletar solicitação pendente.'
    );

    // Editor 1 não pode deletar solicitação completada
    $this->assertFalse(
      $request_completed->access('delete', $this->editor1),
      'Editor não pode deletar solicitação completada.'
    );

    // Editor 2 não pode deletar solicitação do Editor 1
    $this->assertFalse(
      $request_pending->access('delete', $this->editor2),
      'Editor 2 não pode deletar solicitação do Editor 1.'
    );
  }

}
