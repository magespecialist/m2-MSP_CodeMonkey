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

namespace MSP\CodeMonkey\Model;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Exception\LocalizedException;

class ModuleManager
{
    /**
     * @var ComponentRegistrarInterface
     */
    private $componentRegistrar;

    public function __construct(
        ComponentRegistrarInterface $componentRegistrar
    ) {
        $this->componentRegistrar = $componentRegistrar;
    }

    /**
     * Get full module path from filesystem
     * @param $moduleName
     * @return null|string
     * @throws LocalizedException
     */
    public function getModulePath($moduleName)
    {
        $res = $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, $moduleName);
        if (!$res) {
            throw new LocalizedException(__('Unknown module ' . $moduleName));
        }

        return $res;
    }

    /**
     * Return a correctly encoded class name
     * @param $className
     * @return string
     */
    public function getRelativeClassName($className)
    {
        $className = explode('\\', $className);

        $res = [];
        foreach ($className as $i) {
            $res[] = str_replace(' ', '', ucwords(preg_replace('/[^a-zA-Z0-9]+/', ' ', $i)));
        }

        return implode('\\', $res);
    }

    /**
     * Get module name
     * @param $moduleName
     * @return string
     */
    public function getModuleName($moduleName)
    {
        return str_replace('_', '\\', $moduleName);
    }

    /**
     * Return a full class path
     * @param $moduleName
     * @param $className
     * @return string
     */
    public function getClassName($moduleName, $className)
    {
        return '\\' . $this->getModuleName($moduleName) . '\\' . $this->getRelativeClassName($className);
    }

    /**
     * Return a full class file path
     * @param $moduleName
     * @param $className
     * @return string
     * @throws LocalizedException
     */
    public function getClassFile($moduleName, $className)
    {
        return $this->getModulePath($moduleName) . '/'
            . str_replace('\\', '/', $this->getRelativeClassName($className)) . '.php';
    }

    /**
     * Get class information
     * @param $fullClassName
     * @return array
     */
    public function getClassInfo($fullClassName)
    {
        if ($fullClassName[0] == '\\') {
            $fullClassName = substr($fullClassName, 1);
        }

        $parts = explode('\\', $fullClassName);
        $className = array_pop($parts);
        $namespace = implode('\\', $parts);

        return [
            'namespace' => $namespace,
            'class_name' => $className,
        ];
    }

    /**
     * Generate classes names and information
     * @param $moduleName
     * @param array $classes
     * @return array
     * @throws LocalizedException
     */
    public function generateClasses($moduleName, array $classes)
    {
        $res = [];

        foreach ($classes as $code => $class) {
            $className = $this->getClassName($moduleName, $class);
            $res[$code] = [
                'class' => $className,
                'file' => $this->getClassFile($moduleName, $class),
                'info' => $this->getClassInfo($className),
            ];
        }

        return $res;
    }
}
