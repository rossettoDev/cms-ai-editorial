<?php

namespace Drupal\Tests\cms_ai_editorial\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\cms_ai_editorial\Entity\BriefingRequest;
use Drupal\user\Entity\User;

/**
 * Testes Kernel para a entidade BriefingRequest.
 *
 * @group cms_ai_editorial
 */
class BriefingRequestEntityTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'cms_ai_editorial',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('briefing_request');
    $this->installSchema('system', ['sequences']);
  }

  /**
   * Testa criação básica de uma entidade BriefingRequest.
   */
  public function testBriefingRequestCreation() {
    $user = User::create([
      'name' => 'test_user',
      'mail' => 'test@example.com',
    ]);
    $user->save();

    $briefing = BriefingRequest::create([
      'uid' => $user->id(),
      'objective' => 'Criar um artigo sobre tecnologia',
      'audience' => 'Desenvolvedores',
      'tone' => 'technical',
      'language' => 'pt-BR',
      'instructions' => 'Focar em boas práticas de desenvolvimento',
    ]);

    $briefing->save();

    $this->assertNotNull($briefing->id(), 'A entidade foi criada com um ID.');
    $this->assertEquals('pending', $briefing->getStatus(), 'O estado inicial é "pending".');
    $this->assertNotEmpty($briefing->getCorrelationId(), 'O ID de correlação foi gerado.');
    $this->assertStringStartsWith('BR-', $briefing->getCorrelationId(), 'O ID de correlação tem o prefixo correto.');
  }

  /**
   * Testa geração de correlation ID.
   */
  public function testCorrelationIdGeneration() {
    $user = User::create([
      'name' => 'test_user_2',
      'mail' => 'test2@example.com',
    ]);
    $user->save();

    $briefing1 = BriefingRequest::create([
      'uid' => $user->id(),
      'objective' => 'Objetivo 1',
      'audience' => 'Público 1',
      'tone' => 'formal',
      'language' => 'pt-BR',
      'instructions' => 'Instruções 1',
    ]);
    $briefing1->save();

    $briefing2 = BriefingRequest::create([
      'uid' => $user->id(),
      'objective' => 'Objetivo 2',
      'audience' => 'Público 2',
      'tone' => 'informal',
      'language' => 'en-US',
      'instructions' => 'Instruções 2',
    ]);
    $briefing2->save();

    $this->assertNotEquals(
      $briefing1->getCorrelationId(),
      $briefing2->getCorrelationId(),
      'Cada solicitação tem um ID de correlação único.'
    );
  }

  /**
   * Testa criação de snapshot do briefing.
   */
  public function testBriefingSnapshot() {
    $user = User::create([
      'name' => 'test_user_3',
      'mail' => 'test3@example.com',
    ]);
    $user->save();

    $briefing = BriefingRequest::create([
      'uid' => $user->id(),
      'objective' => 'Teste de snapshot',
      'audience' => 'Testadores',
      'tone' => 'conversational',
      'language' => 'pt-BR',
      'instructions' => 'Criar snapshot',
      'reference_text' => 'Texto de referência para teste',
    ]);

    $briefing->createBriefingSnapshot();
    $briefing->save();

    $snapshot_json = $briefing->get('briefing_snapshot')->value;
    $this->assertNotEmpty($snapshot_json, 'O snapshot foi criado.');

    $snapshot = json_decode($snapshot_json, TRUE);
    $this->assertIsArray($snapshot, 'O snapshot é um array válido.');
    $this->assertEquals($briefing->getCorrelationId(), $snapshot['correlation_id']);
    $this->assertEquals('Teste de snapshot', $snapshot['objective']);
    $this->assertEquals('Testadores', $snapshot['audience']);
    $this->assertEquals('conversational', $snapshot['tone']);
    $this->assertEquals('pt-BR', $snapshot['language']);
    $this->assertEquals('Criar snapshot', $snapshot['instructions']);
    $this->assertEquals('Texto de referência para teste', $snapshot['reference_text']);
  }

  /**
   * Testa mudança de estado da solicitação.
   */
  public function testStatusChange() {
    $user = User::create([
      'name' => 'test_user_4',
      'mail' => 'test4@example.com',
    ]);
    $user->save();

    $briefing = BriefingRequest::create([
      'uid' => $user->id(),
      'objective' => 'Teste de mudança de estado',
      'audience' => 'Público geral',
      'tone' => 'professional',
      'language' => 'pt-BR',
      'instructions' => 'Testar estados',
    ]);
    $briefing->save();

    $this->assertEquals('pending', $briefing->getStatus());

    $briefing->setStatus(BriefingRequest::STATE_PROCESSING);
    $briefing->save();
    $this->assertEquals('processing', $briefing->getStatus());

    $briefing->setStatus(BriefingRequest::STATE_COMPLETED);
    $briefing->save();
    $this->assertEquals('completed', $briefing->getStatus());

    $briefing->setStatus(BriefingRequest::STATE_FAILED);
    $briefing->save();
    $this->assertEquals('failed', $briefing->getStatus());
  }

  /**
   * Testa campos obrigatórios.
   */
  public function testRequiredFields() {
    $user = User::create([
      'name' => 'test_user_5',
      'mail' => 'test5@example.com',
    ]);
    $user->save();

    $briefing = BriefingRequest::create([
      'uid' => $user->id(),
      'objective' => 'Objetivo obrigatório',
      'audience' => 'Público obrigatório',
      'tone' => 'formal',
      'language' => 'pt-BR',
      'instructions' => 'Instruções obrigatórias',
    ]);

    $violations = $briefing->validate();
    $this->assertEquals(0, $violations->count(), 'Não há violações quando todos os campos obrigatórios estão preenchidos.');
  }

}
