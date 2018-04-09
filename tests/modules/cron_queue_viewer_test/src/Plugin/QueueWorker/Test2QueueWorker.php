<?php

namespace Drupal\cron_queue_viewer_test\Plugin\QueueWorker;


use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Cron/Queue viewer test QueueWorker 2
 *
 * @package modules\custom\cron_queue_viewer_test\src\Plugin\QueueWorker
 * @QueueWorker(
 *   id = "test2_cron_queue_viewer",
 *   title = @Translation("Test2 Queue Worker"),
 *   cron = {"time" = 22}
 * )
 */
class Test2QueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {
    /**
     * The user storage.
     *
     * @var \Drupal\Core\Entity\EntityStorageInterface
     */
    protected $userStorage;

    /**
     * Constructs a new CronDeleteUser object.
     *
     * @param array $configuration
     *   A configuration array containing information about the plugin instance.
     * @param string $plugin_id
     *   The plugin_id for the plugin instance.
     * @param mixed $plugin_definition
     *   The plugin implementation definition.
     * @param \Drupal\Core\Entity\EntityStorageInterface $user_storage
     *   The user storage.
     */
    public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityStorageInterface $user_storage) {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
        $this->userStorage = $user_storage;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
        return new static(
            $configuration,
            $plugin_id,
            $plugin_definition,
            $container->get('entity_type.manager')->getStorage('user')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function processItem($data) {
      \Drupal::logger('cron_queue_viewer_test')->debug('Test2 processItem start');
      sleep(2);
      \Drupal::logger('cron_queue_viewer_test')->debug('Test2 processItem stop');
    }
}