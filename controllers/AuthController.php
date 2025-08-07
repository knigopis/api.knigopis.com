<?php

namespace app\controllers;

use app\models\Auth;
use Yii;
use app\components\BaseRestController;
use yii\web\HttpException;

class AuthController extends BaseRestController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        unset($behaviors['authenticator']);

        return $behaviors;
    }

    public function actionRegister()
    {
        $data = Yii::$app->request->post();
        $auth = new Auth();
        $auth->setAttributes($data);

        $user = $auth->register();
        if ($user) {
            $accessToken = $user->getAccessToken();
            // Set cookie for further requests
            \Yii::$app->user->login($user, 3600 * 24 * 365);

            $cred = [
                'access-token' => $accessToken,
                'user' => $user,
            ];

            return $cred;
        } else {
            return ['error' => "Can not be registered", 'data' => $auth->getErrors()];
        }
    }

    public function actionLogin()
    {
        $data = Yii::$app->request->post();
        $auth = new Auth();
        $auth->setAttributes($data);

        $user = $auth->login();

        if ($user) {
            $accessToken = $user->getAccessToken();
            // Set cookie for further requests
            Yii::$app->user->login($user, 3600 * 24 * 365);

            $cred = [
                'access-token' => $accessToken,
                'user' => $user,
            ];
            return $cred;
        }

        throw new HttpException(400, 'Cannot login');
    }
}
