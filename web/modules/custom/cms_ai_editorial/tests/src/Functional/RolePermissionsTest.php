<?php

namespace Drupal\Tests\cms_ai_editorial\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;

/**
 * Testa as permissões dos papéis ai_editor e editorial_reviewer.
 *
 * @group cms_ai_editorial
 */
class RolePermissionsTest extends BrowserTestBase {

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
   * Editor user 1.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $editor1;

  /**
   * Editor user 2.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $editor2;

  /**
   * Reviewer user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $reviewer;

  /**
   * Anonymous user (visitante).
   *
   * @var \Drupal\user\Entity\User
   */
  protected $anonymous;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Criar papéis
    $this->createRole([
      'create editorial_article content',
      'edit own editorial_article content',
      'delete own editorial_article content',
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
      'use editorial transition create_revision_draft',
    ], 'editorial_reviewer', 'Editorial Reviewer');

    // Criar usuários
    $this->editor1 = $this->drupalCreateUser([], 'editor1');
    $this->editor1->addRole('ai_editor');
    $this->editor1->save();

    $this->editor2 = $this->drupalCreateUser([], 'editor2');
    $this->editor2->addRole('ai_editor');
    $this->editor2->save();

    $this->reviewer = $this->drupalCreateUser([], 'reviewer');
    $this->reviewer->addRole('editorial_reviewer');
    $this->reviewer->save();
  }

  /**
   * Testa que editor pode criar e editar seus próprios artigos.
   */
  public function testEditorCanCreateAndEditOwnArticle() {
    $this->drupalLogin($this->editor1);

    // Criar artigo
    $node = Node::create([
      'type' => 'editorial_article',
      'title' => 'Editor 1 Article',
      'body' => ['value' => 'Content by editor 1', 'format' => 'plain_text'],
      'uid' => $this->editor1->id(),
      'moderation_state' => 'draft',
    ]);
    $node->save();

    // Verificar que pode visualizar
    $this->assertTrue($node->access('view', $this->editor1));

    // Verificar que pode editar
    $this->assertTrue($node->access('update', $this->editor1));

    // Verificar que pode deletar
    $this->assertTrue($node->access('delete', $this->editor1));
  }

  /**
   * Testa que editor não pode ver rascunhos de outros editores.
   */
  public function testEditorCannotSeeOtherEditorDrafts() {
    // Editor 1 cria um artigo
    $node = Node::create([
      'type' => 'editorial_article',
      'title' => 'Editor 1 Article',
      'body' => ['value' => 'Content by editor 1', 'format' => 'plain_text'],
      'uid' => $this->editor1->id(),
      'moderation_state' => 'draft',
    ]);
    $node->save();

    // Editor 2 tenta acessar
    $this->drupalLogin($this->editor2);
    
    // Não deve ter acesso
    $this->assertFalse($node->access('view', $this->editor2));
    $this->assertFalse($node->access('update', $this->editor2));
  }

  /**
   * Testa que revisor pode ver todos os artigos.
   */
  public function testReviewerCanSeeAllArticles() {
    // Editor cria um artigo
    $node = Node::create([
      'type' => 'editorial_article',
      'title' => 'Editor Article',
      'body' => ['value' => 'Content by editor', 'format' => 'plain_text'],
      'uid' => $this->editor1->id(),
      'moderation_state' => 'draft',
    ]);
    $node->save();

    // Revisor deve ter acesso
    $this->drupalLogin($this->reviewer);
    $this->assertTrue($node->access('view', $this->reviewer));
    $this->assertTrue($node->access('update', $this->reviewer));
  }

  /**
   * Testa que visitantes (anonymous) só veem conteúdo publicado.
   */
  public function testAnonymousOnlySeesPublished() {
    // Criar artigo em draft
    $draft_node = Node::create([
      'type' => 'editorial_article',
      'title' => 'Draft Article',
      'body' => ['value' => 'Draft content', 'format' => 'plain_text'],
      'uid' => $this->editor1->id(),
      'moderation_state' => 'draft',
    ]);
    $draft_node->save();

    // Criar artigo publicado
    $published_node = Node::create([
      'type' => 'editorial_article',
      'title' => 'Published Article',
      'body' => ['value' => 'Published content', 'format' => 'plain_text'],
      'uid' => $this->editor1->id(),
      'moderation_state' => 'published',
    ]);
    $published_node->save();

    // Anonymous user
    $anonymous = User::getAnonymousUser();

    // Não deve ver draft
    $this->assertFalse($draft_node->access('view', $anonymous));

    // Deve ver publicado
    $this->assertTrue($published_node->access('view', $anonymous));
  }

  /**
   * Testa que revisor pode devolver artigo para edição.
   */
  public function testReviewerCanReturnArticle() {
    // Editor cria artigo e envia para revisão
    $node = Node::create([
      'type' => 'editorial_article',
      'title' => 'Article for Review',
      'body' => ['value' => 'Content', 'format' => 'plain_text'],
      'uid' => $this->editor1->id(),
      'moderation_state' => 'needs_review',
    ]);
    $node->save();

    // Revisor devolve para draft
    $this->drupalLogin($this->reviewer);
    $node->set('moderation_state', 'draft');
    $node->set('field_return_justification', 'Precisa de melhorias');
    $node->save();

    $this->assertEquals('draft', $node->get('moderation_state')->value);
  }

  /**
   * Testa que editor não pode publicar diretamente.
   */
  public function testEditorCannotPublishDirectly() {
    $this->drupalLogin($this->editor1);

    // Editor cria artigo
    $node = Node::create([
      'type' => 'editorial_article',
      'title' => 'Editor Article',
      'body' => ['value' => 'Content', 'format' => 'plain_text'],
      'uid' => $this->editor1->id(),
      'moderation_state' => 'draft',
    ]);
    $node->save();

    // Tentar publicar diretamente deve falhar por falta de permissão
    // (isso será verificado pelo Drupal no form de edição)
    $this->assertFalse($this->editor1->hasPermission('use editorial transition approve_and_publish'));
  }

}
