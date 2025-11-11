<?php

namespace Drupal\myeventlane_ai\Service;

use GuzzleHttp\Client;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

class AiClient {

  protected $http;
  protected $logger;
  protected $apiKey;

  public function __construct($api_key, LoggerChannelFactoryInterface $logger_factory) {
    $this->http = new Client();
    $this->apiKey = $api_key;
    $this->logger = $logger_factory->get('myeventlane_ai');
  }

  /**
   * Call GPT-5 (through ngrok.ai or OpenAI).
   */
  public function chat(string $prompt, string $model = 'openai-gpt5'): string {
    try {
      $res = $this->http->post('https://api.ngrok.com/v1/ai/chat/completions', [
        'headers' => [
          'Authorization' => 'Bearer ' . $this->apiKey,
          'Content-Type'  => 'application/json',
          'Ngrok-Version' => '2',
        ],
        'json' => [
          'model' => $model,
          'messages' => [
            ['role' => 'user', 'content' => $prompt],
          ],
        ],
      ]);
      $data = json_decode((string) $res->getBody(), TRUE);
      return $data['choices'][0]['message']['content'] ?? '[No response]';
    }
    catch (\Exception $e) {
      $this->logger->error('AI request failed: @msg', ['@msg' => $e->getMessage()]);
      return '[Error: ' . $e->getMessage() . ']';
    }
  }
}