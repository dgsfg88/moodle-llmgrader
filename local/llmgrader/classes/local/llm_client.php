<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * LLM client for OpenAI-compatible Chat Completions API.
 *
 * @package   local_llmgrader
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_llmgrader\local;

/**
 * LLM client class.
 *
 * Communicates with OpenAI-compatible Chat Completions APIs.
 */
class llm_client {

    /** @var string API endpoint */
    protected string $endpoint;

    /** @var string|null API key */
    protected ?string $apikey;

    /** @var string Model name */
    protected string $model;

    /** @var float Temperature */
    protected float $temperature;

    /** @var int Max output tokens */
    protected int $maxtokens;

    /** @var int HTTP timeout in seconds */
    protected int $timeout;

    /** @var bool Whether endpoints on private/local networks are allowed */
    protected bool $allowlocalendpoints = false;

    /** @var string|null Last used model (for reporting) */
    protected ?string $lastmodel = null;

    /** @var int|null Last HTTP status code */
    protected ?int $lasthttpcode = null;

    /**
     * Constructor.
     *
     * @param object|null $config Optional configuration object. If null, uses global config.
     */
    public function __construct(?object $config = null) {
        if ($config) {
            $this->endpoint = $this->normalize_endpoint($config->api_endpoint ?: '');
            $this->apikey = $config->api_key ?: null;
            $this->model = $config->model ?: 'gpt-4o';
            $this->temperature = (float)($config->temperature ?? 0.2);
            $this->maxtokens = (int)($config->max_tokens ?? 2048);
            $this->timeout = (int)($config->http_timeout ?? 120);
            // This is a site-wide security setting and is never inherited from
            // course/quiz configuration.
            $this->allowlocalendpoints = (bool)get_config('local_llmgrader', 'allow_local_endpoints');
        } else {
            $this->endpoint = $this->normalize_endpoint(get_config('local_llmgrader', 'api_endpoint') ?: '');
            $this->apikey = get_config('local_llmgrader', 'api_key') ?: null;
            $this->model = get_config('local_llmgrader', 'model') ?: 'gpt-4o';
            $this->temperature = (float)(get_config('local_llmgrader', 'temperature') ?: 0.2);
            $this->maxtokens = (int)(get_config('local_llmgrader', 'max_tokens') ?: 2048);
            $this->timeout = (int)(get_config('local_llmgrader', 'http_timeout') ?: 120);
            $this->allowlocalendpoints = (bool)get_config('local_llmgrader', 'allow_local_endpoints');
        }

        if ($this->timeout <= 0) {
            $this->timeout = 120;
        }
        if ($this->maxtokens <= 0) {
            $this->maxtokens = 2048;
        }
    }

    /**
     * Normalize a base URL into a full Chat Completions endpoint.
     *
     * Administrators often enter a base URL such as http://localhost:8888/v1
     * instead of the full /v1/chat/completions path. Accept both forms.
     *
     * @param string $endpoint The configured endpoint.
     * @return string Normalized endpoint.
     */
    protected function normalize_endpoint(string $endpoint): string {
        $endpoint = trim($endpoint);
        if ($endpoint === '') {
            return '';
        }

        $path = (string)(parse_url($endpoint, PHP_URL_PATH) ?? '');
        $path = rtrim($path, '/');

        if ($path === '') {
            // Bare host: assume the standard /v1/chat/completions path.
            return rtrim($endpoint, '/') . '/v1/chat/completions';
        }

        if (substr($path, -15) === '/chat/completions') {
            return $endpoint;
        }

        if (substr($path, -3) === '/v1') {
            return rtrim($endpoint, '/') . '/chat/completions';
        }

        return $endpoint;
    }

    /**
     * Send a grading request to the LLM provider.
     *
     * @param array $context The grading context built by essay_grader.
     * @return string The raw response text from the LLM.
     * @throws \moodle_exception On HTTP failure or invalid response.
     */
    public function send_request(array $context): string {
        if (empty($this->endpoint)) {
            throw new \moodle_exception('noapiendpoint', 'local_llmgrader');
        }

        // Build the prompt using the prompt builder.
        $promptbuilder = new prompt_builder();
        $messages = $promptbuilder->build_messages($context);

        // Build the request payload. We intentionally do not send
        // response_format because several OpenAI-compatible servers reject it;
        // the JSON contract is enforced by the prompt instead.
        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => $this->temperature,
            'max_tokens' => $this->maxtokens,
        ];

        $data = $this->post_json($payload);
        $this->lastmodel = $this->model;

        if (!isset($data['choices'][0]['message']['content'])) {
            throw new \moodle_exception('invalidllmresponse', 'local_llmgrader', '', $this->truncate(json_encode($data)));
        }

