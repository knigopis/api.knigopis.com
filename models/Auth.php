<?php

namespace app\models;

class Auth extends GitModel
{
    protected $_attributes = [
        'username' => null,
        'password' => null,
        'lang' => 'ru', // Default language
    ];

    protected static $_primaryKeyName = 'username';

    public function rules()
    {
        return [
            ['username', 'required'],
            ['username', 'string', 'min' => 4, 'max' => 30],
            ['username', 'match', 'pattern' => '/^[a-z0-9_.@-]+$/i'],
            ['password', 'required'],
            ['password', 'string', 'min' => 1, 'max' => 255],
            ['lang', 'string', 'max' => 255],
        ];
    }

    protected static function getAuthFilePath()
    {
        return self::getRepoPath() . '/auth.csv';
    }

    /**
     * @return User|null
     * @throws GitModelException
     * @throws \yii\base\Exception
     */
    public function register()
    {
        $inputLang = $this->_attributes['lang'];
        if (preg_match('/^ru/', $inputLang)) {
            $lang = 'ru';
        } else {
            $lang = 'en';
        }

        if ($this->validate()) {
            $row = \app\helpers\Csv::findRowByFirstValue(static::getAuthFilePath(), $this->_attributes['username']);
            if ($row) {
                $this->addError('username', 'Username is already in use. Please choose another one.');
                return null;
            }

            GitModel::beginTransaction();

            \app\helpers\Csv::addRow(static::getAuthFilePath(), [
                'username' => $this->_attributes['username'],
                'phash' => password_hash($this->_attributes['password'], PASSWORD_DEFAULT),
            ]);

            static::$_transactionFilesToCommit[] = 'auth.csv';

            $userData = static::getULoginData($this->_attributes['username'], $lang);

            $user = User::getUserByULoginData($userData);

            GitModel::commitTransaction($this->getCommitMessageForNewAuth());

            unset($this->_attributes['password']);

            return $user;
        }

        return null;
    }

    /**
     * @return User|false
     * @throws GitModelException
     * @throws \yii\base\Exception
     */
    public function login()
    {
        $row = \app\helpers\Csv::findRowByFirstValue(static::getAuthFilePath(), $this->_attributes['username']);
        if (!$row) {
            return false;
        }

        $phash = $row[1];

        if (!password_verify($this->_attributes['password'], $phash)) {
            return false;
        }

        $inputLang = $this->_attributes['lang'];
        if (preg_match('/^ru/', $inputLang)) {
            $lang = 'ru';
        } else {
            $lang = 'en';
        }

        $userData = static::getULoginData($this->_attributes['username'], $lang);

        return User::getUserByULoginData($userData);
    }

    public function getCommitMessageForNewAuth()
    {
        return "Added auth " . escapeshellarg($this->_attributes['username']);
    }

    protected static function getULoginData($username, $lang)
    {
        return [
            'nickname' => $username,
            'photo' => null,
            'profile' => null,
            'uid' => $username, // Using username as UID
            'identity' => 'local/' . $username,
            'network' => 'local',
            'lang' => $lang,
        ];
    }
}
