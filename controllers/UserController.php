<?php

namespace app\controllers;

use Yii;
use app\components\BaseRestController;
use app\models\User;
use app\models\Book;
use yii\web\HttpException;

class UserController extends BaseRestController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = ['get-credentials', 'get-credentials-post', 'view', 'books', 'latest', 'find-id-by-parse-id'];
        return $behaviors;
    }

    public function actionGetCredentials($token)
    {
        if ($token) {
            $s = file_get_contents('http://ulogin.ru/token.php?token=' . $token . '&host=www.knigopis.com');
            $userData = json_decode($s, true);
            if (isset($userData['uid'])) {
                $lang = Yii::$app->request->get('lang');
                if ($lang) {
                    if (preg_match('/^ru/', $lang)) {
                        $userData['lang'] = 'ru';
                    } else {
                        $userData['lang'] = 'en';
                    }
                }

                $user = User::getUserByULoginData($userData);
                $accessToken = $user->getAccessToken();
                // Set cookie for further requests
                Yii::$app->user->login($user, 3600 * 24 * 365);

                $cred = [
                    'access-token' => $accessToken,
                    'user' => $user,
                ];
                return $cred;
            }
            if (!empty($userData['error'])) {
                throw new HttpException(401, $userData['error']);
            }
        }
        throw new HttpException(400, 'Bad request');
    }
    
    public function actionGetCredentialsPost()
    {
        $token = Yii::$app->request->post('token');
        if (!$token) {
            throw new HttpException(400, 'Bad request');
        }
        return $this->actionGetCredentials($token);
    }
    
    public function actionIndex()
    {
        return Yii::$app->user->identity;
    }
    
    public function actionCurrent()
    {
        return Yii::$app->user->identity;
    }
    
    public function actionView($id) 
    {
        $user = User::findById($id);
        if (!$user) {
            throw new HttpException(404, 'Resource not found');
        }
        return $user;
    }
    
    public function actionUpdate($id) 
    {
        $user = Yii::$app->user->identity;
        if ($user->id === $id) {
            $data = Yii::$app->request->post();
            $safeFields = ['nickname', 'profile'];
            foreach ($data as $name => $value) {
                if (in_array($name, $safeFields)) {
                    $user->setAttribute($name, $value);
                }
            }
            if ($user->save()) {
                return $user;
            } else {
                return ['error' => "Can not be saved", 'data' => $user->getErrors()];
            }
            return $user;
        } else {
            throw new \yii\web\HttpException(403, 'Forbidden');
        }
    }
    
    public function actionBooks($userId)
    {
        $user = User::findById($userId);
        if (!$user) {
            throw new \yii\web\HttpException(404, 'Resource not found');
        }
        $path = $user->getRelativeBooksPath();
        $books = Book::findAllInPathByExtension($path);
        $cleanBooks = [];
        foreach ($books as $book) {
            unset($book['user']);
            $cleanBooks[] = $book;
        }
        return $cleanBooks;
    }

    public function actionLatest()
    {
        return User::getLatestUsers();
    }
    
    public function actionFindIdByParseId($parseId)
    {
        return User::findIdByParseId($parseId);
    }

    public function actionCopyBooksFromUser($otherUserId)
    {
        $otherUser = User::findById($otherUserId);
        if (!$otherUser) {
            return [
                'result' => 'error',
                'message' => 'Specified user not found',
            ];
        }
        $user = Yii::$app->user->identity;
        if ($user->booksCount > 0) {
            return [
                'result' => 'error',
                'message' => 'Current user has books',
            ];
        } else if ($otherUser->booksCount == 0) {
            return [
                'result' => 'error',
                'message' => 'Specified user has 0 books',
            ];
        }
        set_time_limit(240);
        $user->copyBooksFromUser($otherUser);
        return ['result' => 'ok'];
    }
}
