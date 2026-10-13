<?php

namespace Drupal\cms_ai_editorial\Entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller para entidade BriefingRequest.
 */
class BriefingRequestAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\cms_ai_editorial\Entity\BriefingRequest $entity */
    
    // Admin tem acesso total
    if ($account->hasPermission('administer briefing requests')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    switch ($operation) {
      case 'view':
        // Usuário pode ver suas próprias solicitações
        if ($entity->getOwnerId() == $account->id()) {
          return AccessResult::allowed()
            ->cachePerPermissions()
            ->cachePerUser()
            ->addCacheableDependency($entity);
        }
        
        // Editorial reviewers podem ver todas as solicitações
        if ($account->hasPermission('view any briefing request')) {
          return AccessResult::allowed()
            ->cachePerPermissions()
            ->addCacheableDependency($entity);
        }
        
        return AccessResult::forbidden('Você só pode visualizar suas próprias solicitações')
          ->cachePerPermissions()
          ->cachePerUser()
          ->addCacheableDependency($entity);

      case 'update':
        // Apenas o autor pode atualizar, e somente se estiver pendente
        if ($entity->getOwnerId() == $account->id() && $entity->getStatus() === BriefingRequest::STATE_PENDING) {
          return AccessResult::allowed()
            ->cachePerPermissions()
            ->cachePerUser()
            ->addCacheableDependency($entity);
        }
        
        return AccessResult::forbidden('Você só pode editar suas próprias solicitações pendentes')
          ->cachePerPermissions()
          ->cachePerUser()
          ->addCacheableDependency($entity);

      case 'delete':
        // Apenas o autor pode deletar, e somente se estiver pendente ou falhou
        if ($entity->getOwnerId() == $account->id() && 
            in_array($entity->getStatus(), [BriefingRequest::STATE_PENDING, BriefingRequest::STATE_FAILED])) {
          return AccessResult::allowed()
            ->cachePerPermissions()
            ->cachePerUser()
            ->addCacheableDependency($entity);
        }
        
        return AccessResult::forbidden('Você só pode deletar suas próprias solicitações pendentes ou falhadas')
          ->cachePerPermissions()
          ->cachePerUser()
          ->addCacheableDependency($entity);

      default:
        return AccessResult::neutral()->cachePerPermissions();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    // Usuários com permissão de criar solicitações de briefing
    if ($account->hasPermission('create briefing request') || 
        $account->hasPermission('administer briefing requests')) {
      return AccessResult::allowed()->cachePerPermissions();
    }
    
    return AccessResult::forbidden('Você não tem permissão para criar solicitações de briefing')
      ->cachePerPermissions();
  }

}
