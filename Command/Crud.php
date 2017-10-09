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

namespace MSP\CodeMonkey\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Crud extends Command
{
    /**
     * @var \MSP\CodeMonkey\Model\Monkey\CrudFactory
     */
    private $crudFactory;

    public function __construct(
        \MSP\CodeMonkey\Model\Monkey\CrudFactory $crudFactory,
        $name = null
    ) {
        parent::__construct($name);
        $this->crudFactory = $crudFactory;
    }

    protected function configure()
    {
        $this->setName('msp:cm:crud');
        $this->setDescription('Create a full model/data-model class set for a database entity');

        $this->addOption('overwrite', null, InputOption::VALUE_NONE, 'Overwrite existing files');
        $this->addArgument('module', InputArgument::REQUIRED, 'Module name');
        $this->addArgument('entity_name', InputArgument::REQUIRED, 'Entity name');
        $this->addArgument('table', InputArgument::REQUIRED, 'Database table name');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $moduleName = $input->getArgument('module');
        $entityName = $input->getArgument('entity_name');
        $tableName = $input->getArgument('table');

        $crud = $this->crudFactory->create([
            'moduleName' => $moduleName,
            'entityName' => $entityName,
            'tableName' => $tableName,
            'overwrite' => !! $input->getOption('overwrite'),
        ]);

        $files = $crud->generateCode();

        $output->writeln('Modified files:');
        foreach ($files as $file) {
            $output->writeln("\t - " . $file);
        }
    }
}
