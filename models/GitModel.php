<?php

namespace app\models;

class GitModel extends \yii\base\Model
{

    protected $_attributes = array();
    protected $_oldAttributes = array();
    protected $_isNewRecord = true;
    protected static $_extension = '.json';
    protected static $_dateFormat = 'Y-m-d H:i:s';
    protected static $_primaryKeyName = 'id';
    protected $_filesToCommit = [];
    protected static $_isIntransaction = false;
    protected static $_transactionFilesToCommit = [];
    protected $_disableAfterSaveEvent = false;

    public static function getRepoPath()
    {
        $path = \Yii::$app->params['repoPath'];
        if (!is_readable($path)) {
            throw new GitModelException("RepoPath '$path' is not readable git repository");
        }
        return $path;
    }

    public function getRelativePath()
    {
        return '';
    }

    public function getPrimaryKeyValue()
    {
        if (isset($this->_attributes[self::$_primaryKeyName])) {
            return $this->_attributes[self::$_primaryKeyName];
        }
    }

    public function getBaseFileName()
    {
        $pkValue = $this->getPrimaryKeyValue();
        if (empty($pkValue)) {
            throw new GitModelException("Primary key '{$this->_primaryKeyName}' is empty");
        }
        return $pkValue . self::$_extension;
    }

    public function getRelativeFilePath()
    {
        $path = rtrim($this->getRelativePath(), '/') . '/';
        $path = \yii\helpers\BaseFileHelper::normalizePath($path . $this->getBaseFileName(), '/');
        return $path;
    }

    public function getFilePath()
    {
        $filepath = \yii\helpers\BaseFileHelper::normalizePath(self::getRepoPath() . '/' . $this->getRelativeFilePath(), '/');
        return $filepath;
    }
    
    public function beforeSave()
    {
        return true;
    }

    public function save($runValidation = true)
    {
        if (!$this->beforeSave()) {
            return false;
        }

        $dir = self::getRepoPath();
        if (!$dir) {
            throw new GitModelException("RepoPath is empty");
        }

        if ($this->getIsNewRecord()) {
            $res = $this->insert($runValidation);
        } else {
            $res = $this->update($runValidation) !== false;
        }
        return $res;
    }

    protected function updateIndexFile()
    {
        $indexFile = static::getIndexFilePath();
        if ($indexFile) {
            \app\helpers\Csv::addRow($indexFile, [$this->getPrimaryKeyValue(), $this->getRelativeFilePath()]);
            $this->_filesToCommit[] = static::getRelativeIndexFilePath();
        }
    }

    public static function getRelativeIndexFilePath()
    {
        return strtolower(get_called_class()) . '.csv';
    }

    public static function getIndexFilePath()
    {
        $relativePath = static::getRelativeIndexFilePath();
        if ($relativePath) {
            return static::getRepoPath() . '/' . $relativePath;
        }
        return false;
    }

    protected function generateId()
    {
        $sec = new \yii\base\Security;
        return $sec->generateRandomString(10);
    }

    protected function _innerInsert()
    {
        if (!$this->getPrimaryKeyValue()) {
            $this->_attributes[self::$_primaryKeyName] = $this->generateId();
        }
        $path = $this->getFilePath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            \yii\helpers\BaseFileHelper::createDirectory($dir);
        }
        if (!is_writable($dir)) {
            throw new GitModelException("Dir '$dir' is not writable");
        }

        $bytes = file_put_contents($path, $this->jsonEncode());

