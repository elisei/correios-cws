<?php
/**
 * O2TI Sigep Web Carrier.
 *
 * Copyright © 2025 O2TI. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

namespace O2TI\SigepWebCarrier\Gateway\Http\Client;

use Laminas\Http\ClientFactory;
use Laminas\Http\Request;
use Laminas\Http\Client\Adapter\Curl;
use Laminas\Http\Header\Authorization;
use Magento\Framework\Exception\InvalidArgumentException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\LaminasClient;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Filesystem\Driver\File;
use Psr\Log\LoggerInterface;
use O2TI\SigepWebCarrier\Gateway\Config\Config;

/**
 * Class ApiClient
 * Handles API communication with Correios services.
 */
class ApiClient
{
    private const PROTECTED_KEYS = ['username', 'password', 'token', 'Authorization', 'posting_card'];
    private const CONTENT_TYPE_JSON = 'application/json';

    /**
     * @var ClientFactory
     */
    private $httpClientFactory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var array
     */
    private $auth;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var string|null
     */
    private $rawResponse = null;

    /**
     * @var File
     */
    private $file;

    /**
     * @param ClientFactory $httpClientFactory
     * @param LoggerInterface $logger
     * @param Json $json
     * @param Config $config
     * @param File $file
     * @param array $auth
     */
    public function __construct(
        ClientFactory $httpClientFactory,
        LoggerInterface $logger,
        Json $json,
        Config $config,
        File $file,
        array $auth = []
    ) {
        $this->httpClientFactory = $httpClientFactory;
        $this->logger = $logger;
        $this->json = $json;
        $this->config = $config;
        $this->file = $file;
        $this->auth = $auth;
    }

    /**
     * Set Auth Data
     *
     * @param array $auth
     * @return void
     */
    public function setAuth(array $auth): void
    {
        $this->auth = $auth;
    }

    /**
     * Get raw response
     *
     * @return string|null
     */
    public function getRawResponse()
    {
        return $this->rawResponse;
    }

    /**
     * Send API Request
     *
     * @param string $uri
     * @param array $headers
     * @param array $request
     * @param string $method
     * @param bool $expectNonJson
     * @return array
     * @throws LocalizedException
     */
    public function request(
        string $uri,
        array $headers,
        array $request = [],
        string $method = 'POST',
        bool $expectNonJson = false
    ): array {
        try {
            $client = $this->httpClientFactory->create();
            $client->setUri($uri);

            $headers = $this->prepareHeaders($headers, $method);
            $client->setHeaders($headers);
            $client->setMethod($method);

            if ($method === Request::METHOD_POST) {
                $client->setRawBody($this->json->serialize($request));
            }

            $response = $client->send();
            $responseBody = $response->getBody();
            $this->rawResponse = $responseBody;

            if ($expectNonJson) {
                if ($response->getStatusCode() >= 400) {
                    $this->logApiInteraction($uri, $headers, $request, ['brute_error' => $responseBody]);
                    throw new LocalizedException(__('Invalid JSON was returned by the Correios'));
                }
                
                try {
                    $jsonData = $this->json->unserialize($responseBody);
                    $this->logApiInteraction($uri, $headers, $request, $jsonData);
                    return $jsonData;
                } catch (\InvalidArgumentException $e) {
                    $contentType = null;
                    if ($response->getHeaders()->has('Content-Type')) {
                        $contentType = $response->getHeaders()->get('Content-Type')->getFieldValue();
                    }
                    
                    $isPdf = false;
                    if ($contentType && strpos($contentType, 'application/pdf') !== false) {
                        $isPdf = true;
                    } elseif (substr($responseBody, 0, 5) === '%PDF-') {
                        $isPdf = true;
                    }
                    
                    $result = [
                        'raw' => true,
                        'isPdf' => $isPdf,
                        'content_type' => $contentType,
                        'status_code' => $response->getStatusCode()
                    ];
                    
                    if ($this->config->hasDebug()) {
                        $this->logger->debug(
                            'Correios API Binary Response',
                            [
                                'uri' => $uri,
                                'status_code' => $response->getStatusCode(),
                                'content_type' => $contentType,
                                'response_length' => strlen($responseBody),
                                'is_pdf' => $isPdf ? 'Yes' : 'No'
                            ]
                        );
                    }
                    
                    return $result;
                }
            }
            
            $data = $this->json->unserialize($responseBody);

            $this->logApiInteraction($uri, $headers, $request, $data);

            return $data;
        } catch (InvalidArgumentException $exc) {
            $this->logApiError($uri, $headers, $request, $exc->getMessage());
            
            if ($expectNonJson && $this->rawResponse) {
                // Verifica se é um erro de sincronização
                if (strpos($this->rawResponse, 'PPN-291') !== false &&
                    strpos($this->rawResponse, 'Recibo em sincronização') !== false) {
                    return [
                        'mensagem' => $this->rawResponse,
                        'status' => 'synchronizing'
                    ];
                }
                
                if (substr($this->rawResponse, 0, 5) === '%PDF-') {
                    return [
                        'raw' => true,
                        'isPdf' => true,
                        'content' => $this->rawResponse
                    ];
                }
                
                return [
                    'raw' => true,
                    'error' => true,
                    'message' => $exc->getMessage(),
                    'content' => $this->rawResponse
                ];
            }
            
            throw new LocalizedException(__('Invalid JSON was returned by the Correios'));
        }
    }

