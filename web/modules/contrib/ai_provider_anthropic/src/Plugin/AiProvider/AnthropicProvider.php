<?php

namespace Drupal\ai_provider_anthropic\Plugin\AiProvider;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\AiProvider;
use Drupal\ai\Base\AiProviderClientBase;
use Drupal\ai\Enum\AiModelCapability;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatInterface;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionOutput;
use Drupal\ai\Traits\OperationType\ChatTrait;
use OpenAI\Client;
use Symfony\Component\Yaml\Yaml;

/**
 * Plugin implementation of the 'anthropic' provider.
 */
#[AiProvider(
  id: 'anthropic',
  label: new TranslatableMarkup('Anthropic'),
)]
class AnthropicProvider extends AiProviderClientBase implements
  ChatInterface {

  use ChatTrait;

  /**
   * The Anthropic Client.
   *
   * @var \OpenAI\Client|null
   */
  protected $client;

  /**
   * API Key.
   *
   * @var string
   */
  protected string $apiKey = '';

  /**
   * Run moderation call, before a normal call.
   *
   * @var bool
   */
  protected bool $moderation = TRUE;

  /**
   * {@inheritdoc}
   */
  public function getConfiguredModels(?string $operation_type = NULL, array $capabilities = []): array {
    // No complex JSON support.
    if (in_array(AiModelCapability::ChatJsonOutput, $capabilities)) {
      return [
        'claude-3-7-sonnet-latest' => 'Claude 3.7 Sonnet',
        'claude-3-5-sonnet-latest' => 'Claude 3.5 Sonnet',
        'claude-3-5-haiku-latest' => 'Claude 3.5 Haiku',
      ];
    }
    // Anthropic hard codes :/.
    if ($operation_type == 'chat') {
      return [
        'claude-3-7-sonnet-latest' => 'Claude 3.7 Sonnet',
        'claude-3-5-sonnet-latest' => 'Claude 3.5 Sonnet',
        'claude-3-5-haiku-latest' => 'Claude 3.5 Haiku',
        'claude-3-opus-latest' => 'Claude 3 Opus',
        'claude-3-sonnet-latest' => 'Claude 3 Sonnet',
        'claude-3-haiku-latest' => 'Claude 3 Haiku',
      ];
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function isUsable(?string $operation_type = NULL, array $capabilities = []): bool {
    // If its not configured, it is not usable.
    if (!$this->apiKey && !$this->getConfig()->get('api_key')) {
      return FALSE;
    }
    // If its one of the bundles that Anthropic supports its usable.
    if ($operation_type) {
      return in_array($operation_type, $this->getSupportedOperationTypes());
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedOperationTypes(): array {
    return [
      'chat',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(): ImmutableConfig {
    return $this->configFactory->get('ai_provider_anthropic.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function getApiDefinition(): array {
    // Load the configuration.
    $definition = Yaml::parseFile($this->moduleHandler->getModule('ai_provider_anthropic')->getPath() . '/definitions/api_defaults.yml');
    return $definition;
  }

  /**
   * {@inheritdoc}
   */
  public function getModelSettings(string $model_id, array $generalConfig = []): array {
    return $generalConfig;
  }

  /**
   * {@inheritdoc}
   */
  public function setAuthentication(mixed $authentication): void {
    // Set the new API key and reset the client.
    $this->apiKey = $authentication;
    $this->client = NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function chat(array|string|ChatInput $input, string $model_id, array $tags = []): ChatOutput {
    $this->loadClient();
    // Normalize the input if needed.
    $chat_input = $input;
    if ($input instanceof ChatInput) {
      $chat_input = [];
      // Add a system role if wanted.
      if ($this->chatSystemRole) {
        $chat_input[] = [
          'role' => 'system',
          'content' => $this->chatSystemRole,
        ];
      }
      /** @var \Drupal\ai\OperationType\Chat\ChatMessage $message */
      foreach ($input->getMessages() as $message) {
        $content = [
          [
            'type' => 'text',
            'text' => $message->getText(),
          ],
        ];
        if (count($message->getImages())) {
          foreach ($message->getImages() as $image) {
            $content[] = [
              'type' => 'image_url',
              'image_url' => [
                'url' => $image->getAsBase64EncodedString(),
              ],
            ];
          }
        }
        $new_message = [
          'role' => $message->getRole(),
          'content' => $content,
        ];

        if ($message->getRole() == 'tool') {
          $new_message = [
            'role' => 'tool',
            'content' => $message->getText(),
          ];
        }

        // If its a tools response.
        if ($message->getToolsId()) {
          $new_message['tool_call_id'] = $message->getToolsId();
        }

        // If we want the results from some older tools call.
        if ($message->getTools()) {
          $new_message['tool_calls'] = $message->getRenderedTools();
        }

        $chat_input[] = $new_message;
      }
    }

    $payload = [
      'model' => $model_id,
      'messages' => $chat_input,
    ] + $this->configuration;
    // If we want to add tools to the input.
    if (method_exists($input, 'getChatTools') && $input->getChatTools()) {
      $payload['tools'] = $input->getChatTools()->renderToolsArray();
      foreach ($payload['tools'] as $key => $tool) {
        $payload['tools'][$key]['function']['strict'] = FALSE;
      }
    }
    // Check for structured json schemas.
    if (method_exists($input, 'getChatStructuredJsonSchema') && $input->getChatStructuredJsonSchema()) {
      $payload['response_format'] = [
        'type' => 'json_schema',
        'json_schema' => $input->getChatStructuredJsonSchema(),
      ];
    }
    try {
      $response = $this->client->chat()->create($payload)->toArray();
      // If tools are generated.
      $tools = [];
      if (!empty($response['choices'][0]['message']['tool_calls'])) {
        foreach ($response['choices'][0]['message']['tool_calls'] as $tool) {
          $arguments = Json::decode($tool['function']['arguments']);
          $tools[] = new ToolsFunctionOutput($input->getChatTools()->getFunctionByName($tool['function']['name']), $tool['id'], $arguments);
        }
      }
      $message = new ChatMessage($response['choices'][0]['message']['role'], $response['choices'][0]['message']['content'] ?? "", []);
      if (!empty($tools)) {
        $message->setTools($tools);
      }
    }
    catch (\Exception $e) {
      throw $e;
    }

    return new ChatOutput($message, $response, []);
  }

  /**
   * {@inheritdoc}
   */
  public function getSetupData(): array {
    return [
      'key_config_name' => 'api_key',
      'default_models' => [
        'chat' => 'claude-3-5-sonnet-latest',
        'chat_with_image_vision' => 'claude-3-5-sonnet-latest',
        'chat_with_complex_json' => 'claude-3-5-sonnet-latest',
        'chat_with_tools' => 'claude-3-5-sonnet-latest',
        'chat_with_structured_response' => 'claude-3-5-sonnet-latest',
      ],
    ];
  }

  /**
   * Enables moderation response, for all next coming responses.
   */
  public function enableModeration(): void {
    $this->moderation = TRUE;
  }

  /**
   * Disables moderation response, for all next coming responses.
   */
  public function disableModeration(): void {
    $this->moderation = FALSE;
  }

  /**
   * Gets the raw client.
   *
   * @param string $api_key
   *   If the API key should be hot swapped.
   *
   * @return \OpenAI\Client
   *   The OpenAI Client for Anthropic
   */
  public function getClient(string $api_key = ''): Client {
    if ($api_key) {
      $this->setAuthentication($api_key);
    }
    $this->loadClient();
    return $this->client;
  }

  /**
   * Loads the Anthropic Client with authentication if not initialized.
   */
  protected function loadClient(): void {
    if (!$this->client) {
      if (!$this->apiKey) {
        $this->setAuthentication($this->loadApiKey());
      }
      $host = 'https://api.anthropic.com/v1/';
      $client = \OpenAI::factory()
        ->withApiKey($this->apiKey)
        ->withBaseUri($host)
        ->withHttpClient($this->httpClient);

      // If the configuration has a custom endpoint, we set it.
      if (!empty($this->getConfig()->get('host'))) {
        $client->withBaseUri($this->getConfig()->get('host'));
      }

      $this->client = $client->make();
    }
  }

}
