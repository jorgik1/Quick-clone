<?php

namespace Drupal\quick_clone\Controller;

use DateTime;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\field\Entity\FieldConfig;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Class CloneController.
 */
class CloneController extends ControllerBase {

  /**
   * CloneController constructor.
   */
  public function __construct(EntityTypeManager $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Quick_clone.
   *
   * @param int $id
   *   Id of the current node.
   *
   * @throws \InvalidArgumentException
   *
   * @return string
   *   Return Hello string.
   */
  public function quickClone($id) {
    $node = $this->entityTypeManager->getStorage('node')->load($id);
    if (!$node instanceof NodeInterface) {
      drupal_set_message($this->t('Node with id @id does not exist.', ['@id' => $id]), 'error');
    }
    else {

      $fields = array_filter($node->getFieldDefinitions(), function ($field_definition) {
        $storage_definitions = $field_definition->getFieldStorageDefinition();
        $settings = $storage_definitions->getSettings();
        if ($field_definition instanceof FieldConfig && $settings['target_type'] === 'paragraph') {
          return $field_definition;
        }
      });
      if (count($fields)) {
        $nodeDuplicate = $node->createDuplicate();
        foreach ($fields as $field) {
          if ($nodeDuplicate->hasField($field) && !$nodeDuplicate->get($field)->isEmpty()) {
            foreach ($nodeDuplicate->{$field} as $value) {
              $value->entity = $value->entity->createDuplicate();
            }
          }
        }

        $date_time = new DateTime();
        $nodeDuplicate->setCreatedTime($date_time->getTimestamp());
        $nodeDuplicate->set('title', 'Clone of ' . $node->getTitle());
        $nodeDuplicate->save();

        drupal_set_message(
          $this->t("Node @title has been created. <a href='/node/@id/edit' target='_blank'>Edit now</a>", [
            '@id' => $nodeDuplicate->id(),
            '@title' => $nodeDuplicate->getTitle(),
          ]
        ), 'status');
      }
      else {
        $nodeDuplicate = $node->createDuplicate();
        $nodeDuplicate->set('title', 'Clone of ' . $node->getTitle());
        $nodeDuplicate->save();
        drupal_set_message(
          $this->t('There is no paragraphs in to node, duplicate of the @title was created', [
            '@title' => $nodeDuplicate->getTitle(),
          ]
        ), 'status');
      }
      return new RedirectResponse('/admin/content');
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity_type.manager'));
  }

}
