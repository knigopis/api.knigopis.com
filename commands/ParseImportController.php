<?php

namespace app\commands;

use yii\console\Controller;
use app\models\GitModel;
use app\models\User;
use app\helpers\Csv;

/**
 * Imports data from parse to the local repo
 *
 */
class ParseImportController extends Controller
{
    
    protected $_notSavedData = [];

    /**
     * This command imports all data from extracted Parse zip into local repo
     * @param path $path the directory to be imported.
     */
    public function actionByDir($path)
    {
        $searchFor = ['_User', 'Book', 'Wish', 'Subscription'];
        $files = [];
        foreach ($searchFor as $name) {
            $fullpath = \yii\helpers\BaseFileHelper::normalizePath($path . '/' . $name . '.json');
            if (is_file($fullpath) && is_readable($fullpath)) {
                $files[$name] = $fullpath;
            } else {
                echo "File $fullpath not found or not readable. Exit.\n";
                exit (1);
            }
        }
        $this->actionUsers($files['_User']);
        $this->actionBooks($files['Book']);
        //$this->actionWishes($files['Wish']);
        $this->actionSubs($files['Subscription']);
        echo "Done\n";
    }

    /**
     * This command imports data to books
     * @param file $file the file to be imported.
     */
    public function actionBooks($file)
    {
        return $this->_iterateFile($file, 'parse_ids_books.csv', 'app\models\Book', 'books');
    }
    
    /**
     * This command imports data to wishes
     * @param file $file the file to be imported.
     */
    public function actionWishes($file)
    {
        return $this->_iterateFile($file, 'parse_ids_wishes.csv', 'app\models\Wish', 'wishes');
    }
    
    protected function _iterateFile($file, $csvParseIdFile, $modelClassName, $modelShortName)
    {
        $records = $this->_readFile($file);
        $limit = count($records);
        GitModel::beginTransaction();
        $added = 0;
        $csvParseIdFileAbs = GitModel::getRepoPath() . '/' . $csvParseIdFile;
        if (!is_file($csvParseIdFileAbs)){
            file_put_contents($csvParseIdFileAbs, '');
        }
        $savedParseIds = Csv::loadAssocOneValue($csvParseIdFileAbs);
        $savedParseUserIds = Csv::loadAssocOneValue(GitModel::getRepoPath() . '/' . 'parse_ids_users.csv');
        if (empty($savedParseUserIds)) {
            echo "Empty file with parse users ids\n";
            exit(1);
        }
        for ($i = 0; $i < $limit; $i++) {
            $row = $records[$i];
            if (!isset($savedParseIds[$row['objectId']])) {
                $model = new $modelClassName;
                $this->_populateRecord($model, $row);
                $parseUserId = $row['user']['objectId'];
                $user = null;
                if (isset($savedParseUserIds[$parseUserId])) {
                    $localUserId = $savedParseUserIds[$parseUserId];
                    $user = User::findById($localUserId);
                    if (!$user) {
                        echo "Local user with id '$localUserId' not found. Skipping.\n";
                    }
                } else {
                    echo "User with parse id '$parseUserId' not found. Skipping.\n";
                }
                if (!$user) {
                    $this->_notSavedData[] = $row;
                    continue;
                }
                $model->setUser($user);
                $model->save();
                Csv::addRow($csvParseIdFileAbs, [$row['objectId'], $model->id]);
                echo "$i: saved new {$model->id}\n";
                $added++;
            } else {
                echo "Parse id '{$row['objectId']}' exists with id '" . $savedParseIds[$row['objectId']] . "'. Skipping.\n";
            }
        }
        if ($added) {
            GitModel::addFileToTransactionCommit($csvParseIdFile);
            GitModel::commitTransaction("Imported $added $modelShortName records");
            echo "Imported: $added\n";
        }
        $this->_dumpNotSaved(GitModel::getRepoPath() . "/unsaved_$modelShortName.json");
    }
    
