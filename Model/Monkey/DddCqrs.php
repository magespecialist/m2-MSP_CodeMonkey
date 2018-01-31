<?php
/**
 * MageSpecialist
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to info@magespecialist.it so we can send you a copy immediately.
 *
 * @category   MSP
 * @package    MSP_CodeMonkey
 * @copyright  Copyright (c) 2018 Skeeller srl (http://www.magespecialist.it)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace MSP\CodeMonkey\Model\Monkey;

use MSP\CodeMonkey\Model\Database;
use MSP\CodeMonkey\Model\DiManager;
use MSP\CodeMonkey\Model\Filesystem;
use MSP\CodeMonkey\Model\ModuleManager;
use MSP\CodeMonkey\Model\PhpCode;
use MSP\CodeMonkey\Model\Template;

/**
 * @SuppressWarnings(PHPMD.ExcessiveParameterList)
 * @SuppressWarnings(PHPMD.TooManyFields)
 */
class DddCqrs
{
    private $moduleName;
    private $entityName;
    private $entityVar;
    private $tableName;
    private $overwrite;
    private $primaryKey;
    private $columns = [];
    private $indexedColumns = [];
    private $classes = [];
    private $outFiles = [];

    /**
     * @var PhpCode
     */
    private $phpCode;

    /**
     * @var Template
     */
    private $template;

    /**
     * @var ModuleManager
     */
    private $moduleManager;

    /**
     * @var Database
     */
    private $database;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var DiManager
     */
    private $diManager;

    public function __construct(
        Template $template,
        PhpCode $phpCode,
        ModuleManager $moduleManager,
        DiManager $diManager,
        Database $database,
        Filesystem $filesystem,
        $moduleName,
        $entityName,
        $tableName,
        $overwrite
    ) {
        $this->phpCode = $phpCode;
        $this->moduleName = $moduleName;
        $this->entityName = $entityName;
        $this->tableName = $tableName;
        $this->template = $template;
        $this->moduleManager = $moduleManager;
        $this->database = $database;
        $this->filesystem = $filesystem;
        $this->overwrite = $overwrite;
        $this->diManager = $diManager;
    }

