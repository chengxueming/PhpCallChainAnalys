<?php
/**
 * @author cheng.xueming
 * @since  2020-12-01
 */
namespace CodeScanner\Command;
use parallel\Runtime\Error\IllegalParameter;
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
    const MODE_FULL = 'full';
    const MODE_TARGET = 'target';
    const MODE_CALLER = 'caller';
    protected function configure()
    {
        $this->addArgument('class', InputArgument::REQUIRED, '需要分析的类名')
             ->addArgument('method', InputArgument::REQUIRED, '需要分析的类的成员方法')
             ->addOption('target_class', 'tc', InputArgument::OPTIONAL, '单条调用链的目标类名')
             ->addOption('target_method', 'tm', InputArgument::OPTIONAL, '单条调用链的目标方法名')
             ->addOption('config', 'c', InputArgument::OPTIONAL, '配置文件', 'scan.yaml')
             ->addOption('depth', 'd', InputArgument::OPTIONAL, 'mode 为 caller时表示分析的深度', 1)
             ->addOption('mode', 'm', InputArgument::OPTIONAL, '模式 full表示一个函数的完整所有调用 target 表示一个方法到另一个方法的所有可能逻辑 需要配合 target_class 与 target_method，caller表示调用指定方法的所有方法 可以配合depth表示拓展的深度', self::MODE_FULL)
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
        $mode = $input->getOption('mode');
        switch ($mode) {
            case self::MODE_FULL:
                $this->drawFullyCallChain($class, $method, $this->config['dot'] . $class . ':' . $method . '.dot');
                break;
            case self::MODE_TARGET:
                $targetClass = $input->getOption('target_class');
                $targetMethod = $input->getOption('target_method');
                if(!empty($targetClass) && !empty($targetMethod)) {
                    throw new \InvalidArgumentException('target_class 或者 target_method 没有指定');
                }
                $this->drawSingleCallChain($class, $method, $targetClass, $targetMethod, sprintf('%s%s:%s=>%s:%s.dot', $this->config['dot'], $class, $method, $targetClass, $targetMethod));
                break;
            case self::MODE_CALLER:
                $maxDepth = $input->getOption('depth');
                $path = $this->config['dot'] . $class . ':' . $method .'_caller'. '.dot';
                $this->drawCallerCallChain($class, $method, $path, $maxDepth);
                break;
        }
        $output->writeln('SUCCESS!');
        return 0;
    }

    public function drawFullyCallChain($className, $method, $path) {
        $graph = new Digraph('G');
        $drawedEdges = [];
        $this->_drawFullyCallChain($graph, $className, $method, $drawedEdges);
        file_put_contents($path, $graph->render());
    }

    public function drawSingleCallChain($className, $method, $targetClass, $targetMethod, $path) {
        $graph = new Digraph('G');
        $drawedEdges = [];
        $this->_drawSingleCallChain($graph, $className, $method, $targetClass, $targetMethod, $drawedEdges);
        file_put_contents($path, $graph->render());
    }

    /**
     * @param Digraph $graph
     * @param string $className
     * @param string $method
     */
    private function _drawFullyCallChain($graph, $className, $method, &$drawedEdges) {
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
                $this->_drawFullyCallChain($graph, $depClass, $depMethod, $drawedEdges);
            }
        }
    }

    /**
     * @param Digraph $graph
     * @param string $className
     * @param string $method
     * @param string $targetClass
     * @param string $targetMethod
     * @param array $drawedEdges
     */
    private function _drawSingleCallChain($graph, $className, $method, $targetClass, $targetMethod, &$drawedEdges) {
        if($className == $targetClass && $method == $targetMethod) {
            return true;
        }
        $deps = [];
        $caller = sprintf('%s:%s', $className, $method);
        $callRelation = CalledRelation::getClass($className);
        foreach ($callRelation->listCalledInfo($method) as list($calledClass, $methods)) {
            $methods = array_unique($methods);
            foreach ($methods as $method) {
                $deps[$calledClass][$method] = 1;
            }
        }
        $called = false;
        foreach($deps as $depClass => $depInfo) {
            foreach($depInfo as $depMethod => $value) {
                $callee = sprintf('%s:%s', $depClass, $depMethod);
                if(!empty($drawedEdges[$caller][$callee])) {
                    continue;
                }
                $drawedEdges[$caller][$callee] = 1;
                $ret = $this->_drawSingleCallChain($graph, $depClass, $depMethod, $targetClass, $targetMethod, $drawedEdges);
                if($ret) {
                    $called = true;
                    $graph->edge([$caller, $callee]);
                }
            }
        }
        return $called;
    }

    public function drawCallerCallChain($className, $method, $path, $maxDepth) {
        $graph = new Digraph('G');
        $drawedEdges = [];
        $this->_drawCallerCallChain($graph, $className, $method, $drawedEdges, 0, $maxDepth);
        file_put_contents($path, $graph->render());
    }

    /**
     * @param Digraph $graph
     * @param string $className
     * @param string $method
     */
    private function _drawCallerCallChain($graph, $className, $method, &$drawedEdges, $depth, $maxDepth) {
        if($depth == $maxDepth) {
            return;
        }
        $callers      = [];
        $callee       = sprintf('%s:%s', $className, $method);
        foreach (CalledRelation::listCallerInfo($className, $method) as list($callerClass, $callerMethod)) {
            $caller = sprintf('%s:%s', $callerClass, $callerMethod);
            if(!empty($drawedEdges[$caller][$callee])) {
                continue;
            }
            $graph->edge([$caller, $callee]);
            $drawedEdges[$caller][$callee]        = 1;
            $callers[$callerClass][$callerMethod] = 1;
        }
        foreach($callers as $callerClass => $callerInfo) {
            foreach($callerInfo as $callerMethod => $value) {
                $this->_drawCallerCallChain($graph, $callerClass, $callerMethod, $drawedEdges, $depth + 1, $maxDepth);
            }
        }
    }
}
