<?php

namespace Drupal\cron_queue_viewer_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;

/**
 * Class CronQueueViewerTestForm.
 */
class CronQueueViewerTestForm extends FormBase {

  /**
   * Symfony\Component\DependencyInjection\ContainerAwareInterface definition.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerAwareInterface
   */
  protected $queue;
  /**
   * Constructs a new CronQueueViewerTestForm object.
   */
  public function __construct(
    ContainerAwareInterface $queue
  ) {
    $this->queue = $queue;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('queue')
    );
  }


  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cron_queue_viewer_test_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['viewer_link'] = [
      '#title' => $this->t('Viewer link'),
      '#type' => 'link',
      '#url' => Url::fromRoute('cron_queue_viewer.cron_queue_viewer_form'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    // Add a submit button that handles the submission of the form.
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Item to Queue 1'),
    ];

    $form['actions']['submit2'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Item to Queue 2'),
      '#submit' => ['::addToQueue2'],
    ];

//    $form['submit'] = [
//      '#type' => 'submit',
//      '#value' => $this->t('Add Item'),
//    ];

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
    $messenger = \Drupal::messenger();
    $messenger->addMessage('Queue 1', 'status', false);

    // Display result.
    foreach ($form_state->getValues() as $key => $value) {
      $messenger->addMessage($key . ': ' . $value, 'status', false);
    }

    /** @var \Drupal\Core\Queue\QueueInterface $queue */
    $queue = $this->queue->get('test1_cron_queue_viewer');
    $queue->createItem(null);
  }

  /**
   * {@inheritdoc}
   */
  public function addToQueue2(array &$form, FormStateInterface $form_state) {
    $messenger = \Drupal::messenger();
    $messenger->addMessage('Queue 2', 'status', false);

    // Display result.
    foreach ($form_state->getValues() as $key => $value) {
      $messenger->addMessage($key . ': ' . $value, 'status', false);
    }

    /** @var \Drupal\Core\Queue\QueueInterface $queue */
    $queue = $this->queue->get('test2_cron_queue_viewer');
    $queue->createItem(null);
  }

}
