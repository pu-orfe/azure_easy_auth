<?php

namespace Drupal\azure_easy_auth\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Controller to simulate Entra ID login in local development environment.
 */
class SimulateLoginController extends ControllerBase {

  /**
   * Simulates login process.
   */
  public function simulate(Request $request) {
    // Restrict access to local environment only via environment config.
    $is_local = Settings::get('is_local_dev', FALSE);

    if (!$is_local) {
      throw new AccessDeniedHttpException('This endpoint is only available in local development.');
    }

    $email = $request->query->get('email', 'bino@princeton.edu');
    $authorized_principals = Settings::get('azure_easy_auth.authorized_principals', []);

    if (!in_array($email, $authorized_principals)) {
      \Drupal::messenger()->addError($this->t('The email @email is not in the AUTHORIZED_PRINCIPALS list.', [
        '@email' => $email,
      ]));
      return new RedirectResponse(Url::fromRoute('<front>')->toString());
    }

    $user_storage = $this->entityTypeManager()->getStorage('user');
    $users = $user_storage->loadByProperties(['mail' => $email]);

    if (!empty($users)) {
      $user = reset($users);
    }
    else {
      // Create user.
      $username = explode('@', $email)[0];
      $base_username = $username;
      $counter = 1;

      // Ensure unique username.
      while (!empty($user_storage->loadByProperties(['name' => $username]))) {
        $username = $base_username . $counter;
        $counter++;
      }

      $user = $user_storage->create([
        'name' => $username,
        'mail' => $email,
        'status' => 1,
      ]);
      $user->save();
    }

    user_login_finalize($user);

    \Drupal::messenger()->addStatus($this->t('Successfully simulated Entra ID login for @email.', [
      '@email' => $email,
    ]));

    return new RedirectResponse(Url::fromRoute('<front>')->toString());
  }

}
