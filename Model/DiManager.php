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

class DiManager
{
    /**
     * @var ModuleManager
     */
    private $moduleManager;

    public function __construct(
        ModuleManager $moduleManager
    ) {
        $this->moduleManager = $moduleManager;
    }

    /**
     * Get di full path file
     * @param $moduleName
     * @return string
     */
    public function getDiFile($moduleName)
    {
        return $this->moduleManager->getModulePath($moduleName) . '/etc/di.xml';
    }

    /**
     * Create a preference
     * @param $moduleName
     * @param $source
     * @param $dest
     */
    public function createPreference($moduleName, $source, $dest)
    {
        $diFile = $this->getDiFile($moduleName);

        // @codingStandardsIgnoreStart
        $dom = new \DomDocument("1.0", "UTF-8");
        // @codingStandardsIgnoreEnd
        $dom->formatOutput = true;
        $dom->preserveWhiteSpace = false;

        try {
            $dom->load($diFile);
            $root = $dom->getElementsByTagName('config')->item(0);
        } catch (\Exception $e) {
            $root = $dom->createElement('config');
            $root->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
            $root->setAttribute('xsi:noNamespaceSchemaLocation', 'urn:magento:framework:ObjectManager/etc/config.xsd');
            $dom->appendChild($root);
        }

        // @codingStandardsIgnoreStart
        $xpath = new \DOMXpath($dom);
        // @codingStandardsIgnoreEnd

        $preferences = $xpath->query('//preference');
        for ($i = 0; $i < $preferences->length; $i++) {
            $nodeValue = $preferences->item($i)->attributes->getNamedItem('for')->nodeValue;
            if ($nodeValue == $source) {
                return;
            }
        }

        $preference = $dom->createElement('preference');
        $preference->setAttribute('for', $source);
        $preference->setAttribute('type', $dest);
        $root->appendChild($preference);
        $dom->save($diFile);
    }
}
