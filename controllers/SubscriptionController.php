<?php

namespace app\controllers;

use Yii;
use app\components\BaseRestController;
use app\models\User;
use yii\web\HttpException;

class SubscriptionController extends BaseRestController
{

    public function actionIndex()
    {
        $subs = Yii::$app->user->identity->subscriptions;
        if ($subs) {
            $data = [];
            foreach ($subs as $subUserId => $subBooksCount) {
                $subUser = User::findById($subUserId);
                if ($subUser) {
                    $data[] = ['subUser' => $subUser, 'lastBooksCount' => $subBooksCount];
                }
                usort($data, function($a, $b){
                    return strtotime($b['subUser']['updatedAt']) - strtotime($a['subUser']['updatedAt']);
                });
            }
            return $data;
        } else {
            return [];
        }
    }
    
    public function actionView($subUserId)
    {
        $user = Yii::$app->user->identity;
        if (isset($user->subscriptions[$subUserId])) {
            return $user->subscriptions[$subUserId];
        } else {
            throw new HttpException(404, 'Resource not found');
        }
    }

    public function actionCreate($subUserId)
    {
        $subUser = User::findById($subUserId);
        if (!$subUser) {
            throw new HttpException(404, 'Resource not found');
        }
        if ($subUser->id === Yii::$app->user->identity->id) {
            throw new HttpException(400, 'You can not subscribe to you self');
        }
        Yii::$app->user->identity->subscribe($subUser);
        return ['result' => 'ok'];
    }

    public function actionUpdate($subUserId)
    {
        $subUser = User::findById($subUserId);
        if (!$subUser) {
            throw new HttpException(404, 'Resource not found');
        }
        $user = Yii::$app->user->identity;
        if (isset($user->subscriptions[$subUser->id])) {
            $user->updateSubscription($subUser);
        } else {
            throw new HttpException(400, 'You are not subscribed');
        }

        return ['result' => 'ok'];
    }

    public function actionDelete($subUserId)
    {
        $subUser = User::findById($subUserId);
        if (!$subUser) {
            throw new HttpException(404, 'Resource not found');
        }
        $user = Yii::$app->user->identity;
        if (isset($user->subscriptions[$subUser->id])) {
            $user->deleteSubscription($subUser);
        } else {
            throw new HttpException(400, 'You are not subscribed');
        }

        return ['result' => 'ok'];
    }

}
