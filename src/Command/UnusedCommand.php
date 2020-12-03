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
class UnusedCommand extends Command
{
    use Common;
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'app:create-user';

    protected function configure()
    {
        $this->addOption('config', 'c', InputArgument::OPTIONAL, '配置文件', 'scan.yaml')
            // the short description shown while running "php bin/console list"
             ->setDescription('指定入口，获取当前项目中，有哪些类，无法通过入口扫描调用链覆盖到')
            // the full command description shown when running the command with
            // the "--help" option
             ->setHelp('指定入口，获取当前项目中，有哪些类，无法通过入口扫描调用链覆盖到')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initConfig($input->getOption('config'));
        $this->buildSymbolTable();
        $this->buildCalledRelation();
        $knownClasses = array_keys(SymbolTable::$resolveMap);
        $calledClasses = array_keys(CalledRelation::$resolveMap);
        $knownUnCalledClasses = array_diff($knownClasses, $calledClasses);
        $knownUnCalledClasses = array_diff($knownUnCalledClasses, $this->enterClasses);
        $io = new SymfonyStyle($input, $output);
        $knownUnCalledClasses = array_map(function ($class) {
            return [$class];
        }, $knownUnCalledClasses);
        $io->table(
            ['类名'],
            $knownUnCalledClasses
        );
        return 0;
    }
}