<?php

namespace App\Services;

use Obs\ObsClient;
use Illuminate\Support\Facades\Log;

class OBSService
{
    protected $client;
    protected $bucket;

    public function __construct()
    {
        $this->client = new ObsClient([
            'key' => env('OBS_KEY'),
            'secret' => env('OBS_SECRET'),
            'endpoint' => env('OBS_ENDPOINT'),
        ]);
        $this->bucket = env('OBS_BUCKET');
    }

    /**
     * Upload file to OBS
     */
    public function upload($filePath, $objectKey)
    {
        try {
            return $this->client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $objectKey,
                'SourceFile' => $filePath,
            ]);
        } catch (\Exception $e) {
            Log::error('OBS Upload Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete file from OBS
     */
    public function delete($objectKey)
    {
        try {
            return $this->client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $objectKey,
            ]);
        } catch (\Exception $e) {
            Log::error('OBS Delete Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get signed URL for file (temporary access)
     */
    public function getUrl($objectKey, $expires = 3600)
    {
        try {
            $result = $this->client->createSignedUrl([
                'Method' => 'GET',
                'Bucket' => $this->bucket,
                'Key' => $objectKey,
                'Expires' => $expires, // seconds
            ]);

            return $result['SignedUrl'];
        } catch (\Exception $e) {
            Log::error('OBS GetUrl Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get permanent public URL (if bucket is public)
     */
    public function getPublicUrl($objectKey)
    {
        $endpoint = env('OBS_ENDPOINT');
        $bucket = $this->bucket;

        // Remove protocol from endpoint
        $endpoint = str_replace(['https://', 'http://'], '', $endpoint);

        return "https://{$bucket}.{$endpoint}/{$objectKey}";
    }

    /**
     * Check if file exists in OBS
     */
    public function exists($objectKey)
    {
        try {
            $result = $this->client->getObjectMetadata([
                'Bucket' => $this->bucket,
                'Key' => $objectKey,
            ]);

            return $result['HttpStatusCode'] === 200;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get file metadata
     */
    public function getMetadata($objectKey)
    {
        try {
            return $this->client->getObjectMetadata([
                'Bucket' => $this->bucket,
                'Key' => $objectKey,
            ]);
        } catch (\Exception $e) {
            Log::error('OBS GetMetadata Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * List files in a folder
     */
    public function listObjects($prefix = '', $maxKeys = 1000)
    {
        try {
            $result = $this->client->listObjects([
                'Bucket' => $this->bucket,
                'Prefix' => $prefix,
                'MaxKeys' => $maxKeys,
            ]);

            return $result['Contents'] ?? [];
        } catch (\Exception $e) {
            Log::error('OBS ListObjects Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Copy file within OBS
     */
    public function copy($sourceKey, $destinationKey)
    {
        try {
            return $this->client->copyObject([
                'Bucket' => $this->bucket,
                'Key' => $destinationKey,
                'CopySource' => $this->bucket . '/' . $sourceKey,
            ]);
        } catch (\Exception $e) {
            Log::error('OBS Copy Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Download file from OBS
     */
    public function download($objectKey, $savePath)
    {
        try {
            return $this->client->getObject([
                'Bucket' => $this->bucket,
                'Key' => $objectKey,
                'SaveAsFile' => $savePath,
            ]);
        } catch (\Exception $e) {
            Log::error('OBS Download Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete multiple files from OBS
     */
    public function deleteMultiple(array $objectKeys)
    {
        try {
            $objects = array_map(function($key) {
                return ['Key' => $key];
            }, $objectKeys);

            return $this->client->deleteObjects([
                'Bucket' => $this->bucket,
                'Objects' => $objects,
            ]);
        } catch (\Exception $e) {
            Log::error('OBS DeleteMultiple Error: ' . $e->getMessage());
            throw $e;
        }
    }
}
