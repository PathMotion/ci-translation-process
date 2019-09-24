<?php
namespace PathMotion\CI\PoEditor;

use PathMotion\CI\PoEditor\Exception\ApiErrorException;
use PathMotion\CI\PoEditor\Exception\UnexpectedBodyResponseException;
use stdClass;

class Project
{

    /**
     * Project id
     * @var int
     */
    private $id;

    /**
     * Project name
     * @var string
     */
    private $name;

    /**
     * Project is public
     * @var bool
     */
    private $public;

    /**
     * Project is open
     * @var bool
     */
    private $open;

    /**
     * Project creation date
     * @var string
     */
    private $created;

    /**
     * PoEditor API client
     * @var Client
     */
    private $client;

    /**
     * Construct Po Editor project instance representation
     * @param integer $id
     * @param stdClass $project
     * @param Client $client
     */
    public function __construct(int $id, stdClass $project, Client $client)
    {
        $this->id = $id;
        $this->name = !empty($project->name)?$project->name:'';
        $this->public = !empty($project->public) && $project->public === 1;
        $this->open = !empty($project->open) && $project->open === 1;
        $this->created = !empty($project->created)?$project->created:'';
        $this->client = $client;
    }

    /**
     * Get project name
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Perform manual request to the PO editor API
     * With the project id automaticly injected to the request
     * @param string $endpoint
     * @param array $params
     * @return Response
     */
    public function manualRequest(string $endpoint = '/', $params = []): Response
    {
        $params = array_merge($params, ['id' => $this->id]);
        return $this->client->manualRequest($endpoint, $params);
    }

    /**
     * Return project languages list
     * @return array language
     */
    public function languagesList(): array
    {
        $apiResponse = $this->manualRequest('/languages/list');
        if (!$apiResponse->isSuccess()) {
            throw new ApiErrorException('', $apiResponse->getCode());
        }
        $body = $apiResponse->getJsonBody();
        if (empty($body->result->languages) || !is_array($body->result->languages)) {
            if (!empty($body->response->code)) {
                throw new ApiErrorException($body->response->message, $body->response->code);
            }
            return new UnexpectedBodyResponseException();
        }
        $languages = [];
        foreach ($body->result->languages as $language) {
            $languages[] = new Language($language, $this);
        }
        return $languages;
    }
}
