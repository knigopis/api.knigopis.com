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
        
        $behaviors['authenticator'] = [
            'class' => CompositeAuth::class,
            'authMethods' => [
                HttpBearerAuth::class,
                QueryParamAuth::class,
                CookieAuth::class,
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
