<?php

namespace Drupal\cms_ai_editorial\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Formulário para criar/editar solicitação de briefing.
 */
class BriefingRequestForm extends ContentEntityForm {

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->messenger = $container->get('messenger');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    /** @var \Drupal\cms_ai_editorial\Entity\BriefingRequest $entity */
    $entity = $this->entity;

    // Adicionar descrição contextual
    $form['#prefix'] = '<div class="briefing-request-form-wrapper">';
    $form['#suffix'] = '</div>';

    $form['description'] = [
      '#type' => 'markup',
      '#markup' => '<div class="description"><p>' . 
        $this->t('Preencha o formulário abaixo para solicitar a geração de um artigo editorial assistido por IA. Todos os campos marcados com asterisco (*) são obrigatórios.') .
        '</p></div>',
      '#weight' => -100,
    ];

    // Configurar validação de tamanho máximo para reference_text
    if (isset($form['reference_text'])) {
      $form['reference_text']['widget'][0]['#maxlength'] = 5000;
      $form['reference_text']['widget'][0]['#description'] = $this->t('Material de referência opcional (máximo 5000 caracteres).');
    }

    // Configurar validação de tamanho máximo para objective
    if (isset($form['objective'])) {
      $form['objective']['widget'][0]['#maxlength'] = 1000;
      $form['objective']['widget'][0]['#description'] = $this->t('Descreva o objetivo do artigo (máximo 1000 caracteres).');
    }

    // Configurar validação de tamanho máximo para instructions
    if (isset($form['instructions'])) {
      $form['instructions']['widget'][0]['#maxlength'] = 2000;
      $form['instructions']['widget'][0]['#description'] = $this->t('Instruções específicas para a geração (máximo 2000 caracteres).');
    }

    // Ocultar campos técnicos do usuário
    if (isset($form['correlation_id'])) {
      $form['correlation_id']['#access'] = FALSE;
    }
    if (isset($form['status'])) {
      $form['status']['#access'] = FALSE;
    }
    if (isset($form['briefing_snapshot'])) {
      $form['briefing_snapshot']['#access'] = FALSE;
    }
    if (isset($form['uid'])) {
      $form['uid']['#access'] = FALSE;
    }
    if (isset($form['created'])) {
      $form['created']['#access'] = FALSE;
    }
    if (isset($form['changed'])) {
      $form['changed']['#access'] = FALSE;
    }

    // Customizar botão de submit
    $form['actions']['submit']['#value'] = $this->t('Solicitar Geração de Artigo');

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    /** @var \Drupal\cms_ai_editorial\Entity\BriefingRequest $entity */
    $entity = $this->entity;

    // Validar tamanho do objetivo
    $objective = $form_state->getValue(['objective', 0, 'value']);
    if (!empty($objective) && mb_strlen($objective) > 1000) {
      $form_state->setErrorByName('objective', 
        $this->t('O campo "Objetivo" não pode exceder 1000 caracteres.'));
    }

    // Validar tamanho do público-alvo
    $audience = $form_state->getValue(['audience', 0, 'value']);
    if (!empty($audience) && mb_strlen($audience) > 255) {
      $form_state->setErrorByName('audience', 
        $this->t('O campo "Público-alvo" não pode exceder 255 caracteres.'));
    }

    // Validar tamanho das instruções
    $instructions_value = $form_state->getValue(['instructions', 0]);
    if (!empty($instructions_value['value']) && mb_strlen($instructions_value['value']) > 2000) {
      $form_state->setErrorByName('instructions', 
        $this->t('O campo "Instruções adicionais" não pode exceder 2000 caracteres.'));
    }

    // Validar tamanho do texto de referência (opcional)
    $reference_value = $form_state->getValue(['reference_text', 0]);
    if (!empty($reference_value['value']) && mb_strlen($reference_value['value']) > 5000) {
      $form_state->setErrorByName('reference_text', 
        $this->t('O campo "Texto de referência" não pode exceder 5000 caracteres.'));
    }

    // Validar campos obrigatórios
    if (empty($objective)) {
      $form_state->setErrorByName('objective', 
        $this->t('O campo "Objetivo" é obrigatório.'));
    }

    if (empty($audience)) {
      $form_state->setErrorByName('audience', 
        $this->t('O campo "Público-alvo" é obrigatório.'));
    }

    if (empty($instructions_value['value'])) {
      $form_state->setErrorByName('instructions', 
        $this->t('O campo "Instruções adicionais" é obrigatório.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\cms_ai_editorial\Entity\BriefingRequest $entity */
    $entity = $this->entity;

    // Garantir que o estado inicial seja "pending"
    if ($entity->isNew()) {
      $entity->setStatus('pending');
      
      // Criar snapshot do briefing
      $entity->createBriefingSnapshot();
    }

    $status = parent::save($form, $form_state);

    $message_params = [
      '%label' => $entity->label(),
      '@correlation_id' => $entity->getCorrelationId(),
    ];

    if ($status == SAVED_NEW) {
      $this->messenger()->addStatus(
        $this->t('Solicitação criada com sucesso! ID de correlação: @correlation_id', $message_params)
      );
      
      // Redirecionar para página de confirmação
      $form_state->setRedirect('cms_ai_editorial.briefing_request.confirmation', [
        'briefing_request' => $entity->id(),
      ]);
    }
    else {
      $this->messenger()->addStatus(
        $this->t('Solicitação atualizada com sucesso.', $message_params)
      );
      
      // Redirecionar para visualização
      $form_state->setRedirectUrl($entity->toUrl('canonical'));
    }

    return $status;
  }

}
