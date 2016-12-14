<?php
$eol = "\r\n";

echo '# ' . \Yii::t('app', 'Hello, reader!');
echo $eol;
echo \Yii::t('app', 'This is the main storage of [www.knigopis.com](http://www.knigopis.com) - the registry of read books.') . $eol;
echo \Yii::t('app', 'To add your book use www-site. All changes are committed and synchronized with this git repository.') . $eol;
echo \Yii::t('app', 'Do not use pull requests to make changes here.') . $eol;
echo $eol;
echo $eol;

echo '## ' . \Yii::t('app', 'Latest books with notes') . $eol;
$rowNumber = 0;
$latestBooks10 = array_slice($latestBooks, 0, 10, true);
foreach ($latestBooks10 as $bookData) {
    $userId3 = substr($bookData['user']['id'], 0, 3);
    $userUrl = "users/" . $userId3 . '/' . $bookData['user']['id'];
    echo "* " . $bookData['title'] . ' ~ [' . $bookData['user']['nickname'] . '](' . $userUrl . ')<sup>' . $bookData['user']['booksCount'] . '</sup>' . $eol;
    if ($rowNumber++ < 3) {
        $lines = preg_split('/\r\n|\r|\n/', $bookData['notes']);
        foreach ($lines as $i => $line) {
            echo "    > " . $line . $eol;
        }
    }
    echo $eol;
}

echo $eol;
echo '_' . \Yii::t('app', 'More notes [here]') . '(latest_books_with_notes.md)._' . $eol;
echo $eol;
echo $eol;

echo '## ' . \Yii::t('app', 'Latest users') . $eol;
foreach ($latestUsers as $userData) {
    $userId3 = substr($userData['id'], 0, 3);
    $userUrl = "users/" . $userId3 . '/' . $userData['id'];
    echo'[' . $userData['nickname'] . '](' . $userUrl . ')<sup>' . $userData['booksCount'] . '</sup> ' . $eol;
}
echo $eol;
echo $eol;

echo "_" . date('d.m.Y H:i:s') . "_";
echo $eol;
