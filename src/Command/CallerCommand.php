<?php
/**
 * @author cheng.xueming
 * @since  2020-12-01
 */
// src/Command/CreateUserCommand.php
namespace CodeScanner\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{
    InputInterface,
    InputArgument,
};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

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
        if(empty($this->config['dot'])) {
            throw new \InvalidArgumentException('输出目录为空 请在配置文件中指定 `dot` 目录');
        }
        $this->buildSymbolTable();
        $this->buildCalledRelation();
        $class = $input->getArgument('class');
        $method = $input->getArgument('method');
        $this->drawCallChain($class, $method, $this->config['dot'] ?? __DIR__);
        $output->writeln([
            'User Creator',
            '============',
            $input->getArgument('class'),
            $input->getArgument('method'),
            $input->getOption('config'),
        ]);
        $io = new SymfonyStyle($input, $output);
        $io->text([
            'Lorem ipsum dolor sit amet',
            'Consectetur adipiscing elit',
            'Aenean sit amet arcu vitae sem faucibus porta',
        ]);
        $io->title('Lorem Ipsum Dolor Sit Amet');
        $io->listing([
            'Element #1 Lorem ipsum dolor sit amet',
            'Element #2 Lorem ipsum dolor sit amet',
            'Element #3 Lorem ipsum dolor sit amet',
        ]);
        $io->table(
            ['Header 1', 'Header 2'],
            [
                ['Cell 1-1', 'Cell 1-2'],
                ['Cell 2-1', 'Cell 2-2'],
                ['Cell 3-1', 'Cell 3-2'],
            ]
        );
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