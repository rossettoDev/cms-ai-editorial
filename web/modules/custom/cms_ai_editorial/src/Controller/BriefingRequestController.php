<?php

namespace Drupal\cms_ai_editorial\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\cms_ai_editorial\Entity\BriefingRequest;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Controller para páginas de solicitação de briefing.
 */
class BriefingRequestController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a BriefingRequestController object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Página de confirmação após criar uma solicitação.
   */
  public function confirmation(BriefingRequest $briefing_request) {
    // Verificar acesso
    if (!$briefing_request->access('view')) {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
    }

    $build = [];

    $build['title'] = [
      '#type' => 'markup',
      '#markup' => '<h2>' . $this->t('Solicitação Criada com Sucesso!') . '</h2>',
    ];

    $build['confirmation_message'] = [
      '#type' => 'markup',
      '#markup' => '<div class="messages messages--status">' .
        '<p>' . $this->t('Sua solicitação de briefing foi criada e está aguardando processamento.') . '</p>' .
        '<p><strong>' . $this->t('ID de Correlação:') . '</strong> ' . $briefing_request->getCorrelationId() . '</p>' .
        '</div>',
    ];

    $build['details'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Detalhes da Solicitação'),
      '#collapsible' => FALSE,
    ];

    $build['details']['objective'] = [
      '#type' => 'item',
      '#title' => $this->t('Objetivo'),
      '#markup' => $briefing_request->get('objective')->value,
    ];

    $build['details']['audience'] = [
      '#type' => 'item',
      '#title' => $this->t('Público-alvo'),
      '#markup' => $briefing_request->get('audience')->value,
    ];

    $status_map = [
      'pending' => $this->t('Pendente'),
      'processing' => $this->t('Processando'),
      'completed' => $this->t('Concluída'),
      'failed' => $this->t('Falhou'),
    ];

    $build['details']['status'] = [
      '#type' => 'item',
      '#title' => $this->t('Estado'),
      '#markup' => $status_map[$briefing_request->getStatus()] ?? $briefing_request->getStatus(),
    ];

    $build['actions'] = [
      '#type' => 'actions',
    ];

    $build['actions']['view'] = [
      '#type' => 'link',
      '#title' => $this->t('Visualizar Solicitação Completa'),
      '#url' => $briefing_request->toUrl('canonical'),
      '#attributes' => [
        'class' => ['button', 'button--primary'],
      ],
    ];

    $build['actions']['list'] = [
      '#type' => 'link',
      '#title' => $this->t('Minhas Solicitações'),
      '#url' => Url::fromRoute('cms_ai_editorial.briefing_request.user_list'),
      '#attributes' => [
        'class' => ['button'],
      ],
    ];

    $build['actions']['new'] = [
      '#type' => 'link',
      '#title' => $this->t('Criar Nova Solicitação'),
      '#url' => Url::fromRoute('entity.briefing_request.add_form'),
      '#attributes' => [
        'class' => ['button'],
      ],
    ];

    return $build;
  }

  /**
   * Página de listagem das solicitações do usuário atual.
   */
  public function userList() {
    $current_user = $this->currentUser();
    
    $build = [];

    $build['title'] = [
      '#type' => 'markup',
      '#markup' => '<h2>' . $this->t('Minhas Solicitações de Briefing') . '</h2>',
    ];

    // Buscar solicitações do usuário atual
    $storage = $this->entityTypeManager->getStorage('briefing_request');
    $query = $storage->getQuery()
      ->condition('uid', $current_user->id())
      ->sort('created', 'DESC')
      ->accessCheck(TRUE);
    
    $ids = $query->execute();
    
    if (empty($ids)) {
      $build['empty'] = [
        '#type' => 'markup',
        '#markup' => '<p>' . $this->t('Você ainda não criou nenhuma solicitação de briefing.') . '</p>',
      ];
    }
    else {
      $requests = $storage->loadMultiple($ids);
      
      $rows = [];
      foreach ($requests as $request) {
        $status_map = [
          'pending' => $this->t('Pendente'),
          'processing' => $this->t('Processando'),
          'completed' => $this->t('Concluída'),
          'failed' => $this->t('Falhou'),
        ];
        
        $objective = $request->get('objective')->value;
        $objective_short = strlen($objective) > 100 ? substr($objective, 0, 97) . '...' : $objective;
        
        $rows[] = [
          $request->getCorrelationId(),
          $objective_short,
          $status_map[$request->getStatus()] ?? $request->getStatus(),
          \Drupal::service('date.formatter')->format($request->get('created')->value, 'short'),
          [
            'data' => [
              '#type' => 'operations',
              '#links' => [
                'view' => [
                  'title' => $this->t('Visualizar'),
                  'url' => $request->toUrl('canonical'),
                ],
              ],
            ],
          ],
        ];
      }

      $build['table'] = [
        '#type' => 'table',
        '#header' => [
          $this->t('ID de Correlação'),
          $this->t('Objetivo'),
          $this->t('Estado'),
          $this->t('Criado em'),
          $this->t('Operações'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('Nenhuma solicitação encontrada.'),
      ];
    }

    $build['actions'] = [
      '#type' => 'actions',
    ];

    $build['actions']['create'] = [
      '#type' => 'link',
      '#title' => $this->t('Criar Nova Solicitação'),
      '#url' => Url::fromRoute('entity.briefing_request.add_form'),
      '#attributes' => [
        'class' => ['button', 'button--primary'],
      ],
    ];

    return $build;
  }

  /**
   * Título da página de confirmação.
   */
  public function confirmationTitle(BriefingRequest $briefing_request) {
    return $this->t('Confirmação da Solicitação');
  }

}
