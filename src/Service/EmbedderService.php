<?php
namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class EmbedderService
{
    private HttpClientInterface $http;
    private string $apiKey;
    private string $endpoint = 'https://api.openai.com/v1/embeddings';
    private string $model = 'text-embedding-3-small';

    public function getModel(): string
    {
        return $this->model;
    }

    public function __construct(HttpClientInterface $http, string $openAiApiKey)
    {
        $this->http = $http;
        $this->apiKey = $openAiApiKey;
    }

    public function embed(string $text): array
    {
        $response = $this->http->request('POST', $this->endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
            ],
            'json' => [
                'model' => $this->model,
                'input' => $text,
            ],
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException(sprintf(
                'OpenAI embedding request failed (status %d): %s',
                $response->getStatusCode(),
                $response->getContent(false)
            ));
        }
        $payload = $response->toArray();
        if (!isset($payload['data'][0]['embedding']) || !is_array($payload['data'][0]['embedding'])) {
            throw new \RuntimeException('Unexpected OpenAI response: ' . json_encode($payload));
        }
        return $payload['data'][0]['embedding'];
    }
}