    /**
     * This command imports data to users
     * @param file $file the file to be imported.
     */
    public function actionUsers($file)
    {
        $records = $this->_readFile($file);
        $limit = count($records);
        GitModel::beginTransaction();
        $added = 0;
        $csvParseIdFile = 'parse_ids_users.csv';
        $csvParseIdFileAbs = GitModel::getRepoPath() . '/' . $csvParseIdFile;
        if (!is_file($csvParseIdFileAbs)){
            file_put_contents($csvParseIdFileAbs, '');
        }
        $savedParseIds = Csv::loadAssocOneValue($csvParseIdFileAbs);
        for ($i = 0; $i < $limit; $i++) {
            $row = $records[$i];
            if (!isset($savedParseIds[$row['objectId']])) {
                $model = new User;
                $this->_populateRecord($model, $row);
                $model->booksCount = 0; //it will be recalculated after importing books
                if (!empty($row['uid']) && !empty($row['network'])) {
                    $localId = $row['uid'] . '-' . $row['network'];
                } else {
                    $localId = $row['objectId'] . '-unknown';
                }
                $model->id = $localId;
                $model->save();
                Csv::addRow($csvParseIdFileAbs, [$row['objectId'], $model->id]);
                echo "$i: saved new {$model->id}\n";
                $added++;
            } else {
                echo "Parse id '{$row['objectId']}' exists with id '" . $savedParseIds[$row['objectId']] . "'. Skipping.\n";
            }
        }
        if ($added) {
            GitModel::addFileToTransactionCommit($csvParseIdFile);
            GitModel::commitTransaction("Imported $added user records");
        }
    }
    
    /**
     * This command imports data to subscriptions
     * @param file $file the file to be imported.
     */
    public function actionSubs($file)
    {
        $records = $this->_readFile($file);
        $limit = count($records);
        GitModel::beginTransaction();
        $added = 0;
        
        $savedParseUserIds = Csv::loadAssocOneValue(GitModel::getRepoPath() . '/' . 'parse_ids_users.csv');
        if (empty($savedParseUserIds)) {
            echo "Empty file with parse users ids\n";
            exit(1);
        }
        
        for ($i = 0; $i < $limit; $i++) {
            $row = $records[$i];
            $parseUserId = $row['user']['objectId'];
            $parseSubUserId = $row['subUser']['objectId'];
            echo "$i: $parseUserId to $parseSubUserId: ";
            if (isset($savedParseUserIds[$parseUserId]) && isset($savedParseUserIds[$parseSubUserId])) {
                $localUserId = $savedParseUserIds[$parseUserId];
                $localSubUserId = $savedParseUserIds[$parseSubUserId];
                $user = User::findById($localUserId);
                $subUser = User::findById($localSubUserId);
                if ($user && $subUser) {
                    echo "local ids: {$user->id}, {$subUser->id} ";
                    $subs = $user->subscriptions;
                    if (!isset($subs[$subUser->id])) {
                        $subs[$subUser->id] = $row['lastBooksCount'];
                        $user->subscriptions = $subs;
                        $user->save();
                        $added++;
                    } else {
                        echo "already exists";
                    }
                } else {
                    echo "local models are empty";
                }
            } else {
                echo "not found imported users";
            }
            echo "\n";
        }
        if ($added) {
            GitModel::commitTransaction("Imported $added subscriptions");
            echo "Imported: $added\n";
        }
    }
    
    /**
     * 
     * @param string $file
     * @return array
     */
    protected function _readFile($file)
    {
        echo "Reading file '$file'.\n";
        $content = file_get_contents($file);
        if (!$content) {
            echo "File's content is empty or it is not a file.\n";
            exit(1);
        }
        $data = json_decode($content, true);
        if (!$data || empty($data['results'])) {
            echo "Decoded data is empty.\n";
            exit(1);
        }
        $length = count($data['results']);
        echo "File has $length records.\n";
        return $data['results'];
    }
    
    protected function _populateRecord(GitModel $model, $data)
    {
        $class = get_class($model);
        $class::populateRecord($model, $data);
        $dateFormat = $class::getDateFormat();
        $model->createdAt = date($dateFormat, strtotime($data['createdAt']));
        $model->updatedAt = date($dateFormat, strtotime($data['updatedAt']));
    }
    
    protected function _dumpNotSaved($dumpFile)
    {
        if (!$this->_notSavedData) {
            return;
        }
        $str = \yii\helpers\Json::encode($this->_notSavedData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        file_put_contents($dumpFile, $str);
        echo "File with unsaved records dumped: $dumpFile\n";
        $this->_notSavedData = [];
    }
}
