<?php

namespace Drupal\azure_easy_auth\EventSubscriber;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event subscriber to automatically log in users authenticated by Azure Easy Auth.
 */
class AzureEasyAuthSubscriber implements EventSubscriberInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new AzureEasyAuthSubscriber.
   */
  public function __construct(AccountProxyInterface $current_user, EntityTypeManagerInterface $entity_type_manager) {
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Automatically logs in users if authenticated via Azure Easy Auth.
   */
  public function onRequest(RequestEvent $event) {
    // 1. Check if Azure Easy Auth is enabled in settings.
    if (!Settings::get('azure_easy_auth.enabled', FALSE)) {
      return;
    }

    // 2. Only run for anonymous users.
    if (!$this->currentUser->isAnonymous()) {
      return;
    }

    $request = $event->getRequest();

    // 3. Retrieve the UPN from the standard Azure Easy Auth header.
    $upn = $request->headers->get('X-MS-CLIENT-PRINCIPAL-NAME');

    if (empty($upn)) {
      return;
    }

    // 4. Extract the username / netID (first portion before the @).
    $parts = explode('@', $upn);
    $username = $parts[0];

    if (empty($username)) {
      return;
    }

    // 5. Query for an active (status = 1) Drupal user with that username.
    $user_storage = $this->entityTypeManager->getStorage('user');
    $users = $user_storage->loadByProperties([
      'name' => $username,
      'status' => 1,
    ]);

    if (!empty($users)) {
      $user = reset($users);

      // 6. Check if the user is authorized via the gatekeeper validator.
      /** @var \Drupal\azure_easy_auth\AzureEasyAuthValidator $validator */
      if (\Drupal::hasService('azure_easy_auth.validator')) {
        $validator = \Drupal::service('azure_easy_auth.validator');
        if (!$validator->isAuthorized($user)) {
          return;
        }
      }

      // 7. Finalize login.
      $this->finalizeLogin($user);
    }
  }

  /**
   * Finalizes the user login.
   *
   * @param \Drupal\Core\Session\AccountInterface|\Drupal\user\UserInterface $user
   *   The user entity.
   */
  protected function finalizeLogin($user) {
    if (function_exists('user_login_finalize')) {
      user_login_finalize($user);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // Run at a priority of 100, which is after standard authentication has run
    // but before routing and access checks.
    $events[KernelEvents::REQUEST][] = ['onRequest', 100];
    return $events;
  }

}
