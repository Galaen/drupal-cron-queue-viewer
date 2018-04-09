<?php

namespace Drupal\cron_queue_viewer\Form;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Queue\QueueWorkerManagerInterface;

/**
 * Class CronQueueViewerForm.
 *
 * @see \Drupal\Core\Cron class (core\lib\Drupal\Core\Cron.php)
 */
class CronQueueViewerForm extends FormBase {

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The queue service.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * The queue plugin manager.
   *
   * @var \Drupal\Core\Queue\QueueWorkerManagerInterface
   */
  protected $queueManager;


  /**
   * Constructs a new CronQueueViewerForm object.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue service.
   * @param \Drupal\Core\Queue\QueueWorkerManagerInterface $queue_manager
   *   The queue plugin manager.
   */
  public function __construct(MessengerInterface $messenger, ModuleHandlerInterface $module_handler, QueueFactory $queue_factory, QueueWorkerManagerInterface $queue_manager) {
    $this->messenger = $messenger;  // Inside MessengerTrait
    $this->moduleHandler = $module_handler;
    $this->queueFactory = $queue_factory;
    $this->queueManager = $queue_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger'),
      $container->get('module_handler'),
      $container->get('queue'),
      $container->get('plugin.manager.queue_worker')
    );
  }


  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cron_queue_viewer_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['desc'] = [
      '#type' => 'markup',
      '#markup' => $this->t('<p><strong>RUN</strong> will run the Cron or Queue as close as possible as the core does.</p>'),
      '#weight' => '0',
    ];


    $header = [
      'title' => $this->t('Name'),
      'type' => $this->t('Type'),
      'id' => $this->t('Id'),
      'provider' => $this->t('Provider'),
      'time' => $this->t('Cron time'),
      'items' => t('Items'),
      //      'class' => t('Class'),
      //      '#weight' => t('Weight'),
      'operations' => t('Operations'),
    ];

    $options = [];

    // --------
    // - CRON -
    // --------
    // Iterate through the modules calling their cron handlers (if any):
    foreach ($this->moduleHandler->getImplementations('cron') as $module) {

      $operations = [];
      $operations['run'] = [
        'title' => t('Run'),
        'url' => Url::fromRoute('cron_queue_viewer.cron_run', ['cid' => $module]),
      ];

      $options[$module . '_cron'] = [
        'title' => $this->moduleHandler->getName($module),
        'type' => $this->t('Cron'),
        'id' => $module . '_cron',
        'provider' => $module,
        'time' => '-',
        'items' => '-',
//        'class' => '-',
        'operations' => [
          'data' => [
            '#type' => 'operations',
            '#links' => $operations,
          ],
        ],
      ];
    }


    // ---------
    // - QUEUE -
    // ---------
    $defQueues = $this->queueManager->getDefinitions();
    //dump($defQueues);

    // Get queue info
    foreach ($defQueues as $id => $def) {

      /** @var \Drupal\Core\Queue\QueueInterface $queue */
      $queue = $this->queueFactory->get($id);

      $title = (string) $def['title'];
      $provider = $def['provider'] ?? "";
      $time = $def['cron']['time'] ?? 0;
      //$class = $def['class'] ?? "";

      $operations = [];
      $operations['run'] = [
        'title' => t('Run'),
        'url' => Url::fromRoute('cron_queue_viewer.queue_run', ['qid' => $id]),
      ];
      $operations['delete'] = [
        'title' => t('Delete'),
        'url' => Url::fromRoute('cron_queue_viewer.queue_delete', ['qid' => $id]),
      ];

      $options[$id] = [
        'title' => $title,
        'type' => $this->t('Queue'),
        'id' => $id,
        'provider' => $provider,
        'time' => $time,
        'items' => $queue->numberOfItems(),
//        'class' => $class,
//        '#weight' => [
//          '#type' => 'weight',
//          '#title' => t('Weight toto'),
//          '#title_display' => 'invisible',
//          '#default_value' => '0',
//          // Classify the weight element for #tabledrag.
//          '#attributes' => array('class' => array('cronqueue-order-weight')),
//        ],
        'operations' => [
          'data' => [
            '#type' => 'operations',
            '#links' => $operations,
          ],
        ],
      ];
      //$options[$id]['#attributes']['class'][] = 'draggable';
    }

    $form['cronqueue'] = [
      '#type' => 'tableselect', //'table',
      '#title' => $this->t('CronQueue'),
      '#description' => $this->t('Cron and Queue table'),
      '#header' => $header,
      '#options' => $options,
      '#weight' => '1',
      '#empty' => $this->t('No cron nor queue'),
//      '#tableselect' => TRUE,
//      '#tabledrag' => array(
//        array(
//          'action' => 'order',
//          'relationship' => 'sibling',
//          'group' => 'cronqueue-order-weight',
//        ),
//      ),
    ];

    $form['todo'] = [
      '#type' => 'markup',
      '#markup' => $this->t('<p><strong>RUN SELECTED:</strong> is not implemented yet.</p>'),
      '#weight' => '2',
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Run selected'),
      '#weight' => '3',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Display a message
    $this->messenger->addMessage($this->t('Not implemented yet!'), MessengerInterface::TYPE_WARNING);

  }

}
