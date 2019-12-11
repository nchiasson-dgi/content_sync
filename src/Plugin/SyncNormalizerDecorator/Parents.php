<?php

namespace Drupal\content_sync\Plugin\SyncNormalizerDecorator;


use Drupal\content_sync\Plugin\SyncNormalizerDecoratorBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 *
 * @SyncNormalizerDecorator(
 *   id = "parents",
 *   name = @Translation("Parents"),
 * )
 */
class Parents extends SyncNormalizerDecoratorBase implements ContainerFactoryPluginInterface {


  protected $entityTypeManager;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   *
   * @return static
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * @param array $normalized_entity
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   * @param $format
   * @param array $context
   */
  public function decorateNormalization(array &$normalized_entity, ContentEntityInterface $entity, $format, array $context = []) {
    if ($entity->hasField('parent')) {
      $entity_type = $entity->getEntityTypeId();
      $storage = $this->entityTypeManager->getStorage($entity_type);
      if (method_exists($storage, 'loadParents')) {
        $parents = $storage->loadParents($entity->id());
        foreach ($parents as $parent_key => $parent) {
          if (!$this->parentExistence($parent->uuid(), $normalized_entity)) {
            $normalized_entity['parent'][] = [
              'target_type' => $entity_type,
              'target_uuid' => $parent->uuid(),
            ];
            $normalized_entity['_content_sync']['entity_dependencies'][$entity_type][] = $entity_type . "." . $parent->bundle() . "." . $parent->uuid();
          }
        }
      }
      elseif (method_exists($entity, 'getParentId')) {
        $parent = $entity->getParentId();
        if (($tmp = strstr($parent, ':')) !== FALSE) {
          $parent_uuid = substr($tmp, 1);
          if (!$this->parentExistence($parent_uuid, $normalized_entity)) {
            $normalized_entity['parent'][] = [
              'target_type' => $entity_type,
              'target_uuid' => $parent_uuid,
            ];
            $normalized_entity['_content_sync']['entity_dependencies'][$entity_type][] = $entity_type . "." . $entity_type . "." . $parent_uuid;
          }
        }
      }
    }
  }

  /**
   * Sees if the parent has not already been added prior to this point.
   *
   * @param string $parent_uuid
   *   The UUID of the parent to check against.
   * @param array $normalized_entity
   *   The entity being exported.
   *
   * @return bool
   *   TRUE if it already exists, FALSE if not.
   */
  protected function parentExistence($parent_uuid, array $normalized_entity) {
    return array_search($parent_uuid, array_column($normalized_entity['parent'], 'target_uuid')) !== FALSE;
  }

}
