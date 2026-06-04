<?php

namespace Drupal\Tests\azure_easy_auth\Unit;

use Drupal\azure_easy_auth\AzureEasyAuthGuzzleMiddleware;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * @coversDefaultClass \Drupal\azure_easy_auth\AzureEasyAuthGuzzleMiddleware
 * @group azure_easy_auth
 */
class AzureEasyAuthGuzzleMiddlewareTest extends TestCase {

  /**
   * Tests that the middleware maps userPrincipalName to mail when mail is empty.
   */
  public function testMiddlewareMapsUserPrincipalName() {
    $middleware = new AzureEasyAuthGuzzleMiddleware();

    // Mock URI pointing to Microsoft Graph /me endpoint.
    $uri = $this->createMock(UriInterface::class);
    $uri->method('getHost')->willReturn('graph.microsoft.com');
    $uri->method('getPath')->willReturn('/v1.0/me');

    $request = $this->createMock(RequestInterface::class);
    $request->method('getUri')->willReturn($uri);

    // Mock response body with empty mail and valid userPrincipalName.
    $responseBody = Utils::streamFor(json_encode([
      'mail' => '',
      'userPrincipalName' => 'bino@princeton.edu',
      'displayName' => 'Bino',
    ]));

    $response = $this->createMock(ResponseInterface::class);
    $response->method('getBody')->willReturn($responseBody);

    $response->expects($this->once())
      ->method('withBody')
      ->with($this->callback(function ($stream) {
        $data = json_decode((string) $stream, TRUE);
        return $data['mail'] === 'bino@princeton.edu';
      }))
      ->willReturn($response);

    $handler = function (RequestInterface $req, array $opts) use ($response) {
      return new FulfilledPromise($response);
    };

    $callable = $middleware();
    $promisedHandler = $callable($handler);
    $promise = $promisedHandler($request, []);
    $promise->wait();
  }

  /**
   * Tests that the middleware does not alter the response if mail is already present.
   */
  public function testMiddlewareDoesNotAlterWithMail() {
    $middleware = new AzureEasyAuthGuzzleMiddleware();

    $uri = $this->createMock(UriInterface::class);
    $uri->method('getHost')->willReturn('graph.microsoft.com');
    $uri->method('getPath')->willReturn('/v1.0/me');

    $request = $this->createMock(RequestInterface::class);
    $request->method('getUri')->willReturn($uri);

    $responseBody = Utils::streamFor(json_encode([
      'mail' => 'existing@princeton.edu',
      'userPrincipalName' => 'bino@princeton.edu',
    ]));

    $response = $this->createMock(ResponseInterface::class);
    $response->method('getBody')->willReturn($responseBody);
    $response->expects($this->never())->method('withBody');

    $handler = function (RequestInterface $req, array $opts) use ($response) {
      return new FulfilledPromise($response);
    };

    $callable = $middleware();
    $promisedHandler = $callable($handler);
    $promise = $promisedHandler($request, []);
    $promise->wait();
  }

  /**
   * Tests that the middleware does not alter non-Microsoft Graph requests.
   */
  public function testMiddlewareBypassesNonGraphRequests() {
    $middleware = new AzureEasyAuthGuzzleMiddleware();

    $uri = $this->createMock(UriInterface::class);
    $uri->method('getHost')->willReturn('api.github.com');
    $uri->method('getPath')->willReturn('/user');

    $request = $this->createMock(RequestInterface::class);
    $request->method('getUri')->willReturn($uri);

    $response = $this->createMock(ResponseInterface::class);
    $response->expects($this->never())->method('getBody');

    $handler = function (RequestInterface $req, array $opts) use ($response) {
      return new FulfilledPromise($response);
    };

    $callable = $middleware();
    $promisedHandler = $callable($handler);
    $promise = $promisedHandler($request, []);
    $promise->wait();
  }

}
