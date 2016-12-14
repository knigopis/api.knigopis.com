<?php

use yii\helpers\Html;
use app\models\Wish;

$eol = "\r\n";
Wish::sortListByPriority($models);

echo "# " . \Yii::t('app', 'Wish list of books by') . ' ';
if ($user->profile) {
    echo "[" . ($user->nickname) . "](" . ($user->profile) . ")";
} else {
    echo ($user->nickname);
}
echo $eol;
echo '---';
echo $eol;
echo $eol;

foreach ($models as $model) {
    if ($model->title) {
        echo "### `" . $model->priority . "` " . ($model->title) . $eol;
    }

    if ($model->author) {
        echo ($model->author) . $eol;
    }

    if ($model->notes) {
        $lines = preg_split('/\r\n|\r|\n/', $model->notes);
        foreach ($lines as $i => $line) {
            echo "> " . ($line) . $eol;
        }
    }
    echo $eol;
}
?>
