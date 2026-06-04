<?php

namespace Drupal\Tests\azure_easy_auth\Unit;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Site\Settings;
use Drupal\azure_easy_auth\AzureEasyAuthValidator;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\azure_easy_auth\AzureEasyAuthValidator
 * @group azure_easy_auth
 */
class AzureEasyAuthValidatorTest extends TestCase {

  /**
   * The validator under test.
   *
   * @var \Drupal\azure_easy_auth\AzureEasyAuthValidator
   */
  protected $validator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->validator = new AzureEasyAuthValidator();
  }

  /**
   * Tests authorized email validation.
   */
  public function testIsAuthorized() {
    // Setup Settings mock with authorized principals.
    new Settings([
      'azure_easy_auth.authorized_principals' => ['authorized@princeton.edu', 'another@princeton.edu'],
    ]);

    // Test authorized user via email.
    $authorized_account = $this->createMock(AccountInterface::class);
    $authorized_account->expects($this->any())
      ->method('getEmail')
      ->willReturn('authorized@princeton.edu');
    $authorized_account->expects($this->any())
      ->method('getAccountName')
      ->willReturn('some_name');

    $this->assertTrue($this->validator->isAuthorized($authorized_account));

    // Test authorized user via account name.
    $authorized_name_account = $this->createMock(AccountInterface::class);
    $authorized_name_account->expects($this->any())
      ->method('getEmail')
      ->willReturn('');
    $authorized_name_account->expects($this->any())
      ->method('getAccountName')
      ->willReturn('another@princeton.edu');

    $this->assertTrue($this->validator->isAuthorized($authorized_name_account));

    // Test unauthorized user.
    $unauthorized_account = $this->createMock(AccountInterface::class);
    $unauthorized_account->expects($this->any())
      ->method('getEmail')
      ->willReturn('unauthorized@princeton.edu');
    $unauthorized_account->expects($this->any())
      ->method('getAccountName')
      ->willReturn('unauthorized_name');

    $this->assertFalse($this->validator->isAuthorized($unauthorized_account));

    // Test empty email/name user.
    $empty_account = $this->createMock(AccountInterface::class);
    $empty_account->expects($this->any())
      ->method('getEmail')
      ->willReturn('');
    $empty_account->expects($this->any())
      ->method('getAccountName')
      ->willReturn('');

    $this->assertFalse($this->validator->isAuthorized($empty_account));
  }

}
