<?php

namespace Drupal\azure_easy_auth;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Guzzle middleware to map userPrincipalName to mail in Graph API responses.
 */
class AzureEasyAuthGuzzleMiddleware {

  /**
   * Invokes the middleware.
   */
  public function __invoke() {
    return function (callable $handler) {
      return function (RequestInterface $request, array $options) use ($handler) {
        return $handler($request, $options)->then(
          function (ResponseInterface $response) use ($request) {
            $uri = $request->getUri();
            // Check if this is a Microsoft Graph API request for the /me endpoint.
            if (strpos($uri->getHost(), 'graph.microsoft.com') !== FALSE && strpos($uri->getPath(), '/me') !== FALSE) {
              $body = (string) $response->getBody();
              $data = json_decode($body, TRUE);
              if (is_array($data) && empty($data['mail']) && !empty($data['userPrincipalName'])) {
                $data['mail'] = $data['userPrincipalName'];
                // Write back to the response body.
                if (class_exists('\GuzzleHttp\Psr7\Utils')) {
                  $new_body = \GuzzleHttp\Psr7\Utils::streamFor(json_encode($data));
                }
                else {
                  $new_body = \GuzzleHttp\Psr7\stream_for(json_encode($data));
                }
                $response = $response->withBody($new_body);
              }
            }
            return $response;
          }
        );
      };
    };
  }

}
