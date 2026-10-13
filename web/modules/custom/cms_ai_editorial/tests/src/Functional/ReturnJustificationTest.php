<?php

namespace Drupal\Tests\cms_ai_editorial\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\node\Entity\Node;

/**
 * Testa a funcionalidade de justificativa de devolução.
 *
 * @group cms_ai_editorial
 */
class ReturnJustificationTest extends BrowserTestBase {

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
    'workflows',
    'content_moderation',
    'cms_ai_editorial',
  ];

  /**
   * Editor user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $editor;

  /**
   * Reviewer user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $reviewer;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Criar papéis
    $this->createRole([
      'create editorial_article content',
      'edit own editorial_article content',
      'view own unpublished editorial_article content',
      'use editorial transition create_new_draft',
      'use editorial transition submit_for_review',
    ], 'ai_editor', 'AI Editor');

    $this->createRole([
      'view any unpublished editorial_article content',
      'edit any editorial_article content',
      'use editorial transition approve_and_publish',
      'use editorial transition return_to_draft',
      'use editorial transition unpublish',
    ], 'editorial_reviewer', 'Editorial Reviewer');

    // Criar usuários
    $this->editor = $this->drupalCreateUser([], 'editor_user');
    $this->editor->addRole('ai_editor');
    $this->editor->save();

    $this->reviewer = $this->drupalCreateUser([], 'reviewer_user');
    $this->reviewer->addRole('editorial_reviewer');
    $this->reviewer->save();
  }

  /**
   * Testa que devolução com justificativa funciona.
   */
  public function testReturnWithJustification() {
    // Editor cria e envia para revisão
    $node = Node::create([
      'type' => 'editorial_article',
      'title' => 'Article for Review',
      'body' => ['value' => 'Test content', 'format' => 'plain_text'],
      'uid' => $this->editor->id(),
      'moderation_state' => 'needs_review',
    ]);
    $node->save();

    // Revisor devolve com justificativa
    $node->set('moderation_state', 'draft');
    $node->set('field_return_justification', 'O artigo precisa de mais referências e correção gramatical.');
    $node->save();

    // Verificar que a transição funcionou
    $this->assertEquals('draft', $node->get('moderation_state')->value);
    
    // Verificar que a justificativa foi salva
    $this->assertEquals(
      'O artigo precisa de mais referências e correção gramatical.',
      $node->get('field_return_justification')->value
    );
  }

  /**
   * Testa que justificativa é armazenada e pode ser lida pelo editor.
   */
  public function testEditorCanReadJustification() {
    // Criar artigo e devolver com justificativa
    $node = Node::create([
      'type' => 'editorial_article',
      'title' => 'Article for Review',
      'body' => ['value' => 'Test content', 'format' => 'plain_text'],
      'uid' => $this->editor->id(),
      'moderation_state' => 'needs_review',
      'field_return_justification' => 'Justificativa inicial',
    ]);
    $node->save();

    // Atualizar justificativa na devolução
    $node->set('moderation_state', 'draft');
    $node->set('field_return_justification', 'Título pouco descritivo, reformule.');
    $node->save();

    // Editor deve poder ler a justificativa
    $this->drupalLogin($this->editor);
    
    // Recarregar node
    $node = Node::load($node->id());
    
    $this->assertEquals('draft', $node->get('moderation_state')->value);
    $this->assertEquals(
      'Título pouco descritivo, reformule.',
      $node->get('field_return_justification')->value
    );
  }

  /**
   * Testa que justificativa não é obrigatória para outras transições.
   */
  public function testJustificationNotRequiredForOtherTransitions() {
    // Criar artigo em needs_review
    $node = Node::create([
      'type' => 'editorial_article',
      'title' => 'Article for Review',
      'body' => ['value' => 'Test content', 'format' => 'plain_text'],
      'uid' => $this->editor->id(),
      'moderation_state' => 'needs_review',
    ]);
    $node->save();

    // Revisor aprova sem justificativa
    $node->set('moderation_state', 'published');
    $node->save();

    // Deve funcionar sem problemas
    $this->assertEquals('published', $node->get('moderation_state')->value);
  }

  /**
   * Testa múltiplas devoluções e atualizações de justificativa.
   */
  public function testMultipleReturns() {
    // Primeira rodada: criar e enviar para revisão
    $node = Node::create([
      'type' => 'editorial_article',
      'title' => 'Article with Multiple Reviews',
      'body' => ['value' => 'Initial content', 'format' => 'plain_text'],
      'uid' => $this->editor->id(),
      'moderation_state' => 'needs_review',
    ]);
    $node->save();

    // Primeira devolução
    $node->set('moderation_state', 'draft');
    $node->set('field_return_justification', 'Primeira devolução: adicione mais conteúdo');
    $node->save();
    
    $this->assertEquals('Primeira devolução: adicione mais conteúdo', 
      $node->get('field_return_justification')->value);

    // Editor edita e reenvia
    $node->set('body', ['value' => 'Updated content', 'format' => 'plain_text']);
    $node->set('moderation_state', 'needs_review');
    $node->save();

    // Segunda devolução
    $node->set('moderation_state', 'draft');
    $node->set('field_return_justification', 'Segunda devolução: revise a conclusão');
    $node->save();
    
    $this->assertEquals('Segunda devolução: revise a conclusão', 
      $node->get('field_return_justification')->value);
  }

}
