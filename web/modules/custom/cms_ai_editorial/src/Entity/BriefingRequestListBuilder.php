<?php

namespace Drupal\cms_ai_editorial\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * List builder para entidade BriefingRequest.
 */
class BriefingRequestListBuilder extends EntityListBuilder {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs a new BriefingRequestListBuilder object.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, DateFormatterInterface $date_formatter) {
    parent::__construct($entity_type, $storage);
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('ID');
    $header['correlation_id'] = $this->t('ID de Correlação');
    $header['objective'] = $this->t('Objetivo');
    $header['status'] = $this->t('Estado');
    $header['author'] = $this->t('Autor');
    $header['created'] = $this->t('Criado em');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\cms_ai_editorial\Entity\BriefingRequest $entity */
    $row['id'] = $entity->id();
    $row['correlation_id'] = $entity->getCorrelationId();
    
    // Truncar objetivo se muito longo
    $objective = $entity->get('objective')->value;
    $row['objective'] = strlen($objective) > 80 ? substr($objective, 0, 77) . '...' : $objective;
    
    // Mapear status para português
    $status_map = [
      'pending' => 'Pendente',
      'processing' => 'Processando',
      'completed' => 'Concluída',
      'failed' => 'Falhou',
    ];
    $row['status'] = $status_map[$entity->getStatus()] ?? $entity->getStatus();
    
    // Nome do autor
    $owner = $entity->getOwner();
    $row['author'] = $owner ? $owner->getDisplayName() : $this->t('Anônimo');
    
    $row['created'] = $this->dateFormatter->format($entity->get('created')->value, 'short');
    
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);
    
    // Adicionar link para visualizar
    if ($entity->access('view')) {
      $operations['view'] = [
        'title' => $this->t('Visualizar'),
        'weight' => 10,
        'url' => $entity->toUrl('canonical'),
      ];
    }
    
    return $operations;
  }

}
