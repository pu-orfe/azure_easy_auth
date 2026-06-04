<?php

namespace Drupal\azure_easy_auth;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Site\Settings;

/**
 * Validates whether user accounts are authorized via AUTHORIZED_PRINCIPALS.
 */
class AzureEasyAuthValidator {

  /**
   * Checks if the given account is authorized.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account to check.
   *
   * @return bool
   *   TRUE if authorized, FALSE otherwise.
   */
  public function isAuthorized(AccountInterface $account) {
    $email = $account->getEmail();
    $name = $account->getAccountName();

    $authorized_principals = Settings::get('azure_easy_auth.authorized_principals', []);

    if (!empty($email) && in_array($email, $authorized_principals)) {
      return TRUE;
    }
    if (!empty($name) && in_array($name, $authorized_principals)) {
      return TRUE;
    }

    return FALSE;
  }

}
