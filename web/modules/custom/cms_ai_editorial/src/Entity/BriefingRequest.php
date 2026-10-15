<?php

namespace Drupal\cms_ai_editorial\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\EntityOwnerTrait;

/**
 * Define a entidade de Solicitação de Briefing.
 *
 * @ContentEntityType(
 *   id = "briefing_request",
 *   label = @Translation("Solicitação de Briefing"),
 *   label_collection = @Translation("Solicitações de Briefing"),
 *   label_singular = @Translation("solicitação de briefing"),
 *   label_plural = @Translation("solicitações de briefing"),
 *   label_count = @PluralTranslation(
 *     singular = "@count solicitação de briefing",
 *     plural = "@count solicitações de briefing",
 *   ),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\cms_ai_editorial\Entity\BriefingRequestListBuilder",
 *     "access" = "Drupal\cms_ai_editorial\Entity\BriefingRequestAccessControlHandler",
 *     "form" = {
 *       "default" = "Drupal\cms_ai_editorial\Form\BriefingRequestForm",
 *       "add" = "Drupal\cms_ai_editorial\Form\BriefingRequestForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "briefing_request",
 *   admin_permission = "administer briefing requests",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *     "label" = "id",
 *   },
 *   links = {
 *     "canonical" = "/briefing-request/{briefing_request}",
 *     "add-form" = "/briefing-request/add",
 *     "collection" = "/admin/content/briefing-requests",
 *   },
 * )
 */
class BriefingRequest extends ContentEntityBase {
  
  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * Estados possíveis da solicitação.
   */
  const STATE_PENDING = 'pending';
  const STATE_PROCESSING = 'processing';
  const STATE_COMPLETED = 'completed';
  const STATE_FAILED = 'failed';

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    // ID do proprietário (autor)
    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Autor'))
      ->setDescription(t('O usuário que criou a solicitação.'))
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback('Drupal\cms_ai_editorial\Entity\BriefingRequest::getDefaultEntityOwner')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'author',
        'weight' => 0,
      ]);

    // Estado da solicitação
    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Estado'))
      ->setDescription(t('O estado atual da solicitação.'))
      ->setRequired(TRUE)
      ->setSetting('allowed_values', [
        self::STATE_PENDING => 'Pendente',
        self::STATE_PROCESSING => 'Processando',
        self::STATE_COMPLETED => 'Concluída',
        self::STATE_FAILED => 'Falhou',
      ])
      ->setDefaultValue(self::STATE_PENDING)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 1,
      ]);

    // ID de correlação
    $fields['correlation_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('ID de Correlação'))
      ->setDescription(t('Identificador único para rastreamento da solicitação.'))
      ->setRequired(TRUE)
      ->setDefaultValueCallback('Drupal\cms_ai_editorial\Entity\BriefingRequest::generateCorrelationId')
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 2,
      ]);

    // Objetivo
    $fields['objective'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Objetivo'))
      ->setDescription(t('Qual o objetivo do artigo?'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 1000)
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 3,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 3,
      ]);

    // Público-alvo
    $fields['audience'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Público-alvo'))
      ->setDescription(t('Para quem é destinado o artigo?'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 4,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 4,
      ]);

    // Tom
    $fields['tone'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Tom'))
      ->setDescription(t('Qual o tom desejado para o artigo?'))
      ->setRequired(TRUE)
      ->setSetting('allowed_values', [
        'formal' => 'Formal',
        'informal' => 'Informal',
        'technical' => 'Técnico',
        'conversational' => 'Conversacional',
        'professional' => 'Profissional',
      ])
      ->setDefaultValue('professional')
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 5,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 5,
      ]);

    // Idioma
    $fields['language'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Idioma'))
      ->setDescription(t('Em qual idioma o artigo deve ser gerado?'))
      ->setRequired(TRUE)
      ->setSetting('allowed_values', [
        'pt-BR' => 'Português (Brasil)',
        'en-US' => 'Inglês (EUA)',
        'es-ES' => 'Espanhol (Espanha)',
      ])
      ->setDefaultValue('pt-BR')
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 6,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 6,
      ]);

    // Instruções adicionais
    $fields['instructions'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Instruções adicionais'))
      ->setDescription(t('Instruções específicas para a geração do artigo.'))
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
        'weight' => 7,
        'settings' => [
          'rows' => 5,
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'text_default',
        'weight' => 7,
      ]);

    // Texto de referência (opcional)
    $fields['reference_text'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Texto de referência'))
      ->setDescription(t('Material de referência opcional para orientar a geração (máximo 5000 caracteres).'))
      ->setRequired(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
        'weight' => 8,
        'settings' => [
          'rows' => 8,
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'text_default',
        'weight' => 8,
      ]);

    // Referência à importação de fonte (opcional)
    $fields['source_import'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Importação de fonte'))
      ->setDescription(t('Referência à fonte importada, se houver.'))
      ->setSetting('target_type', 'source_import')
      ->setRequired(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'weight' => 9,
      ]);

    // Snapshot do briefing (JSON serializado)
    $fields['briefing_snapshot'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Snapshot do Briefing'))
      ->setDescription(t('Snapshot JSON completo do briefing no momento da submissão.'))
      ->setRequired(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 9,
      ]);

    // Timestamp de criação
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Criado em'))
      ->setDescription(t('A data em que a solicitação foi criada.'));

    // Timestamp de modificação
    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Modificado em'))
      ->setDescription(t('A data em que a solicitação foi modificada pela última vez.'));

    return $fields;
  }

  /**
   * Gera um ID de correlação único.
   */
  public static function generateCorrelationId() {
    return 'BR-' . date('YmdHis') . '-' . bin2hex(random_bytes(8));
  }

  /**
   * Retorna o proprietário padrão.
   */
  public static function getDefaultEntityOwner() {
    return [\Drupal::currentUser()->id()];
  }

  /**
   * Retorna o estado da solicitação.
   */
  public function getStatus() {
    return $this->get('status')->value;
  }

  /**
   * Define o estado da solicitação.
   */
  public function setStatus($status) {
    $this->set('status', $status);
    return $this;
  }

  /**
   * Retorna o ID de correlação.
   */
  public function getCorrelationId() {
    return $this->get('correlation_id')->value;
  }

  /**
   * Cria um snapshot do briefing.
   */
  public function createBriefingSnapshot() {
    $snapshot = [
      'correlation_id' => $this->getCorrelationId(),
      'created' => $this->get('created')->value,
      'author_id' => $this->getOwnerId(),
      'objective' => $this->get('objective')->value,
      'audience' => $this->get('audience')->value,
      'tone' => $this->get('tone')->value,
      'language' => $this->get('language')->value,
      'instructions' => $this->get('instructions')->value,
      'reference_text' => $this->get('reference_text')->value,
    ];
    
    $this->set('briefing_snapshot', json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    return $this;
  }
}
