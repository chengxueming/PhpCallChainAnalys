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
use Symfony\Component\Console\Helper\TableSeparator;

class CallerCommand extends Command
{
    use Common;
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'app:create-user';

    protected function configure()
    {
        $this->addArgument('class', InputArgument::REQUIRED, '需要分析的类名')
            ->addArgument('method', InputArgument::REQUIRED, '需要分析的类的成员方法')
            ->addOption('config', 'c', InputArgument::OPTIONAL, '配置文件', 'scan.yaml')
            // the short description shown while running "php bin/console list"
            ->setDescription('计算有哪些入口在调用这个方法')
            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('计算有哪些入口在调用这个方法')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initConfig($input->getOption('config'));
        $this->buildSymbolTable();
        $this->buildCalledRelation();
        $class = $input->getArgument('class');
        $method = $input->getArgument('method');
        $calledEnters = [];
        $this->getCalledEnters($class, $method, $calledEnters);
        // print_r($calledEnters);
        $tableCells = [];
        foreach ($calledEnters as $calledClass => $calledMethods) {
            $tableCells[] = [
                SymbolTable::getClass($calledClass)->getFilePath(),
                $calledClass,
                join(PHP_EOL, array_keys($calledMethods))
            ];
        }
        $io = new SymfonyStyle($input, $output);
        $io->table(
            ['路径', '类名', '方法名'],
            $tableCells
        );
        //$io->definitionList(
        //    'This is a title',
        //    ['foo1' => 'bar1'],
        //    ['foo2' => 'bar2'],
        //    ['foo3' => 'bar3'],
        //    new TableSeparator(),
        //    'This is another title',
        //    ['foo4' => 'bar4']
        //);
        // ... put here the code to run in your command

        // this method must return an integer number with the "exit status code"
        // of the command. You can also use these constants to make code more readable

        // return this if there was no problem running the command
        // (it's equivalent to returning int(0))
        return 0;

        // or return this if some error happened during the execution
        // (it's equivalent to returning int(1))
        // return Command::FAILURE;
    }
}