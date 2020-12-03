<?php
/**
 * @author cheng.xueming
 * @since  2020-12-01
 */
// src/Command/CreateUserCommand.php
namespace CodeScanner\Command;

use CodeScanner\SymbolTable;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{
    InputInterface,
    InputArgument,
};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use CodeScanner\{
    CalledRelation
};
class ReBuildCommand extends Command
{
    use Common;
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'app:create-user';

    protected function configure()
    {
        $this->addOption('config', 'c', InputArgument::OPTIONAL, '配置文件', 'scan.yaml')
            // the short description shown while running "php bin/console list"
             ->setDescription('重新生成缓存文件')
            // the full command description shown when running the command with
            // the "--help" option
             ->setHelp('重新生成缓存文件')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initConfig($input->getOption('config'));
        $this->buildSymbolTable(false);
        $this->buildCalledRelation(false);
        $output->writeln('SUCCESS!');
        return 0;
    }
}