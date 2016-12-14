<?php

namespace app\models;

class Wish extends GitModel
{

    protected $_attributes = array(
        'id' => null,
        'userId' => null,
        'createdAt' => null,
        'updatedAt' => null,
        'title' => null,
        'author' => null,
        'priority' => null,
        'notes' => null,
    );

    /**
     * @var User
     */
    protected $user;

    public function rules()
    {
        return [
            [['title', 'author', 'notes'], 'filter', 'filter' => 'trim'],
            ['author', 'string', 'max' => 140],
            ['title', 'string', 'max' => 140],
            ['notes', 'string', 'max' => 2000],
            ['priority', 'integer', 'min' => 1, 'max' => 100],
        ];
    }

    public function getRelativePath()
    {
        $userId = $this->_attributes['userId'];
        if (!$userId) {
            throw new GitModelException("userId is empty");
        }
        $userId3 = substr($userId, 0, 3);
        $path = "users/$userId3/$userId/wishes";
        return $path;
    }

    public static function getRelativeIndexFilePath()
    {
        return 'wishes.csv';
    }

    public function setUser(User $user)
    {
        $this->user = $user;
        $this->userId = $user->id;
    }

    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);
        if ($insert || $changedAttributes) {
            static::renderList($this->user);
        }
    }

    public static function renderList(User $user)
    {
        $path = $user->getRelativeWishesPath();
        $models = static::findAllInPathByExtension($path);
        if ($user->lang === 'ru') {
            \Yii::$app->language = 'ru';
        }
        $md = \Yii::$app->view->renderFile('@app/views/wishes_md.php', ['models' => $models, 'user' => $user]);
        $file = $path . '/../wishes.md';
        $fullpath = \yii\helpers\BaseFileHelper::normalizePath(self::getRepoPath() . '/' . $file, '/');
        file_put_contents($fullpath, $md);
        GitModel::commitFilesWithMessage($file, 'Updated wishes.md of ' . escapeshellarg($user->nickname));
    }

    public function afterFind()
    {
        parent::afterFind();
        $this->user = User::findById($this->userId);
    }

    public function getCommitMessageForNewWish()
    {
        return "Added wish " . escapeshellarg($this->title) . " by " . escapeshellarg($this->user->nickname);
    }

    public function getCommitMessageForUpdatedWish()
    {
        return "Updated wish " . escapeshellarg($this->title) . " by " . escapeshellarg($this->user->nickname);
    }
    
    public function getCommitMessageForDeletedModel()
    {
        return "Deleted a wish by " . escapeshellarg($this->user->nickname);
    }

    public static function sortListByPriority(&$models)
    {
        usort($models, function($a, $b) {
            if ($a->priority > $b->priority) {
                return -1;
            }
            if ($a->priority < $b->priority) {
                return 1;
            }
            return 0;
        });
    }

}
