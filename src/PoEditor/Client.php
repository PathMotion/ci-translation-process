<?php
namespace PathMotion\CI\PoEditor;

use PathMotion\CI\PoEditor\Exception\ApiErrorException;
use PathMotion\CI\PoEditor\Exception\UnexpectedBodyResponseException;

class Client
{

    private const _API_DEFAULT_VERSION_ = 'v2';

    private const _API_HOST_ = 'https://api.poeditor.com/%s';

    /**
     * Api Version
     * @var string
     */
    private $version = null;

    /**
     * Po Editor API token
     * @var string
     */
    private $apiToken;

    /**
     * construct Po Editor APO client instance
     * @param string $apiToken
     */
    public function __construct(string $apiToken)
    {
        $this->setApiVersion();
        $this->apiToken = $apiToken;
    }

    /**
     * Set Api version
     *
     * @param string|null $version - if it is not provided self::_API_DEFAULT_VERSION_ will be defined
     * @return Client
     */
    public function setApiVersion(string $version = null): Client
    {
        if (is_null($version)) {
            $version = self::_API_DEFAULT_VERSION_;
        }
        $this->version = $version;
        return $this;
    }

    /**
     * Format complete API url
     * @param string $endpoint API endpoint (default must be '/')
     * @return string
     */
    protected function getUrl(string $endpoint = '/'): string
    {
        $url = sprintf(self::_API_HOST_, $this->version);
        if (strpos($endpoint, '/') !== 0) {
            $endpoint = '/' . $endpoint;
        }
        return $url . $endpoint;
    }

    /**
     * Get all projects assoc to your api key
     * @throws ApiErrorException
     * @throws UnexpectedBodyResponseException
     * @return array <int, Project>
     */
    public function listProject(): array
    {
        $apiResponse = $this->manualRequest('/projects/list');

        if (!$apiResponse->isSuccess()) {
            throw new ApiErrorException('', $apiResponse->getCode());
        }
        $body = $apiResponse->getJsonBody();
        if (empty($body->result->projects)) {
            throw new UnexpectedBodyResponseException;
        }
        $projects = [];
        foreach ($body->result->projects as $project) {
            $projects[$project->id] = new Project($project->id, $project, $this);
        }
        return $projects;
    }

    /**
     * Get specific project by id
     * @throws ApiErrorException
     * @throws UnexpectedBodyResponseException
     * @param integer $id
     * @return Project|null
     */
    public function getProject(int $id): ?Project
    {
        $apiResponse = $this->manualRequest('/projects/view', ['id' => $id]);
        if (!$apiResponse->isSuccess()) {
            throw new ApiErrorException('', $apiResponse->getCode());
        }
        $body = $apiResponse->getJsonBody();
        if (empty($body->result->project)) {
            if (!empty($body->response->code)) {
                throw new ApiErrorException($body->response->message, $body->response->code);
            }
            throw new UnexpectedBodyResponseException;
        }
        $project = $body->result->project;
        return new Project($project->id, $project, $this);
    }

    /**
     * Manually execute http request to po editor API
     * @param string $endpoint
     * @param array $params
     * @return Response
     */
    public function manualRequest(string $endpoint = '/', $params = []): Response
    {
        $params = array_merge($params, ['api_token' => $this->apiToken]);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->getUrl($endpoint));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);

        return Response::fromCurlResource($ch);
    }
}
