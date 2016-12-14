<?php

namespace app\controllers;

use Yii;
use app\components\BaseRestController;
use app\models\Book;
use app\models\GitModel;

class BookController extends BaseRestController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = ['view', 'latest', 'latest-notes'];
        return $behaviors;
    }

    public function actionIndex()
    {
        $path = Yii::$app->user->identity->getRelativeBooksPath();
        $books = Book::findAllInPathByExtension($path);
        $cleanBooks = [];
        foreach ($books as $book) {
            unset($book['user']);
            $cleanBooks[] = $book;
        }
        return $cleanBooks;
    }

    public function actionView($id) 
    {
        $book = Book::findById($id);
        if (!$book) {
            throw new \yii\web\HttpException(404, 'Resource not found');
        }
        return $book;
    }
    
    public function actionCreate() 
    {
        $data = Yii::$app->request->post();
        $book = new Book;
        $book->setAttributes($data);
        $book->setUser(Yii::$app->user->identity);
        GitModel::beginTransaction();
        if ($book->save()) {
            GitModel::commitTransaction($book->getCommitMessageForNewBook());
            return $book;
        } else {
            return ['error' => "Can not be saved", 'data' => $book->getErrors()];
        }
    }
    
    public function actionUpdate($id) 
    {
        $book = Book::findById($id);
        if (!$book) {
            throw new \yii\web\HttpException(404, 'Resource not found');
        }
        $user = Yii::$app->user->identity;
        if ($user->canUpdateBook($book)) {
            $data = Yii::$app->request->post();
            $book->setAttributes($data);
            $book->setUser($user);
            GitModel::beginTransaction();
            if ($book->save()) {
                GitModel::commitTransaction($book->getCommitMessageForUpdatedBook());
                return $book;
            } else {
                return ['error' => "Can not be saved", 'data' => $book->getErrors()];
            }
            return $book;
        } else {
            throw new \yii\web\HttpException(403, 'Forbidden');
        }
    }
    
    public function actionDelete($id)
    {
        $book = Book::findById($id);
        if (!$book) {
            throw new \yii\web\HttpException(404, 'Resource not found');
        }
        $user = Yii::$app->user->identity;
        if ($user->canDeleteBook($book)) {
            $book->delete();
            return ['result' => 'ok'];
        } else {
            throw new \yii\web\HttpException(403, 'Forbidden');
        }
    }
    
    public function actionLatest()
    {
        return Book::getLatestBooks();
    }
    
    public function actionLatestNotes()
    {
        return Book::getLatestBooksWithNotes();
    }
}
