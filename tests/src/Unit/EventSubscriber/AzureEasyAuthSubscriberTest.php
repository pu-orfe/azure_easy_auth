<?php

namespace Drupal\Tests\azure_easy_auth\Unit\EventSubscriber;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Site\Settings;
use Drupal\azure_easy_auth\EventSubscriber\AzureEasyAuthSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * @coversDefaultClass \Drupal\azure_easy_auth\EventSubscriber\AzureEasyAuthSubscriber
 * @group azure_easy_auth
 */
class AzureEasyAuthSubscriberTest extends TestCase {

  /**
   * The mocked current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $currentUser;

  /**
   * The mocked entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * The mocked user storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $userStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->currentUser = $this->createMock(AccountProxyInterface::class);
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->userStorage = $this->createMock(EntityStorageInterface::class);

    $this->entityTypeManager->expects($this->any())
      ->method('getStorage')
      ->with('user')
      ->willReturn($this->userStorage);
  }

  /**
   * Tests that onRequest returns early when azure_easy_auth is disabled.
   */
  public function testOnRequestDisabled() {
    new Settings([
      'azure_easy_auth.enabled' => FALSE,
    ]);

    // If disabled, it should not check anything else.
    $this->currentUser->expects($this->never())->method('isAnonymous');

    $event = $this->createMock(RequestEvent::class);

    $subscriber = new AzureEasyAuthSubscriber($this->currentUser, $this->entityTypeManager);
    $subscriber->onRequest($event);
  }

  /**
   * Tests that onRequest returns early when the user is not anonymous.
   */
  public function testOnRequestNotAnonymous() {
    new Settings([
      'azure_easy_auth.enabled' => TRUE,
    ]);

    $this->currentUser->expects($this->once())
      ->method('isAnonymous')
      ->willReturn(FALSE);

    $event = $this->createMock(RequestEvent::class);
    $event->expects($this->never())->method('getRequest');

    $subscriber = new AzureEasyAuthSubscriber($this->currentUser, $this->entityTypeManager);
    $subscriber->onRequest($event);
  }

  /**
   * Tests onRequest with empty header.
   */
  public function testOnRequestEmptyHeader() {
    new Settings([
      'azure_easy_auth.enabled' => TRUE,
    ]);

    $this->currentUser->expects($this->once())
      ->method('isAnonymous')
      ->willReturn(TRUE);

    $request = $this->createMock(Request::class);
    $headers = $this->createMock(HeaderBag::class);
    $headers->expects($this->once())
      ->method('get')
      ->with('X-MS-CLIENT-PRINCIPAL-NAME')
      ->willReturn(NULL);

    $request->headers = $headers;

    $event = $this->createMock(RequestEvent::class);
    $event->expects($this->once())
      ->method('getRequest')
      ->willReturn($request);

    $subscriber = new AzureEasyAuthSubscriber($this->currentUser, $this->entityTypeManager);
    $subscriber->onRequest($event);
  }

  /**
   * Tests successful auto-login scenario.
   */
  public function testOnRequestSuccessfulLogin() {
    new Settings([
      'azure_easy_auth.enabled' => TRUE,
    ]);

    $this->currentUser->expects($this->once())
      ->method('isAnonymous')
      ->willReturn(TRUE);

    $request = $this->createMock(Request::class);
    $headers = $this->createMock(HeaderBag::class);
    $headers->expects($this->once())
      ->method('get')
      ->with('X-MS-CLIENT-PRINCIPAL-NAME')
      ->willReturn('bino@princeton.edu');

    $request->headers = $headers;

    $event = $this->createMock(RequestEvent::class);
    $event->expects($this->once())
      ->method('getRequest')
      ->willReturn($request);

    // Mock loading the user.
    $mock_user = $this->createMock(AccountInterface::class);
    $this->userStorage->expects($this->once())
      ->method('loadByProperties')
      ->with([
        'name' => 'bino',
        'status' => 1,
      ])
      ->willReturn([$mock_user]);

    // Use a partial mock/subclass to capture finalizeLogin.
    $subscriber = $this->getMockBuilder(AzureEasyAuthSubscriber::class)
      ->setConstructorArgs([$this->currentUser, $this->entityTypeManager])
      ->onlyMethods(['finalizeLogin'])
      ->getMock();

    $subscriber->expects($this->once())
      ->method('finalizeLogin')
      ->with($mock_user);

    $subscriber->onRequest($event);
  }

}
