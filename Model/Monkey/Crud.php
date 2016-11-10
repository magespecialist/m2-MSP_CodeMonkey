<?php
/**
 * IDEALIAGroup srl - MageSpecialist
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to info@idealiagroup.com so we can send you a copy immediately.
 *
 * @category   MSP
 * @package    MSP_CodeMonkey
 * @copyright  Copyright (c) 2016 IDEALIAGroup srl - MageSpecialist (http://www.magespecialist.it)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace MSP\CodeMonkey\Model\Monkey;

use MSP\CodeMonkey\Model\Database;
use MSP\CodeMonkey\Model\Filesystem;
use MSP\CodeMonkey\Model\ModuleManager;
use MSP\CodeMonkey\Model\Template;
use MSP\CodeMonkey\Model\DiManager;

class Crud
{
    protected $moduleManager;
    protected $filesystem;
    protected $template;
    protected $database;

    public function __construct(
        ModuleManager $moduleManager,
        DiManager $diManager,
        Filesystem $filesystem,
        Template $template,
        Database $database
    ) {
        $this->moduleManager = $moduleManager;
        $this->filesystem = $filesystem;
        $this->template = $template;
        $this->database = $database;
        $this->diManager = $diManager;
    }

    /**
     * Convert to camel case
     * @param $string
     * @return mixed
     */
    protected function toCamelCase($string)
    {
        return str_replace(' ', '', ucwords(preg_replace('/[^a-zA-Z0-9]+/', ' ', $string)));
    }

    /**
     * Create getter function
     * @param $interfaceClass
     * @param $tableName
     * @param $columnName
     * @param $columnInfo
     * @param bool $interface
     * @return string
     */
    public function createGetter($interfaceClass, $tableName, $columnName, $columnInfo, $interface = false)
    {
        $out = [];
        if ($this->database->isPrimaryKey($tableName, $columnName)) {
            $methodName = 'getId';
        } else {
            $methodName = "get" . $this->toCamelCase($columnName);
        }

        $constName = $interfaceClass.'::'.$this->getConstName($tableName, $columnName);

        if ($interface) {
            $out[] = "    /**";
            $out[] = "     * Get value for $columnName";
            $out[] = "     * @return " . $this->database->getTypeByColumnType($columnInfo['DATA_TYPE']);
            $out[] = "     */";
            $out[] = "    public function $methodName" . "();";
        } else {
            $out[] = "    public function $methodName" . "()";
            $out[] = "    {";
            $out[] = "        return \$this->getData($constName);";
            $out[] = "    }";
        }

        return implode("\n", $out);
    }

    /**
     * Create getter function
     * @param $interfaceClass
     * @param $tableName
     * @param $columnName
     * @param $columnInfo
     * @param bool $interface
     * @return string
     */
    public function createSetter($interfaceClass, $tableName, $columnName, $columnInfo, $interface = false)
    {
        $out = [];
        if ($this->database->isPrimaryKey($tableName, $columnName)) {
            $methodName = 'setId';
        } else {
            $methodName = "set" . $this->toCamelCase($columnName);
        }

        $constName = $interfaceClass.'::'.$this->getConstName($tableName, $columnName);

        if ($interface) {
            $out[] = "    /**";
            $out[] = "     * Set value for $columnName";
            $out[] = "     * @param ".$this->database->getTypeByColumnType($columnInfo['DATA_TYPE'])." \$value";
            $out[] = "     * @return \$this";
            $out[] = "     */";
            $out[] = "    public function $methodName"."(\$value);";
        } else {
            $out[] = "    public function $methodName"."(\$value)";
            $out[] = "    {";
            $out[] = "        \$this->setData($constName, \$value);";
            $out[] = "        return \$this;";
            $out[] = "    }";
        }

        return implode("\n", $out);
    }

    /**
     * Return column const name
     * @param $tableName
     * @param $columnName
     * @return string
     */
    public function getConstName($tableName, $columnName)
    {
        return $this->database->isPrimaryKey($tableName, $columnName) ? 'ID' : strtoupper($columnName);
    }

    /**
     * Create crud model and return a list of created/modified files
     * @param $moduleName
     * @param $entityName
     * @param $tableName
     * @return string[]
     */
    public function create($moduleName, $entityName, $tableName)
    {
        $modelClassName = $this->moduleManager->getClassName($moduleName, 'Model\\'.$entityName);
        $modelFile = $this->moduleManager->getClassFile($moduleName, 'Model\\'.$entityName);

        $resourceClassName = $this->moduleManager->getClassName($moduleName, 'Model\\ResourceModel\\'.$entityName);
        $resourceFile = $this->moduleManager->getClassFile($moduleName, 'Model\\ResourceModel\\'.$entityName);

        $collectionClassName =
            $this->moduleManager->getClassName($moduleName, 'Model\\ResourceModel\\'.$entityName.'\\Collection');
        $collectionFile =
            $this->moduleManager->getClassFile($moduleName, 'Model\\ResourceModel\\'.$entityName.'\\Collection');

        $dataClassName = $this->moduleManager->getClassName($moduleName, 'Api\\Data\\'.$entityName.'Interface');
        $dataFile = $this->moduleManager->getClassFile($moduleName, 'Api\\Data\\'.$entityName.'Interface');

        $repoIfClassName = $this->moduleManager->getClassName($moduleName, 'Api\\'.$entityName.'RepositoryInterface');
        $repoIfFile = $this->moduleManager->getClassFile($moduleName, 'Api\\'.$entityName.'RepositoryInterface');

        $repoClassName = $this->moduleManager->getClassName($moduleName, 'Model\\'.$entityName.'Repository');
        $repoFile = $this->moduleManager->getClassFile($moduleName, 'Model\\'.$entityName.'Repository');

        $involvedFiles = [
            $modelFile,
            $resourceFile,
            $collectionFile,
            $dataFile,
            $repoFile,
            $repoIfFile,
        ];

        $this->filesystem->assertNotExisting($involvedFiles);
        $involvedFiles[] = $this->diManager->getDiFile($moduleName);

//        // Model class
//        $columns = $this->database->getColumns($tableName);
//        $classInfo = $this->moduleManager->getClassInfo($modelClassName);
//        $getterList = [];
//        $setterList = [];
//        foreach ($columns as $columnName => $columnInfo) {
//            $getterList[] = $this->createGetter($dataClassName, $tableName, $columnName, $columnInfo, false);
//            $setterList[] = $this->createSetter($dataClassName, $tableName, $columnName, $columnInfo, false);
//        }
//
//        $this->template->createFromTemplate('crud/Model/Model', $modelFile, [
//            'namespace' => $classInfo['namespace'],
//            'class' => $classInfo['class'],
//            'interface' => $dataClassName,
//            'resource_model' => $resourceClassName,
//            'getter_list' => implode("\n\n", $getterList),
//            'setter_list' => implode("\n\n", $setterList),
//        ]);
//
//        // Resource class
//        $classInfo = $this->moduleManager->getClassInfo($resourceClassName);
//        $primaryKey = $this->database->getPrimaryKey($tableName);
//        $this->template->createFromTemplate('crud/Model/ResourceModel/Model', $resourceFile, [
//            'namespace' => $classInfo['namespace'],
//            'class' => $classInfo['class'],
//            'table' => $this->database->getTableName($tableName),
//            'primary_key' => $primaryKey,
//        ]);
//
//        // Collection class
//        $classInfo = $this->moduleManager->getClassInfo($collectionClassName);
//        $this->template->createFromTemplate('crud/Model/ResourceModel/Model/Collection', $collectionFile, [
//            'namespace' => $classInfo['namespace'],
//            'class' => $classInfo['class'],
//            'model' => $modelClassName,
//            'resource_model' => $resourceClassName,
//            'primary_key' => $primaryKey,
//        ]);
//
//        // Interface
//        $classInfo = $this->moduleManager->getClassInfo($dataClassName);
//        $constList = [];
//        $getterList = [];
//        $setterList = [];
//
//        foreach ($columns as $columnName => $columnInfo) {
//            $constList[] = "    const ".$this->getConstName($tableName, $columnName)." = '$columnName';";
//            $getterList[] = $this->createGetter($dataClassName, $tableName, $columnName, $columnInfo, true);
//            $setterList[] = $this->createSetter($dataClassName, $tableName, $columnName, $columnInfo, true);
//        }
//
//        $this->template->createFromTemplate('crud/Api/Data/Interface', $dataFile, [
//            'namespace' => $classInfo['namespace'],
//            'class' => $classInfo['class'],
//            'const_list' => implode("\n", $constList),
//            'getter_list' => implode("\n\n", $getterList),
//            'setter_list' => implode("\n\n", $setterList),
//        ]);
//
//        // Repo file
//        $classInfo = $this->moduleManager->getClassInfo($repoClassName);
//        $this->template->createFromTemplate('crud/Model/Repository', $repoFile, [
//            'namespace' => $classInfo['namespace'],
//            'class' => $classInfo['class'],
//            'interface' => $repoIfClassName,
//            'data_interface' => $dataClassName,
//            'resource' => $resourceClassName,
//        ]);
//
//        // Repo interface
//        $classInfo = $this->moduleManager->getClassInfo($repoIfClassName);
//        $this->template->createFromTemplate('crud/Api/RepositoryInterface', $repoIfFile, [
//            'namespace' => $classInfo['namespace'],
//            'class' => $classInfo['class'],
//            'data_interface' => $dataClassName,
//        ]);

        // Inject preferences
        $this->diManager->createPreference($moduleName, $dataClassName, $modelClassName);
        $this->diManager->createPreference($moduleName, $repoIfClassName, $repoClassName);

        return $involvedFiles;
    }
}
