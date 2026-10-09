<?php

namespace Drupal\Tests\cms_ai_editorial\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;

/**
 * Testa as transições do workflow editorial.
 *
 * @group cms_ai_editorial
 */
class WorkflowTransitionsTest extends BrowserTestBase {

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
    $this->createRole(['create editorial_article content', 'edit own editorial_article content', 'view own unpublished editorial_article content', 'use editorial transition create_new_draft', 'use editorial transition submit_for_review'], 'ai_editor', 'AI Editor');
    $this->createRole(['view any unpublished editorial_article content', 'edit any editorial_article content', 'use editorial transition approve_and_publish', 'use editorial transition return_to_draft', 'use editorial transition unpublish', 'use editorial transition create_revision_draft'], 'editorial_reviewer', 'Editorial Reviewer');

    // Criar usuários
    $this->editor = $this->drupalCreateUser([], 'editor_user');
    $this->editor->addRole('ai_editor');
    $this->editor->save();

    $this->reviewer = $this->drupalCreateUser([], 'reviewer_user');
    $this->reviewer->addRole('editorial_reviewer');
    $this->reviewer->save();
  }

  /**
   * Testa a transição de draft para needs_review.
   */
  public function testSubmitForReview() {
    $this->drupalLogin($this->editor);

    // Criar um artigo em draft
    $node = Node::create([
      'type' => 'editorial_article',
      'title' => 'Test Article',
      'body' => ['value' => 'Test content', 'format' => 'plain_text'],
      'uid' => $this->editor->id(),
      'moderation_state' => 'draft',
    ]);
    $node->save();

    $this->assertEquals('draft', $node->get('moderation_state')->value);

    // Transição para needs_review
    $node->set('moderation_state', 'needs_review');
    $node->save();

    $this->assertEquals('needs_review', $node->get('moderation_state')->value);
  }

  /**
   * Testa a transição de needs_review para published.
   */
  public function testApproveAndPublish() {
    $this->drupalLogin($this->reviewer);

    // Criar um artigo em needs_review
    $node = Node::create([
      'type' => 'editorial_article',
      'title' => 'Test Article',
      'body' => ['value' => 'Test content', 'format' => 'plain_text'],
      'uid' => $this->editor->id(),
      'moderation_state' => 'needs_review',
    ]);
    $node->save();

    // Transição para published
    $node->set('moderation_state', 'published');
    $node->save();

    $this->assertEquals('published', $node->get('moderation_state')->value);
    $this->assertTrue($node->isPublished());
  }

  /**
   * Testa a transição de needs_review para draft (devolução).
   */
  public function testReturnToDraft() {
    $this->drupalLogin($this->reviewer);

    // Criar um artigo em needs_review
    $node = Node::create([
      'type' => 'editorial_article',
      'title' => 'Test Article',
      'body' => ['value' => 'Test content', 'format' => 'plain_text'],
      'uid' => $this->editor->id(),
      'moderation_state' => 'needs_review',
    ]);
    $node->save();

    // Transição para draft
    $node->set('moderation_state', 'draft');
    $node->set('field_return_justification', 'Necessita revisão adicional');
    $node->save();

    $this->assertEquals('draft', $node->get('moderation_state')->value);
    $this->assertEquals('Necessita revisão adicional', $node->get('field_return_justification')->value);
  }

  /**
   * Testa a transição de published para draft (despublicar).
   */
  public function testUnpublish() {
    $this->drupalLogin($this->reviewer);

    // Criar um artigo publicado
    $node = Node::create([
      'type' => 'editorial_article',
      'title' => 'Test Article',
      'body' => ['value' => 'Test content', 'format' => 'plain_text'],
      'uid' => $this->editor->id(),
      'moderation_state' => 'published',
    ]);
    $node->save();

    // Transição para draft
    $node->set('moderation_state', 'draft');
    $node->save();

    $this->assertEquals('draft', $node->get('moderation_state')->value);
    $this->assertFalse($node->isPublished());
  }

}
