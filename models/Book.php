<?php

namespace app\models;

class Book extends GitModel
{

    protected $_attributes = array(
        'id' => null,
        'lang' => null,
        'userId' => null,
        'createdAt' => null,
        'updatedAt' => null,
        'title' => null,
        'author' => null,
        'readDay' => null,
        'readMonth' => null,
        'readYear' => null,
        'notes' => null,
    );
    
    /**
     * @var User
     */
    protected $user;

    public function rules()
    {
        return [
            [['author', 'notes', 'readDay', 'readMonth', 'readYear', 'title'], 'filter', 'filter' => 'trim'],
            ['author', 'string', 'max' => 140],
            ['title', 'string', 'max' => 140],
            ['notes', 'string', 'max' => 2000],
            ['readDay', 'integer', 'min' => 1, 'max' => 31],
            ['readMonth', 'integer', 'min' => 1, 'max' => 12],
            ['readYear', 'integer', 'min' => 1900, 'max' => 2999],
            ['lang', 'in', 'range' => ['ru', 'en']],
        ];
    }

    public function getRelativePath()
    {
        $userId = $this->_attributes['userId'];
        if (!$userId) {
            throw new GitModelException("userId is empty");
        }
        $userId3 = substr($userId, 0, 3);
        $path = "users/$userId3/$userId/books";
        return $path;
    }

    public static function getRelativeIndexFilePath()
    {
        return 'books.csv';
    }

    public function setUser(User $user)
    {
        $this->user = $user;
        $this->userId = $user->id;
        $this->lang = $user->lang;
    }
    
