<?php

namespace app\models;

class User extends GitModel implements \yii\web\IdentityInterface
{
    
    protected $_attributes = array(
        'id' => null,
        'lang' => null,
        'nickname' => null,
        'photo' => null,
        'profile' => null,
        'identity' => null,
        'booksCount' => null,
        'subscriptions' => null,
        'createdAt' => null,
        'updatedAt' => null,
    );
        
    protected $_accessToken;

    /**
     * @inheritdoc
     */
    public static function findIdentityByAccessToken($token, $type = null)
    {
        $cache = \Yii::$app->cache;
        $userData = $cache->get($token);
        if ($userData !== false) {
            $user = new static($userData);
            $user->setAccessToken($token);
            $user->setIsNewRecord(false);
            return $user;
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * 
     * @param array $userData
     * @return User
     */
    public static function getUserByULoginData($userData)
    {
        /* 
         * {"nickname":"nick",
         * "photo":"http://pbs.twimg.com/url.jpg",
         * "profile":"http://twitter.com/nick",
         * "uid":"112233",
         * "identity":"http://twitter.com/nick",
         * "network":"twitter"}
         */
        
        $id = $userData['uid'] . '-' . $userData['network'];
        $user = static::findById($id);
        if (!$user) {
            $user = new static();
            foreach ($userData as $name => $value) {
                if ($user->hasAttribute($name)) {
                    $user->setAttribute($name, $value);
                }
            }
            $user->id = $id;
            $user->booksCount = 0;
            $user->save();
        } else {
            $user->updateIfNeeded($userData);
        }

        $sec = new \yii\base\Security;
        $accessToken = $sec->generateRandomString(24);

        $cache = \Yii::$app->cache;
        $cache->set($accessToken, $user->getAllAttributes());
        
        $user->setAccessToken($accessToken);
        
        return $user;
    }
    
    public function updateIfNeeded($newUserData)
    {
        $checkFields = ['lang', 'photo'];
        $update = false;
        foreach ($checkFields as $name) {
            if (array_key_exists($name, $newUserData) && $this->$name !== $newUserData[$name]) {
                $this->setAttribute($name, $newUserData[$name]);
                $update = true;
            }
        }
        if ($update) {
            $this->save();
        }
    }

    public function getAuthKey()
    {
        return $this->_accessToken;
    }

    public function validateAuthKey($authKey) {
        if ($this->_accessToken) {
            return $this->_accessToken === $authKey;
        } else {
            /* We do not have the instance with accessToken while trying to login by the cookie,
             * but we can find the identity by the accessToken and compare it with this instance */
            $cache = \Yii::$app->cache;
            $userData = $cache->get($authKey);
            if ($userData && isset($userData['id']) && $userData['id'] === $this->id) {
                $this->_accessToken = $authKey;
                return true;
            }
        }
    }

    public static function findIdentity($id)
    {
        return static::findById($id);
    }
    
    public static function getRelativeIndexFilePath()
    {
        /* switch off index file */
        return false;
    }
    
    public function getRelativePath()
    {
        $userId = $this->id;
        if (!$userId) {
            throw new GitModelException("userId is empty");
        }
        $userId3 = substr($userId, 0, 3);
        $path = "users/$userId3/$userId";
        return $path;
    }
    
    /**
     * @inheritdoc
     */
    public static function findById($id)
    {
        $userId = $id;
        $userId3 = substr($userId, 0, 3);
        $fullpath = self::getRepoPath() . "/users/$userId3/$userId/$userId.json";
        return self::findOne($fullpath);
    }
    
    public function setAccessToken($token)
    {
        $this->_accessToken = $token;
    }
    
    public function getAccessToken()
    {
        return $this->_accessToken;
    }

    public function afterSave($insert, $changedAttributes) {
        if (!$insert && $this->_accessToken) {
            $cache = \Yii::$app->cache;
            $cache->set($this->_accessToken, $this->getAllAttributes());
        }
        if ($insert || isset($changedAttributes['booksCount'])) {
            $this->addToLatestUsers();
            Book::renderMainPage();
        }
    }
    
    public function getRelativeBooksPath()
    {
        $userId = $this->id;
        $userId3 = substr($userId, 0, 3);
        $fullpath = "users/$userId3/$userId/books";
        return $fullpath;
    }
    
    public function getRelativeWishesPath()
    {
        $userId = $this->id;
        $userId3 = substr($userId, 0, 3);
        $fullpath = "users/$userId3/$userId/wishes";
        return $fullpath;
    }
    
    public function canUpdateBook(Book $book)
    {
        return $book->userId === $this->id;
    }

    public function canDeleteBook(Book $book)
    {
        return $book->userId === $this->id;
    }

    public function canUpdateWish(Wish $wish)
    {
        return $wish->userId === $this->id;
    }
    
    public function canDeleteWish(Wish $wish)
    {
        return $wish->userId === $this->id;
    }

    public function subscribe(User $subUser)
    {
        GitModel::beginTransaction();
        $this->_attributes['subscriptions'][$subUser->id] = $subUser->booksCount;
        $this->save(false);
        GitModel::commitTransaction('User ' . escapeshellarg($this->nickname) . ' subscribed to ' . escapeshellarg($subUser->nickname));
    }
    
    public function updateSubscription(User $subUser)
    {
        if (!isset($this->_attributes['subscriptions'][$subUser->id])) {
            return;
        }
        GitModel::beginTransaction();
        $this->_attributes['subscriptions'][$subUser->id] = $subUser->booksCount;
        $this->save(false);
        GitModel::commitTransaction('User ' . escapeshellarg($this->nickname) . ' updated subscription to ' . escapeshellarg($subUser->nickname));
    }

    public function deleteSubscription(User $subUser)
    {
        if (!isset($this->_attributes['subscriptions'][$subUser->id])) {
            return;
        }
        GitModel::beginTransaction();
        unset($this->_attributes['subscriptions'][$subUser->id]);
        $this->save(false);
        GitModel::commitTransaction('User ' . escapeshellarg($this->nickname) . ' unsubscribed from ' . escapeshellarg($subUser->nickname));
    }

    public function getCommitMessage($insert)
    {
        if ($insert) {
            return 'Added user ' . escapeshellarg($this->nickname);
        } else {
            return 'Updated user ' . escapeshellarg($this->nickname);
        }
    }
    
    protected static function _getLatestUsersIndexPath()
    {
        $indexLatestFile = 'latest_users.json';
        $indexLatestPath = \yii\helpers\BaseFileHelper::normalizePath(self::getRepoPath() . '/'. $indexLatestFile);
        return $indexLatestPath;
    }


    /**
     * Adds user to index of latest users
     */
    public function addToLatestUsers()
    {
        $file = self::_getLatestUsersIndexPath();
        $latestUsers = self::getLatestUsers();
        $lastUserUpdate = null;
        if (isset($latestUsers[$this->id])) {
            $lastUserUpdate = strtotime($latestUsers[$this->id]['updatedAt']);
        }


        $fields = ['id', 'nickname', 'booksCount', 'updatedAt'];
        $values = [];
        foreach ($fields as $name) {
            $values[$name] = $this->$name;
        }
        $latestUsers[$this->id] = $values;


        uasort($latestUsers, function($a, $b) {
            $at = strtotime($a['updatedAt']);
            $bt = strtotime($b['updatedAt']);
            if ($at > $bt) {
                return -1;
            } else if ($at < $bt) {
                return 1;
            }
            return 0;
        });

        $latestUsers = array_slice($latestUsers, 0, 30, true);

        if ($lastUserUpdate !== strtotime($this->updatedAt)) {
            $json = \yii\helpers\Json::encode($latestUsers, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            file_put_contents($file, $json);
            GitModel::commitFilesWithMessage($file, 'Updated latest users');
        }
    }

    public static function getLatestUsers()
    {
        $file = self::_getLatestUsersIndexPath();
        $users = null;
        if (is_file($file) && is_readable($file)) {
            $content = file_get_contents($file);
            $users = \yii\helpers\Json::decode($content, true);
        }
        if (!$users) {
            $users = [];
        }
        return $users;
    }
    
    public static function findIdByParseId($parseId)
    {
        $indexFile = 'parse_ids_users.csv';
        $indexFilePath = \yii\helpers\BaseFileHelper::normalizePath(self::getRepoPath() . '/'. $indexFile);
        if (is_readable($indexFilePath)) {
            $row = \app\helpers\Csv::findRowByFirstValue($indexFilePath, $parseId);
            if ($row) {
                return $row[1];
            }
        }
        return false;
    }
    
    public function copyBooksFromUser(User $otherUser)
    {
        GitModel::beginTransaction();
        $path = $otherUser->getRelativeBooksPath();
        $books = Book::findAllInPathByExtension($path);
        $i = 0;

        foreach ($books as $usersBook) {
            $book = new Book;
            $book->disableAfterSaveEvent();
            $attributes = $usersBook->getAllAttributes();
            unset($attributes['id']);
            $book->setAttributes($attributes);
            $book->setUser($this);
            $res = $book->save();
            if ($res) {
                $i++;
            }
        }

        if ($i) {
            $this->booksCount = $i;
            $this->save();
        }

        $wpath = $otherUser->getRelativeWishesPath();
        $wishes = Wish::findAllInPathByExtension($wpath);
        $j = 0;
        foreach ($wishes as $usersWish) {
            $wish = new Wish;
            $wish->disableAfterSaveEvent();
            $attributes = $usersWish->getAllAttributes();
            unset($attributes['id']);
            $wish->setAttributes($attributes);
            $wish->setUser($this);
            $res = $wish->save();
            if ($res) {
                $j++;
            }
        }

        if ($i || $j) {
            GitModel::commitTransaction("Copied book from " . escapeshellarg($otherUser->id) . " to " . escapeshellarg($this->id));
        }
    }

}
