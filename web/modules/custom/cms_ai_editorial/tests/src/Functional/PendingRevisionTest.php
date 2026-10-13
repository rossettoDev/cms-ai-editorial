<?php

namespace Drupal\Tests\cms_ai_editorial\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;

/**
 * Testa revisão pendente sobre artigo publicado.
 *
 * @group cms_ai_editorial
 */
class PendingRevisionTest extends BrowserTestBase {

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
      'use editorial transition create_revision_draft',
      'view all revisions',
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
   * Testa que versão publicada permanece acessível durante revisão.
   */
  public function testPublishedVersionRemainsAccessibleDuringRevision() {
    // Criar e publicar artigo (versão 1)
    $node = Node::create([
      'type' => 'editorial_article',
      'title' => 'Published Article V1',
      'body' => ['value' => 'Published content version 1', 'format' => 'plain_text'],
      'uid' => $this->editor->id(),
      'moderation_state' => 'published',
    ]);
    $node->save();

    $node_id = $node->id();
    $original_title = $node->getTitle();
    $original_body = $node->get('body')->value;

    // Verificar que está publicado
    $this->assertTrue($node->isPublished());
    $this->assertEquals('published', $node->get('moderation_state')->value);

    // Revisor cria nova revisão em draft (versão 2)
    $node->setNewRevision(TRUE);
    $node->set('title', 'Published Article V2 (draft)');
    $node->set('body', ['value' => 'Updated content version 2 (in draft)', 'format' => 'plain_text']);
    $node->set('moderation_state', 'draft');
    $node->setRevisionLogMessage('Creating draft revision of published article');
    $node->save();

    // Recarregar o node
    \Drupal::entityTypeManager()->getStorage('node')->resetCache([$node_id]);
    $node_default = Node::load($node_id);

    // A versão padrão (default) deve ainda ser a versão publicada (V1)
    // porque a revisão em draft (V2) não é publicada
    $this->assertEquals($original_title, $node_default->getTitle(), 
      'Título da versão publicada deve permanecer inalterado');
    $this->assertEquals($original_body, $node_default->get('body')->value,
      'Conteúdo da versão publicada deve permanecer inalterado');
    $this->assertTrue($node_default->isPublished(),
      'Node deve continuar publicado');

    // Anonymous user deve ver a versão publicada original
    $anonymous = User::getAnonymousUser();
    $this->assertTrue($node_default->access('view', $anonymous),
      'Anonymous deve ter acesso à versão publicada');
  }

  /**
   * Testa fluxo completo de revisão de artigo publicado.
   */
  public function testCompletePublishedArticleRevisionWorkflow() {
    // Passo 1: Editor cria e publica artigo (V1)
    $node = Node::create([
      'type' => 'editorial_article',
      'title' => 'Original Published Article',
      'body' => ['value' => 'Original published content', 'format' => 'plain_text'],
      'uid' => $this->editor->id(),
      'moderation_state' => 'published',
    ]);
    $node->save();
    $node_id = $node->id();

    // Verificar versão 1 está publicada
    $this->assertEquals('published', $node->get('moderation_state')->value);
    $this->assertTrue($node->isPublished());

    // Passo 2: Revisor cria revisão em draft (V2)
    $node->setNewRevision(TRUE);
    $node->set('title', 'Revised Article (Draft)');
    $node->set('body', ['value' => 'Revised content in draft', 'format' => 'plain_text']);
    $node->set('moderation_state', 'draft');
    $node->save();

    // Verificar que V1 ainda está acessível
    \Drupal::entityTypeManager()->getStorage('node')->resetCache([$node_id]);
    $node_published = Node::load($node_id);
    $this->assertEquals('Original Published Article', $node_published->getTitle());
    $this->assertTrue($node_published->isPublished());

    // Passo 3: Revisor envia V2 para revisão
    $node_latest = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadRevision($node->getRevisionId());
    $node_latest->set('moderation_state', 'needs_review');
    $node_latest->save();

    // Verificar que V1 ainda está acessível
    \Drupal::entityTypeManager()->getStorage('node')->resetCache([$node_id]);
    $node_published = Node::load($node_id);
    $this->assertEquals('Original Published Article', $node_published->getTitle());
    $this->assertTrue($node_published->isPublished());

    // Passo 4: Revisor aprova e publica V2
    $node_latest = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadRevision($node_latest->getRevisionId());
    $node_latest->set('moderation_state', 'published');
    $node_latest->save();

    // Verificar que agora V2 é a versão publicada
    \Drupal::entityTypeManager()->getStorage('node')->resetCache([$node_id]);
    $node_new_published = Node::load($node_id);
    $this->assertEquals('Revised Article (Draft)', $node_new_published->getTitle());
    $this->assertEquals('Revised content in draft', $node_new_published->get('body')->value);
    $this->assertTrue($node_new_published->isPublished());
  }

  /**
   * Testa que visitante não vê revisão em draft de artigo publicado.
   */
  public function testAnonymousCannotSeeDraftRevision() {
    // Criar artigo publicado
    $node = Node::create([
      'type' => 'editorial_article',
      'title' => 'Published Article',
      'body' => ['value' => 'Published content', 'format' => 'plain_text'],
      'uid' => $this->editor->id(),
      'moderation_state' => 'published',
    ]);
    $node->save();
    $node_id = $node->id();

    // Criar revisão draft
    $node->setNewRevision(TRUE);
    $node->set('title', 'Draft Revision');
    $node->set('body', ['value' => 'Draft content', 'format' => 'plain_text']);
    $node->set('moderation_state', 'draft');
    $node->save();

    // Anonymous deve ver apenas a versão publicada
    $anonymous = User::getAnonymousUser();
    \Drupal::entityTypeManager()->getStorage('node')->resetCache([$node_id]);
    $node_for_anonymous = Node::load($node_id);

    $this->assertTrue($node_for_anonymous->access('view', $anonymous));
    $this->assertEquals('Published Article', $node_for_anonymous->getTitle());
    $this->assertNotEquals('Draft Revision', $node_for_anonymous->getTitle());
  }

}
