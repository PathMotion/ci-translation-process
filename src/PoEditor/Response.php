<?php
namespace PathMotion\CI\PoEditor;

use stdClass;

class Response
{

    /**
     * Response http code
     * @var int
     */
    private $httpCode = 200;

    /**
     * Raw response request
     * @var string
     */
    private $rawHeaders;

    /**
     * Parsed headers
     * @var array
     */
    private $headers = [];

    /**
     * Json parsed body
     * @var stdClass|null
     */
    private $jsonBody = null;

    /**
     * Raw response
     * @var string
     */
    private $rawBody;

    /**
     * Construct HTTP response object
     * This object cannot be created directly you must use static methods :
     *   - Response::fromCurlResource
     *   - Response::fromCurlResponse
     *
     * @param string $headers
     * @param string $body
     * @param integer $httpCode
     */
    private function __construct(string $headers, string $body, int $httpCode = 200)
    {
        $this->httpCode = $httpCode;
        $this->rawHeaders = $headers;
        $this->rawBody = $body;
    }

    /**
     * Construct response from CURL resource
     * /!\ resource have cannot be consume
     * /!\ Return transfer will be force
     * /!\ Return headers will be force
     * @param resource $curlResource
     * @return Response
     */
    public static function fromCurlResource($curlResource): Response
    {
        curl_setopt($curlResource, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curlResource, CURLOPT_HEADER, 1);

        $response = curl_exec($curlResource);
        $httpCode = curl_getinfo($curlResource, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($curlResource, CURLINFO_HEADER_SIZE);

        return Response::FromCurlResponse($response, $headerSize, $httpCode);
    }

    /**
     * Construct Response
     * @param string $body
     * @param integer $headerSize
     * @param integer $httpCode
     * @return Response
     */
    public static function fromCurlResponse(string $body, int $headerSize, int $httpCode = 200): Response
    {
        $headers = substr($body, 0, $headerSize);
        $body = substr($body, $headerSize);

        return new Response($headers, $body, $httpCode);
    }

    /**
     * Get response body
     * @return string
     */
    public function getBody(): string
    {
        return $this->rawBody;
    }

    /**
     * Get json parsed body
     * @return stdClass|null
     */
    public function getJsonBody(): ?stdClass
    {
        if (!empty($this->jsonBody)) {
            return $this->jsonBody;
        }
        $contentType = $this->getHeader('content-type');

        if (is_null($contentType) || strpos('application/json', $contentType) === -1) {
            return null;
        }
        $this->jsonBody = json_decode($this->getBody());
        return $this->jsonBody;
    }

    /**
     * Parse headers
     * @return array <string, string>
     */
    protected function parseHeaders(): array
    {
        $parsedHeaders = [];
        $headers = explode(PHP_EOL, $this->rawHeaders);

        foreach ($headers as $headerLine) {
            $headerParts = explode(':', $headerLine);
            if (count($headerParts) === 1) {
                continue;
            }
            $headerKey = mb_strtolower(trim(array_shift($headerParts)));
            $parsedHeaders[$headerKey] = trim(implode(':', $headerParts));
        }
        return $parsedHeaders;
    }

    /**
     * Return response headers
     * @return array <string, string>
     */
    public function getHeaders(): array
    {
        if (empty($this->headers)) {
            $this->headers = $this->parseHeaders();
        }
        return $this->headers;
    }

    /**
     * Check if an header exist
     * @param string $key
     * @return boolean
     */
    public function hasHeader(string $key): bool
    {
        $insensitiveKey = mb_strtolower($key);
        $headers = $this->getHeaders();

        return !empty($headers[$insensitiveKey]);
    }

    /**
     * Get one header value or null
     * @param string $key
     * @return string|null
     */
    public function getHeader(string $key): ?string
    {
        $insensitiveKey = mb_strtolower($key);
        $headers = $this->getHeaders();

        if (empty($headers[$insensitiveKey])) {
            return null;
        }
        return $headers[$insensitiveKey];
    }

    /**
     * Request has successefull response code
     * @return boolean
     */
    public function isSuccess(): bool
    {
        return $this->httpCode >= 200 && $this->httpCode <= 299;
    }

    /**
     * Http code
     * @return integer
     */
    public function getCode(): int
    {
        return $this->httpCode;
    }
}