    /**
     * Upload a file to the API
     *
     * @param string $uri
     * @param array $headers
     * @param string $typeInput
     * @param string $fileName
     * @param string $filePath
     * @return array
     * @throws LocalizedException
     */
    public function uploadFile(
        string $uri,
        array $headers,
        string $typeInput,
        string $fileName,
        string $filePath
    ): array {
        try {
            if (!$this->file->isExists($filePath)) {
                throw new LocalizedException(__('File does not exist: %1', $filePath));
            }
            
            $fileContent = $this->file->fileGetContents($filePath);
            $fileInfo = $this->getFileInfo($filePath, $fileContent);
            
            $client = $this->httpClientFactory->create();
            $client->setUri($uri);
            $client->setMethod(Request::METHOD_POST);
            
            // Configurar headers para multipart/form-data
            if (isset($headers['Content-Type'])) {
                unset($headers['Content-Type']);
            }
            $headers['Accept'] = self::CONTENT_TYPE_JSON;
            $client->setHeaders($headers);
            
            $client->setFileUpload(
                $filePath,
                'arquivo',
                null,
                'application/json'
            );
            
            if ($this->config->hasDebug()) {
                $this->logger->debug(
                    'Correios API File Upload Request Configuration',
                    [
                        'request_method' => 'POST',
                        'multipart' => true,
                        'form_field_name' => 'arquivo',
                        'file_path' => $filePath,
                        'file_name' => $fileName,
                        'content_type' => 'application/json'
                    ]
                );
                $this->logFileUploadRequest($uri, $headers, 'arquivo', $fileName, $fileInfo);
            }
            
            $response = $client->send();
            $responseBody = $response->getBody();
            $this->rawResponse = $responseBody;
            $data = $this->json->unserialize($responseBody);
            
            if ($this->config->hasDebug()) {
                $this->logFileUploadResponse($response->getStatusCode(), $data);
            }
            
            return $data;
        } catch (InvalidArgumentException $e) {
            $this->logApiError($uri, $headers, ['file_name' => $fileName], $e->getMessage());
            throw new LocalizedException(__('Invalid JSON was returned by the Correios API'));
        } catch (\Exception $e) {
            $this->logApiError($uri, $headers, ['file_name' => $fileName], $e->getMessage());
            throw new LocalizedException(__('Error uploading file to Correios API: %1', $e->getMessage()));
        }
    }

    /**
     * Get detailed file information for debugging
     *
     * @param string $filePath
     * @param string $fileContent
     * @return array
     */
    private function getFileInfo(string $filePath, string $fileContent): array
    {
        $fileSize = $this->file->stat($filePath)['size'] ?? 0;
        $mimeType = $this->getMimeType($filePath, $fileContent);
        $isJson = $this->isJsonContent($fileContent);
        $preview = substr($fileContent, 0, 1000);
        
        $jsonData = null;
        $jsonError = null;
        if ($isJson) {
            try {
                $jsonData = $this->json->unserialize($fileContent);
            } catch (\Exception $e) {
                $jsonError = $e->getMessage();
            }
        }
        
        return [
            'file_path' => $filePath,
            'file_size' => $fileSize,
            'mime_type' => $mimeType,
            'is_json' => $isJson,
            'content_preview' => $preview,
            'json_structure' => $isJson && $jsonData ? array_keys($jsonData) : null,
            'json_error' => $jsonError
        ];
    }
    
