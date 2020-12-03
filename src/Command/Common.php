<?php
/**
 * @author cheng.xueming
 * @since  2020-12-01
 */
namespace CodeScanner\Command;

use Symfony\Component\Finder\Finder;
use PhpParser\{
    ParserFactory
};
use Symfony\Component\Yaml\Yaml;
use CodeScanner\{
    SymbolTable,
    CalledRelation
};

trait Common {
    /**
     * @var \PhpParser\Parser
     */
    private $parser;
    /**
     * @var array
     */
    private $config;

    private $useCache = false;
    private $symbolCost = 0;
    private $calledCost = 0;

    private $enterClasses = [];

    public function initConfig($configPath) {
        if(!file_exists($configPath)) {
            throw new \InvalidArgumentException('config file ' . $configPath . ' not exists!');
        }
        $this->parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
        $this->config = Yaml::parse($configPath);
    }

    public function buildSymbolTable($useCache = true) {
        $cacheFile = $this->config['cache']['symbol-table'] ?? '';
        if(file_exists($cacheFile) && $useCache) {
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
            $this->symbolCost = microtime(true) - $start;
            SymbolTable::dump($cacheFile);
        }
        foreach (SymbolTable::$resolveMap as $className => $symbolTable) {
            if($this->isEnterFile($symbolTable->getFilePath())) {
                $this->enterClasses []= $className;
            }
        }
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

    public function getConfig() {
        return $this->config;
    }

    public function buildCalledRelation($useCache = true) {
        $cacheFile = $this->config['cache']['called-relation'] ?? '';
        if(file_exists($cacheFile) && $useCache) {
            CalledRelation::load($cacheFile);
        } else {
            $start = microtime(true);
            CalledRelation::setNoTraversClass($this->config['no-recursive']['class'] ?? []);
            CalledRelation::setNoTraversDirs($this->config['no-recursive']['dirs'] ?? []);
            foreach ($this->enterClasses as $enterClass) {
                $filePath = SymbolTable::getClass($enterClass)->getFilePath();
                CalledRelation::parser($this->parser, $filePath);
            }
            $this->calledCost = microtime(true) - $start;
            CalledRelation::dump($cacheFile);
        }
    }
}