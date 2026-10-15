<?php

namespace Drupal\Tests\cms_ai_editorial\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\cms_ai_editorial\Entity\SourceImport;
use Drupal\user\Entity\User;
use Drupal\user\Entity\Role;

/**
 * Testa o controle de acesso da entidade SourceImport.
 *
 * @group cms_ai_editorial
 */
class SourceImportAccessTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['cms_ai_editorial'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('source_import');
    
    // Criar role com permissões
    $role = Role::create([
      'id' => 'test_role',
      'label' => 'Test Role',
    ]);
    $role->grantPermission('create source imports');
    $role->grantPermission('view own source imports');
    $role->grantPermission('delete own source imports');
    $role->save();
  }

  /**
   * Testa que o proprietário pode visualizar sua própria importação.
   */
  public function testOwnerCanViewOwnImport() {
    $owner = $this->createUser(['view own source imports']);
    
    $import = SourceImport::create([
      'uid' => $owner->id(),
      'source_url' => 'https://example.com/article',
      'final_url' => 'https://example.com/article',
      'extracted_title' => 'Título de Teste',
      'extracted_text' => 'Texto de teste.',
      'content_hash' => hash('sha256', 'content'),
    ]);
    $import->save();

    $access = $import->access('view', $owner);
    $this->assertTrue($access);
  }

  /**
   * Testa que outro usuário não pode visualizar importação de outro proprietário.
   */
  public function testOtherUserCannotViewImport() {
    $owner = $this->createUser(['view own source imports']);
    $other_user = $this->createUser(['view own source imports']);
    
    $import = SourceImport::create([
      'uid' => $owner->id(),
      'source_url' => 'https://example.com/article',
      'final_url' => 'https://example.com/article',
      'extracted_title' => 'Título de Teste',
      'extracted_text' => 'Texto de teste.',
      'content_hash' => hash('sha256', 'content'),
    ]);
    $import->save();

    $access = $import->access('view', $other_user);
    $this->assertFalse($access);
  }

  /**
   * Testa que importações não podem ser editadas.
   */
  public function testImportsCannotBeUpdated() {
    $owner = $this->createUser(['view own source imports']);
    
    $import = SourceImport::create([
      'uid' => $owner->id(),
      'source_url' => 'https://example.com/article',
      'final_url' => 'https://example.com/article',
      'extracted_title' => 'Título de Teste',
      'extracted_text' => 'Texto de teste.',
      'content_hash' => hash('sha256', 'content'),
    ]);
    $import->save();

    $access = $import->access('update', $owner);
    $this->assertFalse($access);
  }

  /**
   * Testa que o proprietário pode deletar sua própria importação.
   */
  public function testOwnerCanDeleteOwnImport() {
    $owner = $this->createUser(['delete own source imports']);
    
    $import = SourceImport::create([
      'uid' => $owner->id(),
      'source_url' => 'https://example.com/article',
      'final_url' => 'https://example.com/article',
      'extracted_title' => 'Título de Teste',
      'extracted_text' => 'Texto de teste.',
      'content_hash' => hash('sha256', 'content'),
    ]);
    $import->save();

    $access = $import->access('delete', $owner);
    $this->assertTrue($access);
  }

  /**
   * Testa que outro usuário não pode deletar importação de outro proprietário.
   */
  public function testOtherUserCannotDeleteImport() {
    $owner = $this->createUser(['delete own source imports']);
    $other_user = $this->createUser(['delete own source imports']);
    
    $import = SourceImport::create([
      'uid' => $owner->id(),
      'source_url' => 'https://example.com/article',
      'final_url' => 'https://example.com/article',
      'extracted_title' => 'Título de Teste',
      'extracted_text' => 'Texto de teste.',
      'content_hash' => hash('sha256', 'content'),
    ]);
    $import->save();

    $access = $import->access('delete', $other_user);
    $this->assertFalse($access);
  }

  /**
   * Testa que administradores têm acesso total.
   */
  public function testAdminHasFullAccess() {
    $admin = $this->createUser(['administer source imports']);
    $owner = $this->createUser([]);
    
    $import = SourceImport::create([
      'uid' => $owner->id(),
      'source_url' => 'https://example.com/article',
      'final_url' => 'https://example.com/article',
      'extracted_title' => 'Título de Teste',
      'extracted_text' => 'Texto de teste.',
      'content_hash' => hash('sha256', 'content'),
    ]);
    $import->save();

    $this->assertTrue($import->access('view', $admin));
    $this->assertTrue($import->access('delete', $admin));
  }

}