        return $this->extract_content($data['choices'][0]['message']['content']);
    }

    /**
     * Perform a minimal connection test against the configured endpoint.
     *
     * @return string The model reply.
     * @throws \moodle_exception On HTTP failure or invalid response.
     */
    public function test_connection(): string {
        if (empty($this->endpoint)) {
            throw new \moodle_exception('noapiendpoint', 'local_llmgrader');
        }

        $data = $this->post_json([
            'model' => $this->model,
            'messages' => [
                ['role' => 'user', 'content' => 'Reply with exactly: CONNECTION_TEST_OK'],
            ],
            'max_tokens' => 20,
            'temperature' => 0,
        ]);
        $this->lastmodel = $this->model;

        if (!isset($data['choices'][0]['message']['content'])) {
            throw new \moodle_exception('invalidllmresponse', 'local_llmgrader', '', $this->truncate(json_encode($data)));
        }

        return $this->extract_content($data['choices'][0]['message']['content']);
    }

    /**
     * Send a JSON payload to the endpoint and decode the response.
     *
     * @param array $payload Request payload.
     * @return array Decoded response body.
     * @throws \moodle_exception
     */
    protected function post_json(array $payload): array {
        $body = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
        if ($body === false) {
            throw new \moodle_exception('invalidrequestpayload', 'local_llmgrader');
        }

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
        if (!empty($this->apikey)) {
            $headers['Authorization'] = 'Bearer ' . $this->apikey;
        }

        // Moodle blocks requests to localhost/private IP ranges by default
        // (SSRF protection). Local LLM servers are a legitimate use case, but
        // the administrator must explicitly opt in.
        $clientoptions = [];
        $securityhelper = new \core\files\curl_security_helper();
        if ($securityhelper->url_is_blocked($this->endpoint)) {
            if (!$this->allowlocalendpoints) {
                throw new \moodle_exception('endpointblocked', 'local_llmgrader', '', $this->endpoint);
            }
            $clientoptions['ignoresecurity'] = true;
        }

        try {
            // Moodle's Guzzle wrapper. \core\http\Client does not exist in
            // Moodle 5.0; the supported class is \core\http_client.
            $client = new \core\http_client($clientoptions);
            $response = $client->post($this->endpoint, [
                'headers' => $headers,
                'body' => $body,
                'timeout' => $this->timeout,
                'connect_timeout' => min(30, $this->timeout),
                'http_errors' => false,
            ]);
        } catch (\Throwable $e) {
            $this->lasthttpcode = null;
            throw new \moodle_exception('httppostfailed', 'local_llmgrader', '', $e->getMessage());
        }

        $this->lasthttpcode = (int)$response->getStatusCode();
        $rawbody = (string)$response->getBody();

        if ($this->lasthttpcode < 200 || $this->lasthttpcode >= 300) {
            $this->throw_http_error($this->lasthttpcode, $rawbody);
        }

        $data = json_decode($rawbody, true);
        if (!is_array($data)) {
            throw new \moodle_exception('invalidllmresponse', 'local_llmgrader', '', $this->truncate($rawbody));
        }

        return $data;
    }

    /**
     * Throw the appropriate exception for an HTTP error.
     *
     * @param int $code HTTP status code.
     * @param string $body Response body.
     * @throws \moodle_exception
     */
    protected function throw_http_error(int $code, string $body): void {
        $snippet = $this->truncate($body);

        if ($code === 429) {
            throw new \moodle_exception('llmratelimited', 'local_llmgrader', '', "HTTP 429: {$snippet}");
        }

        if ($code >= 500) {
            throw new \moodle_exception('llmserviceunavailable', 'local_llmgrader', '', "HTTP {$code}: {$snippet}");
        }

        throw new \moodle_exception('llmrequestfailed', 'local_llmgrader', '', "HTTP {$code}: {$snippet}");
    }

    /**
     * Extract text content from an OpenAI-compatible message content value.
     *
     * Some providers return a string, others an array of content parts.
     *
     * @param mixed $content Message content.
     * @return string
     */
    protected function extract_content($content): string {
        if (is_string($content)) {
            return $content;
        }

        if (is_array($content)) {
            $parts = [];
            foreach ($content as $part) {
                if (is_string($part)) {
                    $parts[] = $part;
                } else if (is_array($part) && isset($part['text'])) {
                    $parts[] = (string)$part['text'];
                }
            }
            return implode("\n", $parts);
        }

        return (string)$content;
    }

    /**
     * Truncate a string for safe inclusion in error messages.
     *
     * @param string $value Value to truncate.
     * @return string
     */
    protected function truncate(string $value): string {
        return \core_text::substr($value, 0, 500);
    }

    /**
     * Get the model name used for the last request.
     *
     * @return string|null
     */
    public function get_model(): ?string {
        return $this->lastmodel;
    }

    /**
     * Get the HTTP status code of the last request.
     *
     * @return int|null
     */
    public function get_last_http_code(): ?int {
        return $this->lasthttpcode;
    }
}
