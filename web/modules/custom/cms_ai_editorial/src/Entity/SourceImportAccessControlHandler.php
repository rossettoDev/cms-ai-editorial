<?php

namespace Drupal\cms_ai_editorial\Entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller para a entidade SourceImport.
 *
 * Restringe acesso apenas ao proprietário e administradores.
 */
class SourceImportAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\cms_ai_editorial\Entity\SourceImport $entity */

    // Administradores têm acesso total
    if ($account->hasPermission('administer source imports')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    switch ($operation) {
      case 'view':
        // Apenas o proprietário pode ver
        if ($entity->getOwnerId() === $account->id()) {
          return AccessResult::allowedIfHasPermission($account, 'view own source imports')
            ->cachePerPermissions()
            ->cachePerUser()
            ->addCacheableDependency($entity);
        }
        return AccessResult::forbidden('Apenas o proprietário pode visualizar esta importação.')
          ->cachePerPermissions()
          ->cachePerUser()
          ->addCacheableDependency($entity);

      case 'update':
        // Importações não podem ser editadas
        return AccessResult::forbidden('Importações de fonte não podem ser editadas.')
          ->cachePerPermissions();

      case 'delete':
        // Apenas o proprietário ou admin pode deletar
        if ($entity->getOwnerId() === $account->id()) {
          return AccessResult::allowedIfHasPermission($account, 'delete own source imports')
            ->cachePerPermissions()
            ->cachePerUser()
            ->addCacheableDependency($entity);
        }
        return AccessResult::forbidden('Apenas o proprietário pode deletar esta importação.')
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
    // Apenas editores AI e revisores podem criar importações
    return AccessResult::allowedIfHasPermission($account, 'create source imports')
      ->cachePerPermissions();
  }

}
