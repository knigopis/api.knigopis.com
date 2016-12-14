<?php

use yii\helpers\Html;
use app\models\Book;

$eol = "\r\n";
Book::sortListByDate($models);
$modelsByYears = Book::splitListByYears($models);

echo "# " . \Yii::t('app', 'List of books read by') . ' ';
if ($user->profile) {
    echo '[' . ($user->nickname) . '](' . ($user->profile) . ')<sup>' . $user->booksCount . '</sup>';
} else {
    echo ($user->nickname) . '<sup>' . $user->booksCount . '</sup>';
}
echo $eol;
echo '---';
echo $eol;
echo $eol;

foreach ($modelsByYears as $year => $models) {

    if ($year === 'other') {
        $year = \Yii::t('app', 'Other years');
    }
    echo "## $year" . $eol;
    echo $eol;

    foreach ($models as $model) {
        if ($model->title) {
            echo "### " . ($model->title) . $eol;
        }

        if ($model->author) {
            echo ($model->author) . $eol;
        }
        
        $readDate = $model->getReadDateForList();
        if ($readDate) {
            echo "> [" . ($readDate) . "] ";
        }

        if ($model->notes) {
            $lines = preg_split('/\r\n|\r|\n/', $model->notes);
            foreach ($lines as $i => $line) {
                if ($i != 0 || !$readDate) {
                    echo "> ";
                }
                echo ($line) . $eol;
            }
        } else if ($readDate) {
            echo $eol;
        }
        echo $eol;
        echo $eol;
    }
    echo $eol;
}
?>