    /**
     * Generate class names
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function prepare()
    {
        $this->entityName = $this->phpCode->toCamelCase($this->entityName);
        $this->entityVar = lcfirst($this->entityName);
        $this->primaryKey = $this->database->getPrimaryKey($this->tableName);
        $this->columns = $this->database->getColumns($this->tableName);
        $indexes = $this->database->getIndexes($this->tableName);

        $this->indexedColumns = [];
        foreach ($indexes as $info) {
            foreach ($info['COLUMNS_LIST'] as $indexedColumn) {
                if ($indexedColumn != $this->primaryKey) {
                    $this->indexedColumns[] = $indexedColumn;
                }
            }
        }

        $this->classes = $this->moduleManager->generateClasses($this->moduleName, [
            'model' => 'Model\\' . $this->entityName,
            'resource' => 'Model\\ResourceModel\\' . $this->entityName,
            'collection' => 'Model\\ResourceModel\\' . $this->entityName . '\\Collection',
            'data_interface' => 'Api\\Data\\' . $this->entityName . 'Interface',
            'repository' => 'Model\\ResourceModel\\' . $this->entityName . 'Repository',
            'repository_interface' => 'Api\\' . $this->entityName . 'RepositoryInterface',
            'search_results_interface' => 'Api\\Data\\' . $this->entityName . 'SearchResultsInterface',
        ]);
    }

    /**
     * Generate resource
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function generateResource()
    {
        $this->outFiles['resource'] = [
            'file' => $this->classes['resource']['file'],
            'code' => $this->template->getCodeFromTemplate('ddd-cqrs/Model/ResourceModel/Resource', [
                'namespace' => $this->classes['resource']['info']['namespace'],
                'class' => $this->classes['resource']['info']['class_name'],
                'table' => $this->tableName,
                'primary_key' => $this->primaryKey,
            ]),
        ];
    }

    /**
     * Generate collection
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function generateCollection()
    {
        $this->outFiles['collection'] = [
            'file' => $this->classes['collection']['file'],
            'code' => $this->template->getCodeFromTemplate('ddd-cqrs/Model/ResourceModel/Model/Collection', [
                'namespace' => $this->classes['collection']['info']['namespace'],
                'class' => $this->classes['collection']['info']['class_name'],
                'model' => $this->classes['model']['class'],
                'resource' => $this->classes['resource']['class'],
                'primary_key' => $this->primaryKey,
            ]),
        ];
    }

    /**
     * Generate model
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function generateModel()
    {
        $modelMethodsList = [];
        $interfaceMethodsList = [];
        $constList = [];

        foreach ($this->columns as $columnName => $columnInfo) {
            $constList[] = $this->template->getCodeFromTemplate('ddd-cqrs/Api/Data/Interface.constant', [
                'field_const' => $columnName == $this->primaryKey ? 'ID' : strtoupper($columnName),
                'field_name' => $columnName,
            ]);

            $interfaceMethodsList[] = $this->template->getCodeFromTemplate('ddd-cqrs/Api/Data/Interface.methods', [
                'data_type' => $this->database->getTypeByColumnType($columnInfo['DATA_TYPE']),
                'data_interface' => $this->classes['data_interface']['class'],
                'field_name' => $columnName,
                'fn_name' => $columnName == $this->primaryKey ?
                    'Id' :
                    ucfirst($this->phpCode->toCamelCase($columnName)),
            ]);

            $modelMethodsList[] = $this->template->getCodeFromTemplate('ddd-cqrs/Model/Model.methods', [
                'data_type' => $this->database->getTypeByColumnType($columnInfo['DATA_TYPE']),
                'data_interface' => $this->classes['data_interface']['class'],
                'field_name' => $columnName,
                'field_const' => $columnName == $this->primaryKey ? 'ID' : strtoupper($columnName),
                'fn_name' => $columnName == $this->primaryKey ?
                    'Id' :
                    ucfirst($this->phpCode->toCamelCase($columnName)),
            ]);
        }

        $extensionInterface = preg_replace(
            '/Interface$/',
            'ExtensionInterface',
            $this->classes['data_interface']['class']
        );

        $this->outFiles['data_interface'] = [
            'file' => $this->classes['data_interface']['file'],
            'code' => $this->template->getCodeFromTemplate('ddd-cqrs/Api/Data/Interface', [
                'namespace' => $this->classes['data_interface']['info']['namespace'],
                'class' => $this->classes['data_interface']['info']['class_name'],
                'data_interface' => $this->classes['data_interface']['class'],
                'extension_interface' => $extensionInterface,
                'const_list' => implode("\n", $constList),
                'methods_list' => implode("\n\n", $interfaceMethodsList),
            ]),
        ];

        $this->outFiles['data_model'] = [
            'file' => $this->classes['data_model']['file'],
            'code' => $this->template->getCodeFromTemplate('ddd-cqrs/Model/Data/Model', [
                'namespace' => $this->classes['data_model']['info']['namespace'],
                'class' => $this->classes['data_model']['info']['class_name'],
                'data_interface' => $this->classes['data_interface']['class'],
                'extension_interface' => $extensionInterface,
                'methods_list' => implode("\n\n", $modelMethodsList),
            ]),
        ];
    }

    /**
     * Create DDD-CQRS model and return a list of modified files
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute()
    {
        $this->prepare();

        $this->generateModel();
        $this->generateResource();
        $this->generateCollection();
        $this->generateSearchResultInterface();
        $this->generateRepository();

        $outFilesNames = [];
        foreach ($this->outFiles as $outFile) {
            $outFilesNames[] = $outFile['file'];
        }

        if (!$this->overwrite) {
            $this->filesystem->assertNotExisting($outFilesNames);
        }

        foreach ($this->outFiles as $outFile) {
            $this->filesystem->writeFile($outFile['file'], $outFile['code']);
        }

        $this->injectDi();

        return $outFilesNames;
    }
}
