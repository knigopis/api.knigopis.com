<?php

namespace app\commands;

use yii\console\Controller;
use app\models\GitModel;
use app\models\User;
use app\models\Book;

/**
 * Tools
 *
 */
class ToolsController extends Controller
{

    public function actionUpdateReadme()
    {
        Book::renderMainPage();
    }

}
