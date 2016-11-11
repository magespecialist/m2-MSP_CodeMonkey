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

namespace MSP\CodeMonkey\Command;

use MSP\CodeMonkey\Model\Monkey\Crud as CrudModel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Crud extends Command
{
    protected $crud;

    public function __construct(
        CrudModel $crud,
        $name = null
    ) {
        $this->crud = $crud;

        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('codemonkey:crud');
        $this->setDescription('Create model, resource, collection, repo, interface and api for a database entity');

        $this->addArgument('module', InputArgument::REQUIRED, __('Module name'));
        $this->addArgument('entity_name', InputArgument::REQUIRED, __('Entity name'));
        $this->addArgument('table', InputArgument::REQUIRED, __('Database table name'));

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $moduleName = $input->getArgument('module');
        $entityName = $input->getArgument('entity_name');
        $table = $input->getArgument('table');

        $files = $this->crud->create($moduleName, $entityName, $table);

        $output->writeln('Modified files:');
        foreach ($files as $file) {
            $output->writeln("\t - " . $file);
        }
    }
}
