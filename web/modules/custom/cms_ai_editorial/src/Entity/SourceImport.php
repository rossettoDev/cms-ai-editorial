<?php

namespace Drupal\cms_ai_editorial\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\EntityOwnerTrait;

/**
 * Define a entidade de Importação de Fonte.
 *
 * Esta entidade armazena URLs importadas com acesso restrito.
 *
 * @ContentEntityType(
 *   id = "source_import",
 *   label = @Translation("Importação de Fonte"),
 *   label_collection = @Translation("Importações de Fonte"),
 *   label_singular = @Translation("importação de fonte"),
 *   label_plural = @Translation("importações de fonte"),
 *   label_count = @PluralTranslation(
 *     singular = "@count importação de fonte",
 *     plural = "@count importações de fonte",
 *   ),
 *   handlers = {
 *     "access" = "Drupal\cms_ai_editorial\Entity\SourceImportAccessControlHandler",
 *   },
 *   base_table = "source_import",
 *   admin_permission = "administer source imports",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *     "label" = "id",
 *   },
 * )
 */
class SourceImport extends ContentEntityBase {
  
  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    // ID do proprietário (autor)
    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Autor'))
      ->setDescription(t('O usuário que criou a importação.'))
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback('Drupal\cms_ai_editorial\Entity\SourceImport::getDefaultEntityOwner');

    // URL original
    $fields['source_url'] = BaseFieldDefinition::create('uri')
      ->setLabel(t('URL de origem'))
      ->setDescription(t('A URL de onde o conteúdo foi importado.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 2048);

    // URL final (após redirects)
    $fields['final_url'] = BaseFieldDefinition::create('uri')
      ->setLabel(t('URL final'))
      ->setDescription(t('A URL final após redirects.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 2048);

    // Título extraído
    $fields['extracted_title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Título extraído'))
      ->setDescription(t('O título da página extraído.'))
      ->setRequired(FALSE)
      ->setSetting('max_length', 500);

    // Texto extraído
    $fields['extracted_text'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Texto extraído'))
      ->setDescription(t('O texto relevante extraído da página.'))
      ->setRequired(TRUE);

    // Hash do conteúdo
    $fields['content_hash'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Hash do conteúdo'))
      ->setDescription(t('Hash SHA-256 do conteúdo HTML original.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 64);

    // Data de importação
    $fields['import_date'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Data de importação'))
      ->setDescription(t('Data e hora em que a importação foi realizada.'))
      ->setRequired(TRUE)
      ->setDefaultValueCallback('Drupal\cms_ai_editorial\Entity\SourceImport::getRequestTime');

    // Timestamp de criação
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Criado em'))
      ->setDescription(t('A data em que a entidade foi criada.'));

    // Timestamp de modificação
    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Modificado em'))
      ->setDescription(t('A data em que a entidade foi modificada pela última vez.'));

    return $fields;
  }

  /**
   * Retorna o proprietário padrão.
   */
  public static function getDefaultEntityOwner() {
    return [\Drupal::currentUser()->id()];
  }

  /**
   * Retorna o timestamp atual.
   */
  public static function getRequestTime() {
    return [\Drupal::time()->getRequestTime()];
  }

  /**
   * Cria um hash do conteúdo.
   */
  public function createContentHash($content) {
    $this->set('content_hash', hash('sha256', $content));
    return $this;
  }

}
