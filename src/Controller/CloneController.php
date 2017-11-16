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
    $original_entity = $this->entityTypeManager->getStorage('node')->load($id);
    if (!$original_entity instanceof NodeInterface) {
      drupal_set_message($this->t('Node with id @id does not exist.', ['@id' => $id]), 'error');
    }
    else {

      $new_node = $original_entity->createDuplicate();

      // Check for paragraph fields which need to be duplicated as well.
      foreach ($new_node->getTranslationLanguages() as $langcode => $language) {
        $translated_node = $new_node->getTranslation($langcode);

        foreach ($translated_node->getFieldDefinitions() as $field_definition) {
          $field_storage_definition = $field_definition->getFieldStorageDefinition();
          $field_settings = $field_storage_definition->getSettings();
          if (isset($field_settings['target_type']) && $field_settings['target_type'] === 'paragraph') {

            // Each paragraph entity will be duplicated,
            // so we won't be editing the same as the parent in every clone.
            $field_name = $field_storage_definition->getName();
            if (!$translated_node->get($field_name)->isEmpty()) {
              foreach ($translated_node->get($field_name) as $value) {
                if ($value->entity) {
                  $value->entity = $value->entity->createDuplicate();
                }
              }
            }
          }
        }
        $translated_node->setTitle(t('Clone of @title', ['@title' => $original_entity->getTitle()], ['langcode' => $langcode]));

        $date_time = new DateTime();
        $translated_node->setCreatedTime($date_time->getTimestamp());
        $translated_node->save();

        drupal_set_message(
          $this->t("Node @title has been created. <a href='/node/@id/edit' target='_blank'>Edit now</a>", [
            '@id' => $translated_node->id(),
            '@title' => $translated_node->getTitle(),
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
