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

namespace MSP\CodeMonkey\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Database extends AbstractDb
{
    protected function _construct()
    {
    }

    /**
     * Get real table name
     * @param $tableName
     * @return string
     */
    public function getTableName($tableName)
    {
        return $this->getConnection()->getTableName($tableName);
    }

    /**
     * Get primary key field name
     * @param $tableName
     * @return string
     * @throws LocalizedException
     */
    public function getPrimaryKey($tableName)
    {
        $tableName = $this->getTableName($tableName);
        return $this->getConnection()->getAutoIncrementField($tableName);
    }

    /**
     * Get a list of columns information
     * @param $tableName
     * @return array
     */
    public function getColumns($tableName)
    {
        $tableName = $this->getTableName($tableName);
        return $this->getConnection()->describeTable($tableName);
    }

    /**
     * get PHP type by SQL data type
     * @param $type
     * @return string
     */
    public function getTypeByColumnType($type)
    {
        switch (strtolower($type))
        {
            case 'int':
                return 'int';

            case 'decimal':
            case 'float':
                return 'float';
        }

        return 'string';
    }

    /**
     * Return true if column is a primary key
     * @param $tableName
     * @param $columnName
     * @return bool
     */
    public function isPrimaryKey($tableName, $columnName)
    {
        return $this->getPrimaryKey($tableName) == $columnName;
    }
}