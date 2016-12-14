<?php

namespace app\components;

use Yii;
use yii\rest\Controller;
use yii\filters\auth\CompositeAuth;
use yii\filters\auth\HttpBearerAuth;
use yii\filters\auth\QueryParamAuth;

class BaseRestController extends Controller
{
    
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        
        //Configure nginx instead of this behavior
        if (YII_ENV_DEV) {
            $behaviors['corsFilter'] = [
                'class' => \yii\filters\Cors::className(),
                'cors' => [
                    'Origin' => ['*'],
                    'Access-Control-Request-Method' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'],
                    'Access-Control-Request-Headers' => ['*'],
                    'Access-Control-Max-Age' => 3600,
                    'Access-Control-Allow-Credentials' => true,
                ]
            ];
        }   
        
        $behaviors['authenticator'] = [
            'class' => CompositeAuth::className(),
            'authMethods' => [
                HttpBearerAuth::className(),
                QueryParamAuth::className(),
                CookieAuth::className(),
            ],
        ];
        
        return $behaviors;
    }
    
    public function beforeAction($action) 
    {
        $res = parent::beforeAction($action);
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return $res;
    }

}
