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

use MEQP2\Tests\NamingConventions\true\mixed;
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
    private $columns = [];
    private $indexedColumns = [];
    private $classes = [];
    private $outFiles = [];

    /**
     * @var string
     */
    private $primaryKey;

    /**
     * @var string
     */
    private $entityVar;

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

    /**
     * @var string
     */
    private $moduleName;

    /**
     * @var string
     */
    private $apiModuleName;

    /**
     * @var string
     */
    private $entityName;

    /**
     * @var string
     */
    private $tableName;

    /**
     * @var bool
     */
    private $overwrite;

    /**
     * @var bool
     */
    private $tests;

    public function __construct(
        Template $template,
        PhpCode $phpCode,
        ModuleManager $moduleManager,
        DiManager $diManager,
        Database $database,
        Filesystem $filesystem,
        string $moduleName,
        string $apiModuleName,
        string $entityName,
        string $tableName,
        bool $overwrite,
        bool $tests
    ) {
        $this->phpCode = $phpCode;
        $this->template = $template;
        $this->moduleManager = $moduleManager;
        $this->database = $database;
        $this->filesystem = $filesystem;
        $this->diManager = $diManager;
        $this->moduleName = $moduleName;
        $this->apiModuleName = $apiModuleName;
        $this->entityName = $entityName;
        $this->tableName = $tableName;
        $this->overwrite = $overwrite;
        $this->tests = $tests;
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

        $moduleClasses = $this->moduleManager->generateClasses($this->moduleName, [
            'model' => 'Model\\' . $this->entityName,
            'resource' => 'Model\\ResourceModel\\' . $this->entityName,
            'collection' => 'Model\\ResourceModel\\' . $this->entityName . '\\Collection',
            'repository' => 'Model\\' . $this->entityName . 'Repository',
            'search_results' => 'Model\\' . $this->entityName . 'SearchResults',

            'command_get_interface' => 'Model\\' . $this->entityName . '\\Command\\GetInterface',
            'command_save_interface' => 'Model\\' . $this->entityName . '\\Command\\SaveInterface',
            'command_delete_interface' => 'Model\\' . $this->entityName . '\\Command\\DeleteInterface',
            'command_list_interface' => 'Model\\' . $this->entityName . '\\Command\\GetListInterface',

            'command_get' => 'Model\\' . $this->entityName . '\\Command\\Get',
            'command_save' => 'Model\\' . $this->entityName . '\\Command\\Save',
            'command_delete' => 'Model\\' . $this->entityName . '\\Command\\Delete',
            'command_list' => 'Model\\' . $this->entityName . '\\Command\\GetList',

            'test_wrapper' => 'Test\\Integration\\' . $this->entityName . '\\TestCaseWrapper',
            'test_repository' => 'Test\\Integration\\' . $this->entityName . '\\RepositoryTest',
        ]);

        $apiClasses = $this->moduleManager->generateClasses($this->apiModuleName, [
            'data_interface' => 'Api\\Data\\' . $this->entityName . 'Interface',
            'repository_interface' => 'Api\\' . $this->entityName . 'RepositoryInterface',
            'search_results_interface' => 'Api\\' . $this->entityName . 'SearchResultsInterface',
        ]);

        $this->classes = array_merge($moduleClasses, $apiClasses);

        // Indexes getters
        foreach ($this->indexedColumns as $indexedColumn) {
            $className = 'GetBy' . ucfirst($this->phpCode->toCamelCase($indexedColumn));

            $this->classes = array_merge($this->classes, $this->moduleManager->generateClasses($this->moduleName, [
                'command_getby_' . $indexedColumn . '_interface' =>
                    'Model\\' . $this->entityName . '\\Command\\' . $className . 'Interface',
                'command_getby_' . $indexedColumn =>
                    'Model\\' . $this->entityName . '\\Command\\' . $className
            ]));
        }
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
                'data_interface' => $this->classes['data_interface']['class'],
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
                'data_interface' => $this->classes['data_interface']['class'],
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

            if ($columnName !== $this->primaryKey) {
                $interfaceMethodsList[] = $this->template->getCodeFromTemplate('ddd-cqrs/Api/Data/Interface.methods', [
                    'data_type' => $this->database->getTypeByColumnType($columnInfo['DATA_TYPE']),
                    'data_interface' => $this->classes['data_interface']['class'],
                    'field_name' => $columnName,
                    'fn_name' => ucfirst($this->phpCode->toCamelCase($columnName)),
                ]);

                $modelMethodsList[] = $this->template->getCodeFromTemplate('ddd-cqrs/Model/Model.methods', [
                    'data_type' => $this->database->getTypeByColumnType($columnInfo['DATA_TYPE']),
                    'data_interface' => $this->classes['data_interface']['class'],
                    'field_name' => $columnName,
                    'field_const' => $columnName == $this->primaryKey ? 'ID' : strtoupper($columnName),
                    'fn_name' => ucfirst($this->phpCode->toCamelCase($columnName)),
                ]);
            }
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

        $this->outFiles['model'] = [
            'file' => $this->classes['model']['file'],
            'code' => $this->template->getCodeFromTemplate('ddd-cqrs/Model/Model', [
                'namespace' => $this->classes['model']['info']['namespace'],
                'class' => $this->classes['model']['info']['class_name'],
                'data_interface' => $this->classes['data_interface']['class'],
                'resource' => $this->classes['resource']['class'],
                'extension_interface' => $extensionInterface,
                'methods_list' => implode("\n\n", $modelMethodsList),
            ]),
        ];
    }

    /**
     * Generate search result interface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function generateSearchResult()
    {
        $this->outFiles['search_results_interface'] = [
            'file' => $this->classes['search_results_interface']['file'],
            'code' => $this->template->getCodeFromTemplate('ddd-cqrs/Api/SearchResultInterface', [
                'namespace' => $this->classes['search_results_interface']['info']['namespace'],
                'class' => $this->classes['search_results_interface']['info']['class_name'],
                'data_interface' => $this->classes['data_interface']['class'],
            ]),
        ];

        $this->outFiles['search_results'] = [
            'file' => $this->classes['search_results']['file'],
            'code' => $this->template->getCodeFromTemplate('ddd-cqrs/Model/SearchResult', [
                'namespace' => $this->classes['search_results']['info']['namespace'],
                'class' => $this->classes['search_results']['info']['class_name'],
                'interface' => $this->classes['search_results_interface']['class'],
            ]),
        ];
    }

    /**
     * Generate repository class
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function generateRepository()
    {
        $idxMthRepoList = [];
        $idxMthIfaceList = [];
        $diPropsDeclarations = [];
        $diDocs = [];
        $diParams = [];
        $diAssigns = [];

        foreach ($this->indexedColumns as $columnName) {
            $columnInfo = $this->columns[$columnName];

            $idxMthRepoList[] = $this->template->getCodeFromTemplate('ddd-cqrs/Model/Repository.index', [
                'var_name' => lcfirst($this->phpCode->toCamelCase($columnName)),
                'data_type' => $this->database->getTypeByColumnType($columnInfo['DATA_TYPE']),
                'data_interface' => $this->classes['data_interface']['class'],
                'column_name' => ucfirst($this->phpCode->toCamelCase($columnName)),
                'command' => 'commandGetBy' . $this->phpCode->toCamelCase($columnName),
            ]);

            $idxMthIfaceList[] = $this->template->getCodeFromTemplate('ddd-cqrs/Api/RepositoryInterface.index', [
                'data_type' => $this->database->getTypeByColumnType($columnInfo['DATA_TYPE']),
                'data_interface' => $this->classes['data_interface']['class'],
                'entity_var' => $this->entityVar,
                'entity_name' => $this->entityName,
                'column_name' => ucfirst($this->phpCode->toCamelCase($columnName)),
                'var_name' => lcfirst($this->phpCode->toCamelCase($columnName)),
            ]);

            $diPropsDeclarations[] =
                $this->template->getCodeFromTemplate('ddd-cqrs/Model/Repository.index.decl', [
                    'type' => $this->classes['command_getby_' . $columnName . '_interface']['class'],
                    'var' => 'commandGetBy' . $this->phpCode->toCamelCase($columnName),
                ]);

            $diDocs[] =
                $this->template->getCodeFromTemplate('ddd-cqrs/Model/Repository.index.doc', [
                    'type' => $this->classes['command_getby_' . $columnName . '_interface']['class'],
                    'var' => 'commandGetBy' . $this->phpCode->toCamelCase($columnName),
                ]);

            $diParams[] =
                $this->template->getCodeFromTemplate('ddd-cqrs/Model/Repository.index.di', [
                    'type' => $this->classes['command_getby_' . $columnName . '_interface']['class'],
                    'var' => 'commandGetBy' . $this->phpCode->toCamelCase($columnName),
                ]);

            $diAssigns[] =
                $this->template->getCodeFromTemplate('ddd-cqrs/Model/Repository.index.assign', [
                    'var' => 'commandGetBy' . $this->phpCode->toCamelCase($columnName),
                ]);
        }

        $this->outFiles['repository_interface'] = [
            'file' => $this->classes['repository_interface']['file'],
            'code' => $this->template->getCodeFromTemplate('ddd-cqrs/Api/RepositoryInterface', [
                'namespace' => $this->classes['repository_interface']['info']['namespace'],
                'class' => $this->classes['repository_interface']['info']['class_name'],
                'entity_var' => $this->entityVar,
                'data_interface' => $this->classes['data_interface']['class'],
                'search_results_interface' => $this->classes['search_results_interface']['class'],
                'index_methods' => !empty($idxMthRepoList) ? "\n" . implode("\n\n", $idxMthIfaceList) . "\n" : '',
            ]),
        ];

        $this->outFiles['repository'] = [
            'file' => $this->classes['repository']['file'],
            'code' => $this->template->getCodeFromTemplate('ddd-cqrs/Model/Repository', [
                'namespace' => $this->classes['repository']['info']['namespace'],
                'interface' => $this->classes['repository_interface']['class'],
                'model' => $this->classes['model']['class'],
                'class' => $this->classes['repository']['info']['class_name'],
                'data_interface' => $this->classes['data_interface']['class'],
                'collection' => $this->classes['collection']['class'],
                'search_results_interface' => $this->classes['search_results_interface']['class'],
                'entity_var' => $this->entityVar,
                'entity_name' => $this->entityName,
                'resource' => $this->classes['resource']['class'],
                'index_methods' => !empty($idxMthRepoList) ? "\n" . implode("\n\n", $idxMthRepoList) . "\n" : '',

                'command_get_interface' => $this->classes['command_get_interface']['class'],
                'command_delete_interface' => $this->classes['command_delete_interface']['class'],
                'command_save_interface' => $this->classes['command_save_interface']['class'],
                'command_list_interface' => $this->classes['command_list_interface']['class'],

                'index_di_decl' => implode("", $diPropsDeclarations),
                'index_di_doc' => implode("", $diDocs),
                'index_di' => implode("", $diParams),
                'index_di_assign' => implode("", $diAssigns),
            ]),
        ];
    }

    /**
     * Generate repository class
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function generateCommandGet()
    {
        $this->outFiles['command_get_interface'] = [
            'file' => $this->classes['command_get_interface']['file'],
            'code' => $this->template->getCodeFromTemplate('ddd-cqrs/Model/Model/Command/GetInterface', [
                'namespace' => $this->classes['command_get_interface']['info']['namespace'],
                'class' => $this->classes['command_get_interface']['info']['class_name'],
                'entity_var' => $this->entityVar,
                'entity_name' => $this->entityName,
                'data_interface' => $this->classes['data_interface']['class'],
                'repository_interface' => $this->classes['repository_interface']['class'],
            ]),
        ];

        $this->outFiles['command_get'] = [
            'file' => $this->classes['command_get']['file'],
            'code' => $this->template->getCodeFromTemplate('ddd-cqrs/Model/Model/Command/Get', [
                'namespace' => $this->classes['command_get']['info']['namespace'],
                'class' => $this->classes['command_get']['info']['class_name'],
                'interface' => $this->classes['command_get_interface']['info']['class_name'],
                'entity_var' => $this->entityVar,
                'entity_name' => $this->entityName,
                'data_interface' => $this->classes['data_interface']['class'],
                'repository_interface' => $this->classes['repository_interface']['class'],
                'resource_model' => $this->classes['resource']['class'],
            ]),
        ];

        foreach ($this->indexedColumns as $indexedColumn) {
            $columnInfo = $this->columns[$indexedColumn];

            $classKey = 'command_getby_' . $indexedColumn . '_interface';
            $this->outFiles[$classKey] = [
                'file' => $this->classes[$classKey]['file'],
                'code' => $this->template->getCodeFromTemplate('ddd-cqrs/Model/Model/Command/GetByInterface', [
                    'namespace' => $this->classes[$classKey]['info']['namespace'],
                    'class' => $this->classes[$classKey]['info']['class_name'],
                    'data_type' => $this->database->getTypeByColumnType($columnInfo['DATA_TYPE']),
                    'entity_var' => $this->entityVar,
                    'entity_name' => $this->entityName,
                    'data_interface' => $this->classes['data_interface']['class'],
                    'repository_interface' => $this->classes['repository_interface']['class'],
                    'column_name' => ucfirst($this->phpCode->toCamelCase($indexedColumn)),
                    'column_var' => lcfirst($this->phpCode->toCamelCase($indexedColumn)),
                ]),
            ];

            $classKey = 'command_getby_' . $indexedColumn;
            $this->outFiles[$classKey] = [
                'file' => $this->classes[$classKey]['file'],
                'code' => $this->template->getCodeFromTemplate('ddd-cqrs/Model/Model/Command/GetBy', [
                    'namespace' => $this->classes[$classKey]['info']['namespace'],
                    'class' => $this->classes[$classKey]['info']['class_name'],
                    'interface' => $this->classes[$classKey . '_interface']['info']['class_name'],
                    'data_type' => $this->database->getTypeByColumnType($columnInfo['DATA_TYPE']),
                    'entity_var' => $this->entityVar,
                    'entity_name' => $this->entityName,
                    'data_interface' => $this->classes['data_interface']['class'],
                    'repository_interface' => $this->classes['repository_interface']['class'],
                    'column_name' => ucfirst($this->phpCode->toCamelCase($indexedColumn)),
                    'column_var' => lcfirst($this->phpCode->toCamelCase($indexedColumn)),
                    'column_const' => strtoupper($indexedColumn),
                    'resource_model' => $this->classes['resource']['class'],
                ]),
            ];
        }
    }

    /**
     * Generate repository class
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function generateCommandSave()
    {
        $this->outFiles['command_save_interface'] = [
            'file' => $this->classes['command_save_interface']['file'],
            'code' => $this->template->getCodeFromTemplate('ddd-cqrs/Model/Model/Command/SaveInterface', [
                'namespace' => $this->classes['command_save_interface']['info']['namespace'],
                'class' => $this->classes['command_save_interface']['info']['class_name'],
                'entity_var' => $this->entityVar,
                'entity_name' => $this->entityName,
                'data_interface' => $this->classes['data_interface']['class'],
                'repository_interface' => $this->classes['repository_interface']['class'],
            ]),
        ];

        $this->outFiles['command_save'] = [
            'file' => $this->classes['command_save']['file'],
            'code' => $this->template->getCodeFromTemplate('ddd-cqrs/Model/Model/Command/Save', [
                'namespace' => $this->classes['command_save']['info']['namespace'],
                'class' => $this->classes['command_save']['info']['class_name'],
                'interface' => $this->classes['command_save_interface']['info']['class_name'],
                'entity_var' => $this->entityVar,
                'entity_name' => $this->entityName,
                'data_interface' => $this->classes['data_interface']['class'],
                'repository_interface' => $this->classes['repository_interface']['class'],
                'resource_model' => $this->classes['resource']['class'],
            ]),
        ];
    }

    /**
     * Generate repository class
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function generateCommandDelete()
    {
        $this->outFiles['command_delete_interface'] = [
            'file' => $this->classes['command_delete_interface']['file'],
            'code' => $this->template->getCodeFromTemplate('ddd-cqrs/Model/Model/Command/DeleteInterface', [
                'namespace' => $this->classes['command_delete_interface']['info']['namespace'],
                'class' => $this->classes['command_delete_interface']['info']['class_name'],
                'entity_var' => $this->entityVar,
                'entity_name' => $this->entityName,
                'data_interface' => $this->classes['data_interface']['class'],
                'repository_interface' => $this->classes['repository_interface']['class'],
            ]),
        ];

        $this->outFiles['command_delete'] = [
            'file' => $this->classes['command_delete']['file'],
            'code' => $this->template->getCodeFromTemplate('ddd-cqrs/Model/Model/Command/Delete', [
                'namespace' => $this->classes['command_delete']['info']['namespace'],
                'class' => $this->classes['command_delete']['info']['class_name'],
                'interface' => $this->classes['command_delete_interface']['info']['class_name'],
                'entity_var' => $this->entityVar,
                'entity_name' => $this->entityName,
                'data_interface' => $this->classes['data_interface']['class'],
                'repository_interface' => $this->classes['repository_interface']['class'],
                'resource_model' => $this->classes['resource']['class'],
                'get_interface' => $this->classes['command_get_interface']['info']['class_name'],
            ]),
        ];
    }

    /**
     * Generate repository class
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function generateCommandList()
    {
        $this->outFiles['command_list_interface'] = [
            'file' => $this->classes['command_list_interface']['file'],
            'code' => $this->template->getCodeFromTemplate('ddd-cqrs/Model/Model/Command/GetListInterface', [
                'namespace' => $this->classes['command_list_interface']['info']['namespace'],
                'class' => $this->classes['command_list_interface']['info']['class_name'],
                'entity_var' => $this->entityVar,
                'entity_name' => $this->entityName,
                'repository_interface' => $this->classes['repository_interface']['class'],
                'search_results_interface' => $this->classes['search_results_interface']['class'],
            ]),
        ];

        $this->outFiles['command_list'] = [
            'file' => $this->classes['command_list']['file'],
            'code' => $this->template->getCodeFromTemplate('ddd-cqrs/Model/Model/Command/GetList', [
                'namespace' => $this->classes['command_list']['info']['namespace'],
                'class' => $this->classes['command_list']['info']['class_name'],
                'interface' => $this->classes['command_list_interface']['info']['class_name'],
                'entity_var' => $this->entityVar,
                'entity_name' => $this->entityName,
                'repository_interface' => $this->classes['repository_interface']['class'],
                'resource_model' => $this->classes['resource']['class'],
                'search_results_interface' => $this->classes['search_results_interface']['class'],
                'collection' => $this->classes['collection']['class'],
            ]),
        ];
    }

    /**
     * Inject DI preferences
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function injectDi()
    {
        $this->diManager->createPreference(
            $this->moduleName,
            $this->classes['data_interface']['class'],
            $this->classes['model']['class']
        );
        $this->diManager->createPreference(
            $this->moduleName,
            $this->classes['repository_interface']['class'],
            $this->classes['repository']['class']
        );
        $this->diManager->createPreference(
            $this->moduleName,
            $this->classes['search_results_interface']['class'],
            $this->classes['search_results']['class']
        );

        $this->diManager->createPreference(
            $this->moduleName,
            $this->classes['command_get_interface']['class'],
            $this->classes['command_get']['class']
        );
        $this->diManager->createPreference(
            $this->moduleName,
            $this->classes['command_save_interface']['class'],
            $this->classes['command_save']['class']
        );
        $this->diManager->createPreference(
            $this->moduleName,
            $this->classes['command_delete_interface']['class'],
            $this->classes['command_delete']['class']
        );
        $this->diManager->createPreference(
            $this->moduleName,
            $this->classes['command_list_interface']['class'],
            $this->classes['command_list']['class']
        );

        foreach ($this->indexedColumns as $indexedColumn) {
            $this->diManager->createPreference(
                $this->moduleName,
                $this->classes['command_getby_' . $indexedColumn . '_interface']['class'],
                $this->classes['command_getby_' . $indexedColumn]['class']
            );
        }
    }

    /**
     * Get random value by type
     * @param $fieldType
     * @return mixed
     */
    private function getRandomDataGeneratorByType($fieldType)
    {
        switch ($fieldType) {
            case 'int':
                return "(int) rand(0, 100)";

            case 'tinyint':
            case 'boolean':
                return "(bool) rand(0, 1)";

            case 'decimal':
            case 'float':
                return "(float) rand(0, 100) / 10";

            case 'date':
                return "date('Y-m-d', rand(0, time()))";

            case 'time':
                return "date('H:i:s', rand(0, time()))";

            case 'datetime':
                return "date('Y-m-d H:i:s', rand(0, time()))";

            default:
                return "'A random string ' . uniqid()";
        }
    }

    /**
     * Create test wrapper
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function generateTestCaseWrapper()
    {
        $this->outFiles['test_wrapper'] = [
            'file' => $this->classes['test_wrapper']['file'],
            'code' => $this->template->getCodeFromTemplate('ddd-cqrs/Test/Integration/TestCaseWrapper', [
                'namespace' => $this->classes['test_wrapper']['info']['namespace'],
            ]),
        ];
    }

    /**
     * Build repository tests
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function generateRepositoryTest()
    {
        $valuesGenerator = [];

        foreach ($this->columns as $columnName => $columnInfo) {
            if ($columnName === $this->primaryKey) {
                continue;
            }

            $fieldConst = strtoupper($columnName);
            $valuesGenerator[] = $this->template->getCodeFromTemplate(
                'ddd-cqrs/Test/Integration/Model/RepositoryTest.create.values',
                [
                    'data_interface' => $this->classes['data_interface']['class'],
                    'field_const' => $fieldConst,
                    'value' => $this->getRandomDataGeneratorByType($columnInfo['DATA_TYPE']),
                ]
            );
        }

        $this->outFiles['test_repository'] = [
            'file' => $this->classes['test_repository']['file'],
            'code' => $this->template->getCodeFromTemplate('ddd-cqrs/Test/Integration/Model/RepositoryTest', [
                'namespace' => $this->classes['test_repository']['info']['namespace'],
                'class' => $this->classes['test_repository']['info']['class_name'],
                'test_wrapper' => $this->classes['test_wrapper']['class'],
                'test_wrapper_class' => $this->classes['test_wrapper']['info']['class_name'],
                'repository_interface' => $this->classes['repository_interface']['class'],
                'data_interface' => $this->classes['data_interface']['class'],
                'entity_var' => $this->entityVar,
                'values_generator' => implode("\n", $valuesGenerator),
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
        $this->generateSearchResult();
        $this->generateCommandGet();
        $this->generateCommandSave();
        $this->generateCommandDelete();
        $this->generateCommandList();
        $this->generateRepository();

        if ($this->tests) {
            $this->generateTestCaseWrapper();
            $this->generateRepositoryTest();
        }

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
