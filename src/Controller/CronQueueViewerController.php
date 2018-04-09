<?php

namespace Drupal\cron_queue_viewer\Controller;

use Drupal\Component\Utility\Timer;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\RequeueException;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\Database\Driver\mysql\Connection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class CronQueueViewerController.
 *
 * @see \Drupal\Core\Cron class (core\lib\Drupal\Core\Cron.php)
 */
class CronQueueViewerController extends ControllerBase {

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The lock service.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lock;

  /**
   * The account switcher service.
   *
   * @var \Drupal\Core\Session\AccountSwitcherInterface
   */
  protected $accountSwitcher;

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
   * Drupal\Core\Database\Driver\mysql\Connection definition.
   *
   * @var \Drupal\Core\Database\Driver\mysql\Connection
   */
  protected $database;


  /**
   * Constructs a new CronQueueViewerController object.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The lock service.
   * @param \Drupal\Core\Session\AccountSwitcherInterface $account_switcher
   *   The account switching service.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue service.
   * @param \Drupal\Core\Queue\QueueWorkerManagerInterface $queue_manager
   *   The queue plugin manager.
   */
  public function __construct(MessengerInterface $messenger, ModuleHandlerInterface $module_handler, LockBackendInterface $lock, AccountSwitcherInterface $account_switcher, QueueFactory $queue_factory, QueueWorkerManagerInterface $queue_manager, Connection $database) {
    $this->messenger = $messenger;    // Inside MessengerTrait
    $this->moduleHandler = $module_handler;
    $this->lock = $lock;
    $this->accountSwitcher = $account_switcher;
    $this->queueFactory = $queue_factory;
    $this->queueManager = $queue_manager;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger'),
      $container->get('module_handler'),
      $container->get('lock'),
      $container->get('account_switcher'),
      $container->get('queue'),
      $container->get('plugin.manager.queue_worker'),
      $container->get('database')
    );
  }

  /**
   * Run items from a queue as would Drupal core do.
   *
   */
  public function runCron($cid) {
    // Allow execution to continue even if the request gets cancelled.
    @ignore_user_abort(TRUE);

    // Force the current user to anonymous to ensure consistent permissions on
    // cron runs.
    $this->accountSwitcher->switchTo(new AnonymousUserSession());

    // Try to acquire cron lock.
    if (!$this->lock->acquire('cron', 900.0)) {
      $this->messenger->addMessage($this->t('A cron task is already running!'), MessengerInterface::TYPE_WARNING, false);
    }
    else {

      // Run the cron task
      Timer::start('cron_' . $cid);
      // Do not let an exception thrown by one module disturb another.
      try {
        $this->moduleHandler->invoke($cid, 'cron');
      }
      catch (\Exception $e) {
        watchdog_exception('cron', $e);
      }
      Timer::stop('cron_' . $cid);

      // Release cron lock.
      $this->lock->release('cron');

      // Display a message
      $this->messenger->addMessage($this->t('Execution of @module_cron() took @time.', [
        '@module_cron' => $cid,
        '@time' => Timer::read('cron_' . $cid) . 'ms',
      ]));

    }

    // Restore the user.
    $this->accountSwitcher->switchBack();

    return $this->redirect('cron_queue_viewer.cron_queue_viewer_form');
  }

  /**
   * Run items from a queue as would Drupal core do.
   *
   * @param string $qid
   *   Machine name of the queue
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   */
  public function runQueue($qid) {
    // Allow execution to continue even if the request gets cancelled.
    @ignore_user_abort(TRUE);

    // Force the current user to anonymous to ensure consistent permissions on
    // cron runs.
    $this->accountSwitcher->switchTo(new AnonymousUserSession());

    // Run the cron task
    Timer::start('queue_' . $qid);


    // Checks cron info
    $info = $this->queueManager->getDefinition($qid, FALSE);

    if (isset($info['cron'])) {

      // From core:
      // "Make sure every queue exists. There is no harm in trying to recreate
      // an existing queue."
      $this->queueFactory->get($qid)->createQueue();

      $queue_worker = $this->queueManager->createInstance($qid);
      $end = time() + (isset($info['cron']['time']) ? $info['cron']['time'] : 15);
      $queue = $this->queueFactory->get($qid);
      // TODO FIX: There is a bug here but on purpose as the core as the same one (still present on 8.5.1)
      // (see https://www.drupal.org/project/drupal/issues/2883819)
      $lease_time = isset($info['cron']['time']) ?: NULL;

      while (time() < $end && ($item = $queue->claimItem($lease_time))) {
        try {
          $queue_worker->processItem($item->data);
          $queue->deleteItem($item);
        }
        catch (RequeueException $e) {
          // The worker requested the task be immediately requeued.
          $queue->releaseItem($item);
        }
        catch (SuspendQueueException $e) {
          // If the worker indicates there is a problem with the whole queue,
          // release the item and skip to the next queue.
          $queue->releaseItem($item);

          watchdog_exception('cron', $e);

          // Skip to the next queue.
          break;
        }
        catch (\Exception $e) {
          // In case of any other kind of exception, log it and leave the item
          // in the queue to be processed again later.
          watchdog_exception('cron', $e);
        }
      }

    }

    Timer::stop('queue_' . $qid);
    // Display a message
    $this->messenger->addMessage($this->t('Execution of @queue took @time.', [
      '@queue' => $qid,
      '@time' => Timer::read('queue_' . $qid) . 'ms',
    ]));

    // Restore the user.
    $this->accountSwitcher->switchBack();

    return $this->redirect('cron_queue_viewer.cron_queue_viewer_form');
  }

  /**
   * Deletes a queue and every item in the queue.
   *
   * @param string $qid
   *   Machine name of the queue
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   */
  public function deleteQueue($qid) {
    // Allow execution to continue even if the request gets cancelled.
    @ignore_user_abort(TRUE);

    // Force the current user to anonymous to ensure consistent permissions on
    // cron runs.
    $this->accountSwitcher->switchTo(new AnonymousUserSession());

    $queue = $this->queueFactory->get($qid);
    if ($queue)
      $queue->deleteQueue();

    // Restore the user.
    $this->accountSwitcher->switchBack();

    return $this->redirect('cron_queue_viewer.cron_queue_viewer_form');
  }
}