    /**
     * Try to determine the mime type from content or extension
     *
     * @param string $filePath
     * @param string $fileContent
     * @return string
     */
    private function getMimeType(string $filePath, string $fileContent): string
    {
        $fileInfo = $this->file->getPathInfo($originalName);
        $extension = $fileInfo['extension'] ?? 'pdf';
        $mimeTypes = [
            'json' => 'application/json',
            'pdf' => 'application/pdf',
            'xml' => 'application/xml',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'zip' => 'application/zip'
        ];
        
        if (isset($mimeTypes[$extension])) {
            return $mimeTypes[$extension];
        }
        
        // Try to detect from content
        if (substr($fileContent, 0, 5) === '%PDF-') {
            return 'application/pdf';
        }
        
        if ($this->isJsonContent($fileContent)) {
            return 'application/json';
        }
        
        if (substr($fileContent, 0, 5) === '<?xml') {
            return 'application/xml';
        }
        
        // Default when unable to determine
        return 'application/octet-stream';
    }
    
    /**
     * Check if content is valid JSON
     *
     * @param string $content
     * @return bool
     */
    private function isJsonContent(string $content): bool
    {
        if (empty($content)) {
            return false;
        }
        
        json_decode($content);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Log detailed file upload request information
     *
     * @param string $uri
     * @param array $headers
     * @param string $typeInput
     * @param string $fileName
     * @param array $fileInfo
     * @return void
     */
    private function logFileUploadRequest(
        string $uri,
        array $headers,
        string $typeInput,
        string $fileName,
        array $fileInfo
    ): void {
        $this->logger->debug(
            'Correios API File Upload Request',
            [
                'url' => $uri,
                'headers' => $this->filterDebugData($headers),
                'upload_field_name' => $typeInput,
                'file_name' => $fileName,
                'file_info' => $fileInfo,
                'request_time' => (new \DateTime())->format('Y-m-d H:i:s')
            ]
        );
    }
    
    /**
     * Log detailed file upload response information
     *
     * @param int $statusCode
     * @param array $responseData
     * @return void
     */
    private function logFileUploadResponse(int $statusCode, array $responseData): void
    {
        $this->logger->debug(
            'Correios API File Upload Response',
            [
                'status_code' => $statusCode,
                'response_data' => $this->filterDebugData($responseData),
                'response_time' => (new \DateTime())->format('Y-m-d H:i:s')
            ]
        );
    }

    /**
     * Prepare request headers
     *
     * @param array $headers
     * @param string $method
     * @return array
     */
    private function prepareHeaders(array $headers, string $method): array
    {
        if ($method === Request::METHOD_POST) {
            $headers = array_merge(
                $headers,
                [
                    'Content-Type' => self::CONTENT_TYPE_JSON,
                    'Accept' => self::CONTENT_TYPE_JSON
                ]
            );
        }
        return $headers;
    }

    /**
     * Log API interaction if debug is enabled
     *
     * @param string $uri
     * @param array $headers
     * @param array $request
     * @param array $response
     * @return void
     */
    private function logApiInteraction(string $uri, array $headers, array $request, array $response): void
    {
        if ($this->config->hasDebug()) {
            $this->logger->debug(
                'Correios API Request',
                [
                    'uri' => $uri,
                    'headers' => $this->filterDebugData($headers),
                    'request' => $this->filterDebugData($request),
                    'response' => $this->filterDebugData($response)
                ]
            );
        }
    }

    /**
     * Log API error if debug is enabled
     *
     * @param string $uri
     * @param array $headers
     * @param array $request
     * @param string $error
     * @return void
     */
    private function logApiError(string $uri, array $headers, array $request, string $error): void
    {
        if ($this->config->hasDebug()) {
            $this->logger->error(
                'Correios API Error',
                [
                    'url' => $uri,
                    'headers' => $this->filterDebugData($headers),
                    'request' => $this->filterDebugData($request),
                    'error' => $error
                ]
            );
        }
    }

    /**
     * Filter sensitive data for logging
     *
     * @param array $debugData
     * @return array
     */
    private function filterDebugData(array $debugData): array
    {
        foreach ($debugData as $key => $value) {
            if (in_array($key, self::PROTECTED_KEYS, true)) {
                $debugData[$key] = '*** protected ***';
            } elseif (is_array($value)) {
                $debugData[$key] = $this->filterDebugData($value);
            }
        }

        return $debugData;
    }
}
