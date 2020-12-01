<?php
/**
 * @author cheng.xueming
 * @since  2020-12-01
 */
namespace CodeScanner\Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{
    InputInterface,
    InputArgument,
};
use Symfony\Component\Console\Output\OutputInterface;
use Alom\Graphviz\Digraph;
use CodeScanner\{
    CalledRelation
};

class DigraphCommand extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'app:create-user';
    use Common;
    protected function configure()
    {
        $this->addArgument('class', InputArgument::REQUIRED, '需要分析的类名')
             ->addArgument('method', InputArgument::REQUIRED, '需要分析的类的成员方法')
             ->addOption('config', 'c', InputArgument::OPTIONAL, '配置文件', 'scan.yaml')
            // the short description shown while running "php bin/console list"
            ->setDescription('指定类和方法绘制调用链路')

            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('指定类和方法绘制调用链路')
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
        $this->drawCallChain($class, $method, $this->config['dot'] . $class . ':' . $method . '.dot');
        $output->writeln('SUCCESS!');
        return 0;
    }

    public function drawCallChain($className, $method, $path) {
        $graph = new Digraph('G');
        $drawedEdges = [];
        $this->_drawCallChain($graph, $className, $method, $drawedEdges);
        file_put_contents($path, $graph->render());
    }

    /**
     * @param Digraph $graph
     * @param string $className
     * @param string $method
     */
    private function _drawCallChain($graph, $className, $method, &$drawedEdges) {
        $deps = [];
        $caller = sprintf('%s:%s', $className, $method);
        $callRelation = CalledRelation::getClass($className);
        foreach ($callRelation->listCalledInfo($method) as list($calledClass, $methods)) {
            $methods = array_unique($methods);
            foreach ($methods as $method) {
                $callee = sprintf('%s:%s', $calledClass, $method);
                if(!empty($drawedEdges[$caller][$callee])) {
                    continue;
                }
                $graph->edge([$caller, $callee]);
                $drawedEdges[$caller][$callee] = 1;
                $deps[$calledClass][$method] = 1;
            }
        }
        foreach($deps as $depClass => $depInfo) {
            IF(in_array($depClass, $this->config['no-recursive']['class'] ?? [])) {
                continue;
            }
            foreach($depInfo as $depMethod => $value) {
                $this->_drawCallChain($graph, $depClass, $depMethod, $drawedEdges);
            }
        }
    }
}
