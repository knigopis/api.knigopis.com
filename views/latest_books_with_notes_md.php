<?php
$eol = "\r\n";

echo "# " . \Yii::t('app', 'List of the latest books with notes');
echo $eol;
echo '---';
echo $eol;
echo $eol;

foreach ($latestBooks as $bookData) {
    $userId3 = substr($bookData['user']['id'], 0, 3);
    $userUrl = "users/" . $userId3 . '/' . $bookData['user']['id'];
    echo "* " . $bookData['title'] . ' ~ [' . $bookData['user']['nickname'] . '](' . $userUrl . ')<sup>' . $bookData['user']['booksCount'] . '</sup>' . $eol;
}
echo $eol;
echo $eol;
echo "_" . date('d.m.Y H:i:s') . "_";
echo $eol;
