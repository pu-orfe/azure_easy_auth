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
   * Known-production host suffixes. Even if $settings['is_local_dev'] is
   * somehow truthy on one of these, the endpoint stays sealed.
   */
  private const PROD_HOST_SUFFIXES = [
    'thesis.orfe.princeton.edu',
    'orfe.princeton.edu',
    'azurewebsites.net',
    'princeton.edu',
  ];

  /**
   * Simulates login process.
   *
   * Passwordless impersonation of any AUTHORIZED_PRINCIPALS user, so the
   * access gate is defense-in-depth:
   *   1. $settings['is_local_dev'] must be TRUE (env-var-only in settings.php).
   *   2. The request host must NOT match a known-prod suffix.
   *   3. The requested email must be on the AUTHORIZED_PRINCIPALS allowlist.
   * Every attempt is logged at warning severity for after-the-fact review.
   */
  public function simulate(Request $request) {
    $logger = \Drupal::logger('azure_easy_auth');
    $host = strtolower((string) $request->getHost());

    // Belt-and-suspenders: even with is_local_dev true, never impersonate
    // on a prod-looking host.
    foreach (self::PROD_HOST_SUFFIXES as $suffix) {
      if ($host === $suffix || str_ends_with($host, '.' . $suffix)) {
        $logger->warning('Refused /user/simulate-login on production-shaped host @host.', ['@host' => $host]);
        throw new AccessDeniedHttpException('This endpoint is not available on this host.');
      }
    }

    if (!Settings::get('is_local_dev', FALSE)) {
      $logger->warning('Refused /user/simulate-login: is_local_dev is not set (host @host).', ['@host' => $host]);
      throw new AccessDeniedHttpException('This endpoint is only available in local development.');
    }

    $email = $request->query->get('email', 'bino@princeton.edu');
    $authorized_principals = Settings::get('azure_easy_auth.authorized_principals', []);

    if (!in_array($email, $authorized_principals)) {
      $logger->warning('Refused /user/simulate-login for @email: not on AUTHORIZED_PRINCIPALS.', ['@email' => $email]);
      \Drupal::messenger()->addError($this->t('The email @email is not in the AUTHORIZED_PRINCIPALS list.', [
        '@email' => $email,
      ]));
      return new RedirectResponse(Url::fromRoute('<front>')->toString());
    }

    $logger->warning('Simulated Entra ID login as @email from host @host.', [
      '@email' => $email,
      '@host' => $host,
    ]);

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