    public function beforeSave()
    {
        foreach (['readDay', 'readMonth', 'readYear'] as $attr) {
            if (isset($this->_attributes[$attr]) && is_numeric($this->_attributes[$attr])) {
                $this->_attributes[$attr] = intval($this->_attributes[$attr]);
            }
        }
        return true;
    }
    
    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);
        if ($insert) {
            $this->user->booksCount++;
            $this->user->save();
        }
        if ($insert || $changedAttributes) {
            static::renderList($this->user);
        }
        if ($insert) {
            $this->addToLatestBooks();
        }
        $userTrustTime = 3600 * 24 * 7;
        if ($insert && mb_strlen(trim($this->notes)) > 2 && (time() - strtotime($this->user->createdAt) > $userTrustTime)) {
            $this->addToLatestBooksWithNotes();
        }
        if ($insert) {
            self::renderMainPage();
        }
    }
    
    public function afterDelete()
    {
        $this->user->booksCount--;
        $this->user->booksCount = max(0, $this->user->booksCount);
        $this->user->save();
        static::renderList($this->user);
    }

    public static function renderList(User $user)
    {
        $path = $user->getRelativeBooksPath();
        $models = static::findAllInPathByExtension($path);
        if ($user->lang === 'ru') {
            \Yii::$app->language = 'ru';
        }
        $md = \Yii::$app->view->renderFile('@app/views/books_md.php', ['models' => $models, 'user' => $user]);
        $file = $path . '/../README.md';
        $fullpath = \yii\helpers\BaseFileHelper::normalizePath(self::getRepoPath() . '/'. $file, '/');
        file_put_contents($fullpath, $md);
        GitModel::commitFilesWithMessage($file, 'Updated books list by ' . escapeshellarg($user->nickname));
    }

    public function afterFind()
    {
        parent::afterFind();
        foreach (['readDay', 'readMonth', 'readYear'] as $attr) {
            if (isset($this->_attributes[$attr]) && is_numeric($this->_attributes[$attr])) {
                $this->_attributes[$attr] = intval($this->_attributes[$attr]);
            }
        }
        $this->user = User::findById($this->userId);
    }
    
    public function getCommitMessageForNewBook()
    {
        return "Added book " . escapeshellarg($this->title) . " by " . escapeshellarg($this->user->nickname);
    }

    public function getCommitMessageForUpdatedBook()
    {
        return "Updated book " . escapeshellarg($this->title) . " by " . escapeshellarg($this->user->nickname);
    }

    public function getCommitMessageForDeletedModel()
    {
        return "Deleted a book by " . escapeshellarg($this->user->nickname);
    }

    public function fields()
    {
        $attrs = array_keys($this->_attributes);
        $attrs[] = 'user';
        return $attrs;
    }
    
    public function getReadDateForList()
    {
        $date = '';
        if ($this->readYear && $this->readMonth) {
            $month = $this->readMonth;
            if ($month < 10) {
                $month = '0' . $month;
            }
            $date = $this->readYear . '-' . $month;
        }
        if ($this->readYear && $this->readDay) {
            $day = $this->readDay;
            if ($day < 10) {
                $day = '0' . $day;
            }
            $date .= '-' . $day;
        }
        return $date;
    }
    
    public static function sortListByDate(&$models)
    {
        usort($models, function($a, $b) {
            if ($a->readYear > $b->readYear) {
                return -1;
            }
            if ($a->readYear < $b->readYear) {
                return 1;
            }
            if (!$a->readMonth && $b->readMonth) {
                return 1;
            }
            if ($a->readMonth && !$b->readMonth) {
                return -1;
            }
            if ($a->readMonth < $b->readMonth) {
                return 1;
            }
            if ($a->readMonth > $b->readMonth) {
                return -1;
            }
            if ($a->readDay < $b->readDay) {
                return 1;
            }
            if ($a->readDay > $b->readDay) {
                return -1;
            }
            if (strtotime($a->createdAt) > strtotime($b->createdAt)) {
                return -1;
            }
            if (strtotime($a->createdAt) < strtotime($b->createdAt)) {
                return 1;
            }
            return 0;
        });
    }
    
    public static function splitListByYears($books)
    {
        $byYears = [];
        foreach ($books as $book) {
            $year = $book->readYear;
            if (!$year || !is_numeric($year)) {
                $year = 'other';
            }
            
            if (!isset($byYears[$year])) {
                $byYears[$year] = [];
            }
            $byYears[$year][] = $book;
        }
        
        uksort($byYears, function($a, $b){
            if ($a === 'other') {
                return 1;
            } else if ($b === 'other') {
                return -1;
            }
            if ($a > $b) {
                return -1;
            } else if ($a < $b) {
                return 1;
            }
            return 0;
        });
        
        return $byYears;
    }

 
    /**
     * Returns path to index of latest books
     * 
     * @return string
     */
    protected static function _getLatestBooksIndexPath()
    {
        $indexLatestFile = 'latest_books.json';
        $indexLatestPath = \yii\helpers\BaseFileHelper::normalizePath(self::getRepoPath() . '/'. $indexLatestFile);
        return $indexLatestPath;
    }
    
    /**
     * Returns path to index of latest books with notes
     * 
     * @return string
     */
    protected static function _getLatestBooksWithNotesIndexPath()
    {
        $indexLatestFile = 'latest_books_with_notes.json';
        $indexLatestPath = \yii\helpers\BaseFileHelper::normalizePath(self::getRepoPath() . '/'. $indexLatestFile);
        return $indexLatestPath;
    }

    /**
     * Adds this book to index of latest books
     */
    public function addToLatestBooks()
    {
        $file = self::_getLatestBooksIndexPath();
        $this->_addToIndexListByCreateDate($file);
    }
    
    /**
     * Adds this book to index of latest books
     */
    public function addToLatestBooksWithNotes()
    {
        $file = self::_getLatestBooksWithNotesIndexPath();
        $this->_addToIndexListByCreateDate($file);
        $this->renderLatestBooksWithNotes();
    }

    /**
     * Adds book to index
     * @param string index file
     */
    protected function _addToIndexListByCreateDate($file)
    {
        
        $latestBooks = self::_getIndexBooksByFile($file);
        
        if (isset($latestBooks[$this->id])) {
            return;
        }

        $values = [];
        foreach (['id', 'title', 'author', 'notes', 'createdAt'] as $name) {
            $values[$name] = $this->$name;
        }
        $values['user'] = [];
        foreach (['id', 'nickname', 'booksCount'] as $userField) {
            $values['user'][$userField] = $this->user->$userField;
        }
        $latestBooks[$this->id] = $values;


        uasort($latestBooks, function($a, $b) {
            $at = strtotime($a['createdAt']);
            $bt = strtotime($b['createdAt']);
            if ($at > $bt) {
                return -1;
            } else if ($at < $bt) {
                return 1;
            }
            return 0;
        });
        
        $deduplicatedBooks = [];
        $added = [];
        foreach ($latestBooks as $id => $book) {
            $key = md5($book['title'] . $book['user']['id']);
            if (!in_array($key, $added)) {
                $deduplicatedBooks[$id] = $book;
                $added[] = $key;
            }
        }
        $latestBooks = $deduplicatedBooks;

        $latestBooks = array_slice($latestBooks, 0, 30, true);

        $json = \yii\helpers\Json::encode($latestBooks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        file_put_contents($file, $json);
        GitModel::commitFilesWithMessage($file, 'Updated latest books');
    }
    
    /**
     * Returns list of latest books
     * @return array
     */
    public static function getLatestBooks()
    {
        $file = self::_getLatestBooksIndexPath();
        return self::_getIndexBooksByFile($file);
    }
    
    /**
     * Returns list of latest books with notes
     * @return array
     */
    public static function getLatestBooksWithNotes()
    {
        $file = self::_getLatestBooksWithNotesIndexPath();
        return self::_getIndexBooksByFile($file);
    }

    protected static function _getIndexBooksByFile($file)
    {
        $books = null;
        if (is_file($file) && is_readable($file)) {
            $content = file_get_contents($file);
            $books = \yii\helpers\Json::decode($content, true);
        }
        if (!$books) {
            $books = [];
        }
        return $books;
    }

    public static function renderLatestBooksWithNotes()
    {
        $latestBooks = self::getLatestBooksWithNotes();
        $md = \Yii::$app->view->renderFile('@app/views/latest_books_with_notes_md.php', ['latestBooks' => $latestBooks]);
        $file = 'latest_books_with_notes.md';
        $fullpath = \yii\helpers\BaseFileHelper::normalizePath(self::getRepoPath() . '/' . $file, '/');
        file_put_contents($fullpath, $md);
        GitModel::commitFilesWithMessage($file, 'Rendered latest books with notes');
    }
    
    public static function renderMainPage()
    {
        $latestBooks = self::getLatestBooksWithNotes();
        $latestUsers = User::getLatestUsers();
        $md = \Yii::$app->view->renderFile('@app/views/main_page_md.php', ['latestBooks' => $latestBooks, 'latestUsers' => $latestUsers]);
        $file = 'README.md';
        $fullpath = \yii\helpers\BaseFileHelper::normalizePath(self::getRepoPath() . '/' . $file, '/');
        file_put_contents($fullpath, $md);
        GitModel::commitFilesWithMessage($file, 'Rendered README.md');
    }

}
