<?php

namespace Drupal\cms_ai_editorial\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\cms_ai_editorial\Service\UrlFetchService;
use Drupal\cms_ai_editorial\Service\HtmlParserService;
use Drupal\cms_ai_editorial\Entity\SourceImport;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\InvokeCommand;

/**
 * Formulário para importar conteúdo de URLs externas.
 */
class UrlImportForm extends FormBase {

  /**
   * O serviço de fetch de URL.
   *
   * @var \Drupal\cms_ai_editorial\Service\UrlFetchService
   */
  protected $urlFetchService;

  /**
   * O serviço de parser HTML.
   *
   * @var \Drupal\cms_ai_editorial\Service\HtmlParserService
   */
  protected $htmlParserService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->urlFetchService = $container->get('cms_ai_editorial.url_fetch');
    $instance->htmlParserService = $container->get('cms_ai_editorial.html_parser');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cms_ai_editorial_url_import_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#prefix'] = '<div id="url-import-form-wrapper">';
    $form['#suffix'] = '</div>';

    $form['description'] = [
      '#type' => 'markup',
      '#markup' => '<div class="description"><p>' . 
        $this->t('Cole a URL de uma página pública para importar seu conteúdo como referência. Apenas páginas HTTP/HTTPS públicas são aceitas.') .
        '</p></div>',
    ];

    $form['url'] = [
      '#type' => 'url',
      '#title' => $this->t('URL da página'),
      '#required' => TRUE,
      '#maxlength' => 2048,
      '#description' => $this->t('Cole a URL completa (ex: https://exemplo.com/artigo)'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['fetch'] = [
      '#type' => 'submit',
      '#value' => $this->t('Buscar e Visualizar'),
      '#ajax' => [
        'callback' => '::ajaxFetchCallback',
        'wrapper' => 'url-import-form-wrapper',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Buscando conteúdo...'),
        ],
      ],
    ];

    // Mostrar preview se houver dados
    $storage = $form_state->getStorage();
    if (!empty($storage['import_data'])) {
      $data = $storage['import_data'];

      $form['preview'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Preview do Conteúdo Importado'),
        '#weight' => 10,
      ];

      $form['preview']['info'] = [
        '#type' => 'markup',
        '#markup' => '<div class="import-info">' .
          '<p><strong>' . $this->t('URL final:') . '</strong> ' . htmlspecialchars($data['final_url']) . '</p>' .
          '<p><strong>' . $this->t('Título:') . '</strong> ' . htmlspecialchars($data['title']) . '</p>' .
          '<p><strong>' . $this->t('Tamanho do texto:') . '</strong> ' . strlen($data['text']) . ' ' . $this->t('caracteres') . '</p>' .
          '</div>',
      ];

      $form['preview']['text'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Texto extraído'),
        '#default_value' => $data['text'],
        '#rows' => 15,
        '#disabled' => TRUE,
        '#description' => $this->t('Este texto será usado como referência para a geração do artigo.'),
      ];

      $form['actions']['use'] = [
        '#type' => 'submit',
        '#value' => $this->t('Usar Este Texto'),
        '#submit' => ['::submitUse'],
        '#ajax' => [
          'callback' => '::ajaxUseCallback',
        ],
      ];

      $form['actions']['cancel'] = [
        '#type' => 'submit',
        '#value' => $this->t('Cancelar'),
        '#submit' => ['::submitCancel'],
        '#ajax' => [
          'callback' => '::ajaxFetchCallback',
          'wrapper' => 'url-import-form-wrapper',
        ],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $url = $form_state->getValue('url');
    
    if (empty($url)) {
      $form_state->setErrorByName('url', $this->t('URL é obrigatória.'));
      return;
    }

    // Validar formato básico de URL
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
      $form_state->setErrorByName('url', $this->t('URL inválida.'));
      return;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $url = $form_state->getValue('url');

    // Buscar conteúdo
    $fetch_result = $this->urlFetchService->fetchUrl($url);

    if (!$fetch_result['success']) {
      $this->messenger()->addError(
        $this->t('Erro ao buscar URL: @error', ['@error' => $fetch_result['error']])
      );
      return;
    }

    // Parsear HTML
    $parse_result = $this->htmlParserService->extractContent($fetch_result['content']);

    if (!$parse_result['success']) {
      $this->messenger()->addError(
        $this->t('Erro ao processar conteúdo: @error', ['@error' => $parse_result['error']])
      );
      return;
    }

    // Armazenar dados no form state
    $form_state->setStorage([
      'import_data' => [
        'source_url' => $url,
        'final_url' => $fetch_result['final_url'],
        'title' => $parse_result['title'],
        'text' => $parse_result['text'],
        'content_hash' => hash('sha256', $fetch_result['content']),
      ],
    ]);

    $form_state->setRebuild(TRUE);
  }

  /**
   * Submit handler para usar o texto.
   */
  public function submitUse(array &$form, FormStateInterface $form_state) {
    $storage = $form_state->getStorage();
    
    if (empty($storage['import_data'])) {
      $this->messenger()->addError($this->t('Nenhum conteúdo para importar.'));
      return;
    }

    $data = $storage['import_data'];

    // Criar entidade SourceImport
    $source_import = SourceImport::create([
      'source_url' => $data['source_url'],
      'final_url' => $data['final_url'],
      'extracted_title' => $data['title'],
      'extracted_text' => $data['text'],
      'content_hash' => $data['content_hash'],
      'import_date' => \Drupal::time()->getRequestTime(),
    ]);

    $source_import->save();

    // Armazenar ID da importação para uso posterior
    $form_state->set('source_import_id', $source_import->id());
    $form_state->set('imported_text', $data['text']);

    $this->messenger()->addStatus($this->t('Conteúdo importado com sucesso!'));
  }

  /**
   * Submit handler para cancelar.
   */
  public function submitCancel(array &$form, FormStateInterface $form_state) {
    $form_state->setStorage([]);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Callback AJAX para fetch.
   */
  public function ajaxFetchCallback(array &$form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * Callback AJAX para usar texto.
   */
  public function ajaxUseCallback(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    if ($form_state->hasAnyErrors()) {
      return $response;
    }

    $imported_text = $form_state->get('imported_text');
    
    if ($imported_text) {
      // Fechar modal e preencher campo de referência
      $response->addCommand(new InvokeCommand('.ui-dialog-titlebar-close', 'click'));
      $response->addCommand(new InvokeCommand('#edit-reference-text-0-value', 'val', [$imported_text]));
    }

    return $response;
  }

}
