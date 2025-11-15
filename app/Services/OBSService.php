<?php

namespace App\Services;

use Obs\ObsClient;

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

    public function upload($filePath, $objectKey)
    {
        return $this->client->putObject([
            'Bucket' => $this->bucket,
            'Key' => $objectKey,
            'SourceFile' => $filePath,
        ]);
    }

    public function getUrl($objectKey)
    {
        return $this->client->createSignedUrl([
            'Method' => 'GET',
            'Bucket' => $this->bucket,
            'Key' => $objectKey,
            'Expires' => 3600, // ساعة
        ])['SignedUrl'];
    }
}
