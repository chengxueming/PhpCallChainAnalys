<?php
/**
 * @author cheng.xueming
 * @since  2020-11-25
 */
namespace CodeScanner;

use Symfony\Component\Finder\Finder;
use PhpParser\{
    ParserFactory
};
use Symfony\Component\Yaml\Yaml;
use Alom\Graphviz\Digraph;

class Application1 {
    /**
     * @var \PhpParser\Parser
     */
    private $parser;
    /**
     * @var array
     */
    private $config;

    private $enterClasses = [];

    public function __construct($configPath) {
        $this->parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
        $this->config = Yaml::parse($configPath);
    }

    public function run() {
        $this->buildSymbolTable();
        $this->buildCalledRelation();
        $this->drawCallChain('Base_Audit_Manager_Deal', 'execute', '/Users/momo/codescanner/.cache/dot/Base_Audit_Manager_Deal::execute.dot');
        //$this->drawCallChain('SymbolResolver', 'afterTraverse', '/Users/momo/Desktop/workspace/php-web-v5/code-test/codescanner/.cache/dot/SymbolResolver::afterTraverse.dot');
    }


    public function buildSymbolTable() {
        $cacheFile = $this->config['cache']['symbol-table'] ?? '';
        if(file_exists($cacheFile)) {
            SymbolTable::load($cacheFile);
        } else {
            $finder = new Finder;
            $includes = $this->config['project-dir']['includes'] ?? [];
            $excludes = $this->config['project-dir']['excludes'] ?? [];
            if(empty($includes)) {
                throw new \InvalidArgumentException('includes dir is empty');
            }
            $files = $finder
                ->in($includes)
                ->exclude($excludes)
                ->name('*.php');
            $start = microtime(true);
            foreach ($files as $file) {
                echo 'parser '. $file . PHP_EOL;
                SymbolTable::parser($this->parser, $file->getRealPath());
            }
            SymbolTable::dump($cacheFile);
        }
        foreach (SymbolTable::$resolveMap as $className => $symbolTable) {
            if($this->isEnterFile($symbolTable->getFilePath())) {
                $this->enterClasses []= $className;
            }
        }

        echo "parse symbol table time cost " . (microtime(true) - $start) . PHP_EOL;
    }

    /**
     * 判断一个文件是否包含在入口目录里
     *
     * @param string $filePath
     */
    private function isEnterFile($filePath) {
        $entersInclude =  $this->config['enters']['includes'] ?? [];
        $entersExclude =  $this->config['enters']['excludes'] ?? [];
        $find = false;
        foreach ($entersInclude as $enterDir) {
            if(empty($enterDir)) {
                continue;
            }
            if(strstr($filePath, $enterDir)) {
                $find = true;
                break;
            }
        }
        if($find) {
            foreach ($entersExclude as $enterDir) {
                if(empty($enterDir)) {
                    continue;
                }
                if(strstr($filePath, $enterDir)) {
                    $find = false;
                    break;
                }
            }
        }
        return $find;
    }

    public function buildCalledRelation() {
        CalledRelation::setNoTraversClass($this->config['no-recursive']['class'] ?? []);
        CalledRelation::setNoTraversDirs($this->config['no-recursive']['dirs'] ?? []);
        foreach ($this->enterClasses as $enterClass) {
            $filePath = SymbolTable::getClass($enterClass)->getFilePath();
            CalledRelation::parser($this->parser, $filePath);
        }
        //$calledEnters = [];
        //$this->getCalledEnters('Base_Helper_Goback', 'wwwRetry', $calledEnters);
        //print_r($calledEnters);
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
            foreach($depInfo as $depMethod => $value) {
                $this->_drawCallChain($graph, $depClass, $depMethod, $drawedEdges);
            }
        }
    }
}