<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
   |--------------------------------------------------------------------------
   | Huawei Cloud Services Configuration
   |--------------------------------------------------------------------------
   */

    'huawei' => [

        // Object Storage Service (OBS)
        'obs' => [
            'access_key' => env('HUAWEI_OBS_ACCESS_KEY'),
            'secret_key' => env('HUAWEI_OBS_SECRET_KEY'),
            'endpoint' => env('HUAWEI_OBS_ENDPOINT', 'obs.ap-southeast-1.myhuaweicloud.com'),
            'bucket' => env('HUAWEI_OBS_BUCKET', 'aquaguard-files'),
            'region' => env('HUAWEI_OBS_REGION', 'ap-southeast-1'),
        ],

        // Relational Database Service (RDS)
        'rds' => [
            'host' => env('HUAWEI_RDS_HOST'),
            'port' => env('HUAWEI_RDS_PORT', 3306),
            'database' => env('HUAWEI_RDS_DATABASE', 'aquaguard'),
            'username' => env('HUAWEI_RDS_USERNAME'),
            'password' => env('HUAWEI_RDS_PASSWORD'),
        ],

        // ModelArts AI Service
        'modelarts' => [
            'endpoint' => env('HUAWEI_MODELARTS_ENDPOINT'),
            'token' => env('HUAWEI_MODELARTS_TOKEN'),
            'project_id' => env('HUAWEI_MODELARTS_PROJECT_ID'),
            'model_id' => env('HUAWEI_MODELARTS_MODEL_ID'),
            'service_id' => env('HUAWEI_MODELARTS_SERVICE_ID'),
        ],

        // Elastic Cloud Server (ECS)
        'ecs' => [
            'region' => env('HUAWEI_ECS_REGION', 'ap-southeast-1'),
            'access_key' => env('HUAWEI_ACCESS_KEY'),
            'secret_key' => env('HUAWEI_SECRET_KEY'),
        ],

        // Cloud Eye (Monitoring)
        'cloudeye' => [
            'enabled' => env('HUAWEI_CLOUDEYE_ENABLED', true),
            'namespace' => env('HUAWEI_CLOUDEYE_NAMESPACE', 'SYS.ECS'),
        ],
    ],

];
