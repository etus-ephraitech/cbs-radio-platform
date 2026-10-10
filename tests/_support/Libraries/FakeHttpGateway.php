<?php

namespace Tests\Support\Libraries;

use CodeIgniter\HTTP\Response;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\HTTP\URI;
use CodeIgniter\Test\Mock\MockCURLRequest;
use Config\App;
use Config\Services;
use RuntimeException;

/**
 * Stands in for the HTTP client the application uses to talk to outside services (Services::curlrequest(): the
 * SMS gateway, gift vendors). Records every request and answers with a chosen status and body, or fails the way
 * a network error would. Nothing ever leaves the machine.
 *
 *     $http = FakeHttpGateway::install();                              // answers 200 with {}
 *     $http = FakeHttpGateway::install(500);                           // the other side refuses
 *     $http = FakeHttpGateway::install(body: '{"order_id": 77}');      // a particular answer
 *     $http = FakeHttpGateway::install(failWith: 'Connection refused'); // cannot connect
 */
class FakeHttpGateway extends MockCURLRequest
{
    /** @var array<int, array{method: string, url: string, options: array<string, mixed>}> */
    public array $calls = [];

    // Named so that they cannot collide with properties of the framework's own HTTP classes (which has $body).
    public int $answerStatus  = 200;
    public string $answerBody = '{}';
    public ?string $failWith  = null;

    public static function install(int $status = 200, string $body = '{}', ?string $failWith = null): self
    {
        $config = config(App::class);
        $fake   = new self($config, new URI(), new Response($config), []);

        $fake->answerStatus = $status;
        $fake->answerBody   = $body;
        $fake->failWith     = $failWith;

        Services::injectMock('curlrequest', $fake);

        return $fake;
    }

    public function request($method, string $url, array $options = []): ResponseInterface
    {
        $this->calls[] = ['method' => strtoupper((string) $method), 'url' => $url, 'options' => $options];

        if ($this->failWith !== null) {
            throw new RuntimeException($this->failWith);
        }

        return (new Response(config(App::class)))->setStatusCode($this->answerStatus)->setBody($this->answerBody);
    }

    /**
     * The form fields or JSON body of the most recent request.
     *
     * @return array<string, mixed>
     */
    public function lastPayload(): array
    {
        $options = $this->calls[array_key_last($this->calls)]['options'] ?? [];

        return $options['form_params'] ?? $options['json'] ?? [];
    }
}
