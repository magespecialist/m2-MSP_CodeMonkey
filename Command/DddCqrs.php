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

namespace MSP\CodeMonkey\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DddCqrs extends Command
{
    /**
     * @var \MSP\CodeMonkey\Model\Monkey\DddCqrs
     */
    private $dddCqrsFactory;

    public function __construct(
        \MSP\CodeMonkey\Model\Monkey\DddCqrsFactory $dddCqrsFactory,
        $name = null
    ) {
        parent::__construct($name);
        $this->dddCqrsFactory = $dddCqrsFactory;
    }

    protected function configure()
    {
        $this->setName('msp:cm:ddd-cqrs');
        $this->setDescription('Create a full DDD-CQRS class set for a database entity (see MSI module)');

        $this->addOption('overwrite', null, InputOption::VALUE_NONE, 'Overwrite existing files');
        $this->addArgument('module', InputArgument::REQUIRED, 'Module name');
        $this->addArgument('module_api', InputArgument::REQUIRED, 'API Module name');
        $this->addArgument('entity_name', InputArgument::REQUIRED, 'Entity name');
        $this->addArgument('table', InputArgument::REQUIRED, 'Database table name');

        parent::configure();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @SuppressWarnings(PHPMD.UnusedFormalParameters)
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $moduleName = $input->getArgument('module');
        $apiModuleName = $input->getArgument('module_api');
        $entityName = $input->getArgument('entity_name');
        $tableName = $input->getArgument('table');

        $dddCqrs = $this->dddCqrsFactory->create([
            'moduleName' => $moduleName,
            'apiModuleName' => $apiModuleName,
            'entityName' => $entityName,
            'tableName' => $tableName,
            'overwrite' => (bool) $input->getOption('overwrite'),
        ]);

        $files = $dddCqrs->execute();

        $output->writeln('Modified files:');
        foreach ($files as $file) {
            $output->writeln("\t - " . $file);
        }
    }
}
