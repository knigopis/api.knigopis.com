<?php

$params = require(__DIR__ . '/params.php');

if (file_exists(__DIR__ . '/params_private.php')) {
    $paramsPrivate = require __DIR__ . '/params_private.php';
    $params = yii\helpers\ArrayHelper::merge($params, $paramsPrivate);
}


$config = [
    'id' => 'basic',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'components' => [
        'request' => [
            'cookieValidationKey' => 'skiwo49872h2rkjsdfh24t29shgndfg4h',
            'parsers' => [
                'application/json' => 'yii\web\JsonParser',
            ],
        ],
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'user' => [
            'enableSession' => true,
            'enableAutoLogin' => true,
            'loginUrl' => null,
            'identityClass' => 'app\models\User',
            'identityCookie' => [
                'name' => '_identity',
                'domain' => '.knigopis.com',
            ],
        ],
        'session' => [
            'cookieParams' => array('domain' => '.knigopis.com'),
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'urlManager' => [
            'enablePrettyUrl' => true,
            'enableStrictParsing' => true,
            'showScriptName' => false,
            'rules' => [
                'POST subscriptions/<subUserId:[a-zA-Z0-9-_]+>' => 'subscription/create',
                'POST users/copy-books-from-user/<otherUserId:[a-zA-Z0-9-_]+>' => 'user/copy-books-from-user',
                'GET users/latest' => 'user/latest',
                'GET users/current' => 'user/current',
                'POST users/get-credentials' => 'user/get-credentials-post',
                'GET books/latest' => 'book/latest',
                'GET books/latest-notes' => 'book/latest-notes',
                ['class' => 'yii\rest\UrlRule', 'controller' => ['book', 'user', 'wish'], 'tokens' => ['{id}' => '<id:[a-zA-Z0-9-_]+>']],
                ['class' => 'yii\rest\UrlRule', 'controller' => ['subscription'], 'tokens' => ['{id}' => '<subUserId:[a-zA-Z0-9-_]+>']],
                'user/<action>' => 'user/<action>',
                'GET users/<userId:[a-zA-Z0-9-_]+>/books' => 'user/books',
                'GET users/find-id-by-parse-id/<parseId:[a-zA-Z0-9-_]+>' => 'user/find-id-by-parse-id',
            ],
        ]
    ],
    'params' => $params,
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
    ];
}

//for non-ASCII escapeshellarg
setlocale(LC_CTYPE, "en_US.UTF-8");

return $config;