        $this->_filesToCommit[] = $this->getRelativeFilePath();
        return $bytes !== false;
    }

    /**
     * 
     * @param boolean $runValidation
     * @return boolean
     */
    protected function insert($runValidation)
    {
        if (!$runValidation || $this->validate()) {

            if (empty($this->_attributes['createdAt'])) {
                $this->_attributes['createdAt'] = date(self::$_dateFormat);
                $this->_attributes['updatedAt'] = date(self::$_dateFormat);
            }

            $res = $this->_innerInsert();
            if ($res) {
                $this->_isNewRecord = false;
                $this->updateIndexFile();
                if (!$this->_disableAfterSaveEvent) {
                    $this->afterSave(true, $this->_attributes);
                }
                $this->commit(true);
            }
            return $res;
        }
    }

    protected function jsonEncode()
    {
        $data = $this->_attributes;
        $notNullData = array();
        foreach ($data as $name => $value) {
            if ($value !== null) {
                $notNullData[$name] = $value;
            }
        }
        return \yii\helpers\Json::encode($notNullData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * 
     * @param boolean $runValidation
     * @return int|boolean
     */
    protected function update($runValidation)
    {
        if (!$runValidation || $this->validate()) {

            $changedAttributes = [];
            foreach ($this->_attributes as $name => $value) {
                if (!array_key_exists($name, $this->_oldAttributes) || $this->_oldAttributes[$name] !== $value) {
                    $changedAttributes[$name] = $value;
                }
            }

            if ($changedAttributes) {

                $this->_attributes['updatedAt'] = date(self::$_dateFormat);
                $changedAttributes['updatedAt'] = $this->_attributes['updatedAt'];

                $res = $this->_innerInsert();
                if ($res) {
                    if (!$this->_disableAfterSaveEvent) {
                        $this->afterSave(false, $changedAttributes);
                    }
                    $this->commit(false);
                }
                return count($changedAttributes);
            } else {
                return 0;
            }
        }
        return false;
    }

    public function afterFind()
    {
        
    }

    public static function findOne($fullpath)
    {
        $fullpath = \yii\helpers\BaseFileHelper::normalizePath($fullpath, '/');
        if (is_file($fullpath) && is_readable($fullpath)) {
            $content = file_get_contents($fullpath);
            $data = \yii\helpers\Json::decode($content);
            $record = static::instantiate($data);
            self::populateRecord($record, $data);
            $record->setIsNewRecord(false);
            $record->afterFind();
            return $record;
        }
    }

    public static function findAllInPathByExtension($relativePath, $extension = null)
    {
        if ($extension === null) {
            $extension = self::$_extension;
        }
        $path = self::getRepoPath() . '/' . $relativePath;
        $fullpath = \yii\helpers\BaseFileHelper::normalizePath($path, '/');
        $models = [];
        if (is_dir($fullpath)) {
            $files = \yii\helpers\BaseFileHelper::findFiles($fullpath, ['only' => ['*' . $extension], 'recursive' => false]);
            foreach ($files as $fullpath) {
                $models[] = static::findOne($fullpath);
            }
        }
        return $models;
    }

    public function getCommitMessage($insert)
    {
        if ($insert) {
            return 'Added new record';
        } else {
            return 'Updated a record';
        }
    }

    /**
     * 
     * @param boolean $insert
     * @throws GitModelException
     */
    public function commit($insert)
    {
        $message = $this->getCommitMessage($insert);
        $this->commitWithMessage($message);
    }

    /**
     * 
     * @param string $message
     */
    public function commitWithMessage($message)
    {
        if (empty($this->_filesToCommit)) {
            return;
        }

        self::commitFilesWithMessage($this->_filesToCommit, $message);
        $this->_filesToCommit = [];
    }

    /**
     * 
     * @param array $files
     * @param string $message
     * @return type
     */
    public static function commitFilesWithMessage($files, $message)
    {
        if (!is_array($files)) {
            $files = [$files];
        }
        if (self::$_isIntransaction) {
            self::$_transactionFilesToCommit = array_merge(self::$_transactionFilesToCommit, $files);
            return;
        }

        array_unique($files);
        
        if (empty($files)) {
            return;
        }

        $cdCommand = 'cd ' . self::getRepoPath();

        static::_exec("$cdCommand && git config user.email \"" . \Yii::$app->params['gitEmail'] . "\"");
        static::_exec("$cdCommand && git config user.name \"" . \Yii::$app->params['gitName'] . "\"");

        foreach ($files as $file) {
            static::_exec("$cdCommand && git add $file");
        }

        $command = "$cdCommand && git commit -m \"$message\"";
        static::_exec($command, true);
    }
    
    public static function commitDeletedFilesWithMessage($files, $message)
    {
        if (!is_array($files)) {
            $files = [$files];
        }

        array_unique($files);
        
        if (empty($files)) {
            return;
        }

        $cdCommand = 'cd ' . self::getRepoPath();

        static::_exec("$cdCommand && git config user.email \"" . \Yii::$app->params['gitEmail'] . "\"");
        static::_exec("$cdCommand && git config user.name \"" . \Yii::$app->params['gitName'] . "\"");

        foreach ($files as $file) {
            static::_exec("$cdCommand && git rm $file");
        }

        $command = "$cdCommand && git commit -m \"$message\"";
        static::_exec($command, true);
    }

    /**
     * 
     * @param string $command
     * @param boolean $execptionOnFail
     * @param array $output
     * @return int
     * @throws GitModelException
     */
    protected static function _exec($command, $execptionOnFail = false, &$output = null)
    {
        $status = null;
        exec($command . " 2>&1", $output, $status);
        if ($execptionOnFail && $status !== 0) {
            \Yii::error(implode(', ', $output));
            if (YII_DEBUG) {
                throw new GitModelException("Command '$command' failed: " . implode(', ', $output));
            } else {
                throw new GitModelException("Command '$command' failed");
            }
        }
        return $status;
    }

    public function fields()
    {
        return array_keys($this->_attributes);
    }

    public function getAllAttributes()
    {
        return array_merge($this->_attributes, $this->attributes());
    }

    /**
     * 
     * @param type $id
     * @return static
     * @throws GitModelException
     */
    public static function findById($id)
    {
        $indexFile = self::getIndexFilePath();
        if (!is_file($indexFile) || !is_readable($indexFile)) {
            throw new GitModelException("Index file '$indexFile' is not readable");
        }
        $row = \app\helpers\Csv::findRowByFirstValue($indexFile, $id);
        $fullpath = self::getRepoPath() . '/' . $row[1];
        return self::findOne($fullpath);
    }

    /**
     * Returns a value indicating whether the current record is new.
     * @return boolean whether the record is new and should be inserted when calling [[save()]].
     */
    public function getIsNewRecord()
    {
        return $this->_isNewRecord;
    }

    /**
     * Sets the value indicating whether the record is new.
     * @param boolean $value whether the record is new and should be inserted when calling [[save()]].
     * @see getIsNewRecord()
     */
    public function setIsNewRecord($value)
    {
        $this->_isNewRecord = $value;
    }

    /**
     * PHP getter magic method.
     * This method is overridden so that attributes and related objects can be accessed like properties.
     *
     * @param string $name property name
     * @throws \yii\base\InvalidParamException if relation name is wrong
     * @return mixed property value
     * @see getAttribute()
     */
    public function __get($name)
    {
        if (isset($this->_attributes[$name]) || array_key_exists($name, $this->_attributes)) {
            return $this->_attributes[$name];
        } elseif ($this->hasAttribute($name)) {
            return null;
        } else {
            return parent::__get($name);
        }
    }

    /**
     * PHP setter magic method.
     * This method is overridden so that AR attributes can be accessed like properties.
     * @param string $name property name
     * @param mixed $value property value
     */
    public function __set($name, $value)
    {
        if ($this->hasAttribute($name)) {
            $this->_attributes[$name] = $value;
        } else {
            parent::__set($name, $value);
        }
    }

    /**
     * Checks if a property value is null.
     * This method overrides the parent implementation by checking if the named attribute is null or not.
     * @param string $name the property name or the event name
     * @return boolean whether the property value is null
     */
    public function __isset($name)
    {
        try {
            return $this->__get($name) !== null;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Sets a component property to be null.
     * This method overrides the parent implementation by clearing
     * the specified attribute value.
     * @param string $name the property name or the event name
     */
    public function __unset($name)
    {
        if ($this->hasAttribute($name)) {
            unset($this->_attributes[$name]);
        } elseif (array_key_exists($name, $this->_related)) {
            unset($this->_related[$name]);
        } elseif ($this->getRelation($name, false) === null) {
            parent::__unset($name);
        }
    }

    /**
     * Returns a value indicating whether the model has an attribute with the specified name.
     * @param string $name the name of the attribute
     * @return boolean whether the model has an attribute with the specified name.
     */
    public function hasAttribute($name)
    {
        return array_key_exists($name, $this->_attributes) || in_array($name, $this->attributes());
    }

    /**
     * Returns the named attribute value.
     * If this record is the result of a query and the attribute is not loaded,
     * null will be returned.
     * @param string $name the attribute name
     * @return mixed the attribute value. Null if the attribute is not set or does not exist.
     * @see hasAttribute()
     */
    public function getAttribute($name)
    {
        return isset($this->_attributes[$name]) ? $this->_attributes[$name] : null;
    }

    /**
     * Sets the named attribute value.
     * @param string $name the attribute name
     * @param mixed $value the attribute value.
     * @throws InvalidParamException if the named attribute does not exist.
     * @see hasAttribute()
     */
    public function setAttribute($name, $value)
    {
        if ($this->hasAttribute($name)) {
            $this->_attributes[$name] = $value;
        } else {
            throw new InvalidParamException(get_class($this) . ' has no attribute named "' . $name . '".');
        }
    }

    /**
     * 
     * @param static $record
     * @param array $row
     */
    public static function populateRecord($record, $row)
    {
        $columns = array_flip($record->attributes());
        foreach ($row as $name => $value) {
            if (isset($columns[$name])) {
                $record->_attributes[$name] = $value;
            } elseif ($record->canSetProperty($name)) {
                $record->$name = $value;
            } else if ($record->hasAttribute($name)) {
                $record->$name = $value;
            }
        }
        $record->_setOldStorageAttributes($record->getStorageAttributes());
    }

    /**
     * @return static the newly created record
     */
    public static function instantiate($row)
    {
        return new static;
    }

    /**
     * 
     * @param boolean $insert new record or updated
     * @param array $changedAttributes changed attributes with new values
     */
    public function afterSave($insert, $changedAttributes)
    {
        
    }

    /**
     * Begins transaction for one commit with several models
     */
    public static function beginTransaction()
    {
        self::$_isIntransaction = true;
    }

    /**
     * Commits transaction. Specify some model for context
     * 
     * @param \app\models\GitModel $contextModel
     * @param string $message
     */
    public static function commitTransaction($message)
    {
        self::$_isIntransaction = false;
        self::commitFilesWithMessage(self::$_transactionFilesToCommit, $message);
    }

    public static function addFileToTransactionCommit($file)
    {
        self::$_transactionFilesToCommit[] = $file;
    }

    public function beforeDelete()
    {
        return true;
    }
    
    public function afterDelete()
    {
        
    }

    public function delete()
    {
        if (!$this->beforeDelete()) {
            return false;
        }
        $res = unlink($this->getFilePath());
        GitModel::commitDeletedFilesWithMessage($this->getFilePath(), $this->getCommitMessageForDeletedModel());
        $this->afterDelete();
        return $res;
    }
    
    public function getCommitMessageForDeletedModel()
    {
        return 'Deleted a model';
    }

    public static function getDateFormat()
    {
        return static::$_dateFormat;
    }

    public function _setOldStorageAttributes($values)
    {
        $this->_oldAttributes = $values;
    }

    public function getStorageAttributes()
    {
        return $this->_attributes;
    }
    
    public function disableAfterSaveEvent() {
        $this->_disableAfterSaveEvent = true;
    }
    
    public function enableAfterSaveEvent() {
        $this->_disableAfterSaveEvent = false;
    }

}

class GitModelException extends \yii\base\Exception
{
    
}
