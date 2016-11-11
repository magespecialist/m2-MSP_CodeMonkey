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
use Magento\Framework\Filesystem\Io\File;

class Template
{
    protected $filesystem;
    protected $moduleManager;
    protected $file;

    public function __construct(
        Filesystem $filesystem,
        File $file,
        ModuleManager $moduleManager
    ) {
        $this->filesystem = $filesystem;
        $this->moduleManager = $moduleManager;
        $this->file = $file;
    }

    /**
     * Create file from template
     * @param $template
     * @param $dstFile
     * @param $params=[]
     * @throws LocalizedException
     */
    public function createFromTemplate($template, $dstFile, $params=[])
    {
        $this->filesystem->assertNotExisting($dstFile);

        $sourceFileContent = $this->getFromTemplate($template, $params);

        $dirName = $this->file->dirname($dstFile);
        if (!$this->file->fileExists($dirName)) {
            $this->file->mkdir($dirName, 0750, true);
        }

        $this->file->write($dstFile, $sourceFileContent);
    }

    /**
     * Create file from template
     * @param $template
     * @param $params=[]
     * @throws LocalizedException
     * @return string
     */
    public function getFromTemplate($template, $params=[])
    {
        if (!isset($params['header'])) {
            $params['header'] = $this->getFromTemplate('header', ['header' => '']);
        }

        $sourceFile = $this->moduleManager->getModulePath('MSP_CodeMonkey').'/templates/'.$template.'.template';
        $sourceFileContent = $this->file->read($sourceFile);

        foreach ($params as $k => $v) {
            $sourceFileContent = str_replace('%'.$k.'%', $v, $sourceFileContent);
        }

        return $sourceFileContent;
    }
}
