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
 * @copyright  Copyright (c) 2017 Skeeller srl (http://www.magespecialist.it)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace MSP\CodeMonkey\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Io\File;

class Template
{
    /**
     * @var File
     */
    private $file;

    /**
     * @var ModuleManager
     */
    private $moduleManager;

    public function __construct(
        File $file,
        ModuleManager $moduleManager
    ) {
        $this->file = $file;
        $this->moduleManager = $moduleManager;
    }

    /**
     * Create file from template
     * @param $template
     * @param $params =[]
     * @throws LocalizedException
     * @return string
     */
    public function getCodeFromTemplate($template, $params = [])
    {
        if (!isset($params['header'])) {
            $params['header'] = $this->getCodeFromTemplate('header', ['header' => '']);
        }

        $sourceFile = $this->moduleManager->getModulePath('MSP_CodeMonkey') . '/templates/' . $template . '.template';
        $sourceFileContent = $this->file->read($sourceFile);

        foreach ($params as $k => $v) {
            $sourceFileContent = str_replace('%' . $k . '%', $v, $sourceFileContent);
        }

        return $sourceFileContent;
    }
}
