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
use CodeScanner\{
    CalledRelation
};
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
        return 0;
    }

    public function getCalledEnters($className, $methodName, &$calledEnters) {
        static $record = [];
        if(isset($record[$className][$methodName])) {
            return;
        }
        $record[$className][$methodName] = 1;
        $calledClasses = CalledRelation::getCalledPosition($className, $methodName, false);
        foreach ($calledClasses as list($calledClass, $calledMethod)) {
            if(in_array($calledClass, $this->enterClasses)) {
                if(!isset($calledEnters[$calledClass][$calledMethod])) {
                    $calledEnters[$calledClass][$calledMethod] = 1;
                } else {
                    continue;
                }
            }
            $this->getCalledEnters($calledClass, $calledMethod, $calledEnters);
        }
    }
}