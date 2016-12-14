<?php

namespace app\controllers;

use Yii;
use app\components\BaseRestController;
use app\models\Wish;
use app\models\GitModel;

class WishController extends BaseRestController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = ['view'];
        return $behaviors;
    }

    public function actionIndex()
    {
        $path = Yii::$app->user->identity->getRelativeWishesPath();
        $models = Wish::findAllInPathByExtension($path);
        return $models;
    }

    public function actionView($id) 
    {
        $model = Wish::findById($id);
        if (!$model) {
            throw new \yii\web\HttpException(404, 'Resource not found');
        }
        return $model;
    }
    
    public function actionCreate() 
    {
        $data = Yii::$app->request->post();
        $model = new Wish;
        $model->setAttributes($data);
        $model->setUser(Yii::$app->user->identity);
        GitModel::beginTransaction();
        if ($model->save()) {
            GitModel::commitTransaction($model->getCommitMessageForNewWish());
            return $model;
        } else {
            return ['error' => "Can not be saved", 'data' => $wish->getErrors()];
        }
    }
    
    public function actionUpdate($id) 
    {
        $model = Wish::findById($id);
        if (!$model) {
            throw new \yii\web\HttpException(404, 'Resource not found');
        }
        $user = Yii::$app->user->identity;
        if ($user->canUpdateWish($model)) {
            $data = Yii::$app->request->post();
            $model->setAttributes($data);
            $model->setUser($user);
            GitModel::beginTransaction();
            if ($model->save()) {
                GitModel::commitTransaction($model->getCommitMessageForUpdatedWish());
                return $model;
            } else {
                return ['error' => "Can not be saved", 'data' => $model->getErrors()];
            }
            return $model;
        } else {
            throw new \yii\web\HttpException(403, 'Forbidden');
        }
    }
    
    public function actionDelete($id)
    {
        $model = Wish::findById($id);
        if (!$model) {
            throw new \yii\web\HttpException(404, 'Resource not found');
        }
        $user = Yii::$app->user->identity;
        if ($user->canDeleteWish($model)) {
            $model->delete();
            return ['result' => 'ok'];
        } else {
            throw new \yii\web\HttpException(403, 'Forbidden');
        }
    }
}
