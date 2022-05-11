<?php
namespace PathMotion\CI\PoEditor;

use Exception;
use PathMotion\CI\PoEditor\Exception\ApiErrorException;
use PathMotion\CI\PoEditor\Exception\IOException;
use PathMotion\CI\PoEditor\Exception\UnexpectedBodyResponseException;
use PathMotion\CI\Utils\TranslationFile;
use stdClass;

class Language
{

    /**
     * Language name
     * @var string
     */
    private $name;

    /**
     * Language code
     * @var string
     */
    private $code;

    /**
     * Translation count
     * @var int
     */
    private $translationsCount;

    /**
     * Translated percentage
     * @var float
     */
    private $percentage;

    /**
     * Last language update
     * @var string
     */
    private $updated;

    /**
     * Po Editor API token
     * @var Project
     */
    private $project;

    /**
     * IETF ISO639.1 format
     * format: language : en
     * @var integer
     */
    const FORMAT_ISO_639_1 = 0;

    /**
     * IETF POSIX format
     * format: language_COUNTRY: en_GB
     * @var integer
     */
    const FORMAT_POSIX = 1;

    /**
     * Pathmotion ISO format
     * format: language_COUNTRY: en-GB
     * @var integer
     */
    const PATHMOTION_FORMAT_ISO = 3;

    /**
     * Po Editor Language constructor
     * @param stdClass $language
     * @param Project $project
     */
    public function __construct(stdClass $language, Project $project)
    {
        $this->name = !empty($language->name)?$language->name:'';
        $this->code = !empty($language->code)?$language->code:'';
        $this->translationsCount = !empty($language->translations)?$language->translations:0;
        $this->percentage = !empty($language->percentage)?$language->percentage:0;
        $this->updated = !empty($language->updated)?$language->updated:'';
        $this->project = $project;
    }

    /**
     * Format language code
     * @throws Exception invalid language code
     * @param int $separator
     * @return string
     */
    public function formatCode(int $format = self::FORMAT_POSIX): string
    {
        if (preg_match('/^([a-zA-Z]+)-([a-zA-Z]+)$/', $this->code, $matches) === 1) {
            $language = $matches[1];
            $country = mb_strtoupper($matches[2]);
        } else {
            $language = mb_strtolower($this->code);
            $country = mb_strtoupper($this->code);
        }

        if (self::FORMAT_ISO_639_1 === $format) {
            return $language;
        }
        if (self::FORMAT_POSIX === $format) {
            return sprintf('%s%s%s', $language, '_', $country);
        }
        if (self::PATHMOTION_FORMAT_ISO === $format) {
            return sprintf('%s%s%s', $language, '-', $country);
        }
        throw new Exception('invalid language code format');
    }

    /**
     * Get export link from PoEditor API
     * @throws ApiErrorException
     * @throws UnexpectedBodyResponseException
     * @param string $type
     * @return string
     */
    protected function getExportLink(string $type): string
    {
        $params = [
            'language' => $this->code,
            'type' => $type
        ];
        $apiResponse = $this->project->manualRequest('/projects/export', $params);

        if (!$apiResponse->isSuccess()) {
            throw new ApiErrorException('', $apiResponse->getCode());
        }
        $body = $apiResponse->getJsonBody();
        if (empty($body->result->url)) {
            if (!empty($body->response->code)) {
                throw new ApiErrorException($body->response->message, $body->response->code);
            }
            return new UnexpectedBodyResponseException();
        }
        return $body->result->url;
    }

    /**
     * Open `$filepath` for writing only;
     * zplace the file pointer at the beginning of the file and truncate the file to zero length.
     * If the file does not exist, attempt to create it.
     * @throws IOException
     * @param string $filePath
     * @return resource
     */
    private function createIfNeededAndOpenFile(string $filePath)
    {
        $dirname = dirname($filePath);
        $dirExist = is_dir($dirname);
        if (!$dirExist) {
            $dirExist = mkdir($dirname, 0755, true);
        }
        if (!$dirExist) {
            throw new IOException('Directory cannot be created');
        }
        $resource = fopen($filePath, 'w');
        if (!$resource) {
            throw new IOException('Fail to open the file');
        }
        return $resource;
    }

    /**
     * Export translation to po/mo file
     * @throws ApiErrorException
     * @throws UnexpectedBodyResponseException
     * @param string $type file type (po/mo)
     * @param string $outputFilePath
     * @return TranslationFile return output file
     */
    public function exportTo(string $type, string $outputFilePath): TranslationFile
    {
        $file = $this->createIfNeededAndOpenFile($outputFilePath);
        try {
            $fileUrl = $this->getExportLink($type);
        } catch (ApiErrorException|UnexpectedBodyResponseException $error) {
            // close openned file
            fclose($file);
            throw $error;
        }
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FILE, $file);
        curl_setopt($curl, CURLOPT_URL, $fileUrl);
        curl_exec($curl);
        curl_close($curl);
        $meta = stream_get_meta_data($file);
        fclose($file);

        return new TranslationFile($meta['uri']);
    }
}
