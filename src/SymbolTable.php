<?php
/**
 * @author cheng.xueming
 * @since  2020-11-15
 */
namespace CodeScanner;

use PhpParser\{
    NodeTraverser,
    NodeVisitor,
    Parser
};

class SymbolTable {
    private $className = '';

    private $filePath = '';

    /**
     * @var bool
     */
    private $solved = false;

    /**
     * @var SymbolTable[]
     */
    public static $resolveMap = [];

    /**
     * @var string[]
     */
    public $objectProperties;

    /**
     * @var array [type => '', params => '', return => '']
     */
    public $methodsInfo;

    /**
     * @var SymbolTable[]
     */
    private $extents = [];

    private function __construct($className) {
        $this->className = $className;
    }

    /**
     * @param            $className
     * @return SymbolTable
     */
    public static function getClass($className) {
        if(self::$resolveMap[$className] ?? false) {
            return self::$resolveMap[$className];
        }
        $symtab                       = new SymbolTable($className);
        $symtab->solved               = false;
        self::$resolveMap[$className] = $symtab;
        return $symtab;
    }

    public function addExtent(SymbolTable $symbolTable) {
        $this->extents []= $symbolTable;
    }

    /**
     * @param Parser $parser
     * @param $filePath
     * @return array
     */
    public static function parser($parser, $filePath) {
        $code = file_get_contents($filePath);
        $traverser = new NodeTraverser;
        $traverser->addVisitor(new NodeVisitor\ParentConnectingVisitor());
        $nameResolver = new Visitors\SymbolResolver($filePath);
        $traverser->addVisitor($nameResolver);
        $ast    = $parser->parse($code);
        $traverser->traverse($ast);
        return $nameResolver->getTraverseClasses();
    }

    public function setSolved($solved) {
        $this->solved = $solved;
    }

    /**
     * @return bool
     */
    public function isSolved() {
        return $this->solved;
    }

    /**
     * @param string $name
     * @return string | null
     */
    public function getPropertySolvedType($name) {
        if($this->objectProperties[$name] ?? false) {
            return $this->objectProperties[$name];
        }
        foreach ($this->extents as $extent) {
            if($extent->isSolved()) {
                $type = $extent->getPropertySolvedType($name);
                if(!is_null($type)) {
                    return $type;
                }
            }
        }
        return $this->getMethodSolvedType('__get');
    }

    /**
     * @param string $name
     * @return string | null
     */
    public function getMethodSolvedType($name) {
        if($this->methodsInfo[$name]['return'] ?? false) {
            return $this->methodsInfo[$name]['return'];
        }
        foreach ($this->extents as $extent) {
            if($extent->isSolved()) {
                $type = $extent->getMethodSolvedType($name);
                if(!is_null($type)) {
                    return $type;
                }
            }
        }
        return null;
    }

    /**
     * @param string $name
     * @return array
     */
    public function getMethodSolvedParams($name) {
        if($this->methodsInfo[$name]['params'] ?? false) {
            return $this->methodsInfo[$name]['params'] ?? [];
        }
        return [];
    }

    /**
     * @return string
     */
    public function getFilePath() {
        return $this->filePath;
    }

    /**
     * @param string
     */
    public function setFilePath($filePath) {
        $this->filePath = $filePath;
    }

    /**
     * 从文件中还原符号表
     *
     * @param $path
     */
    public static function load($path) {
        $contents = file_get_contents($path);
        $st = json_decode($contents, true);
        foreach ($st as $className => $symbolInfo) {
            $obj = self::getClass($className);
            if($obj->solved) {
                continue;
            }
            $obj->filePath = $symbolInfo['filePath'];
            $obj->solved = $symbolInfo['solved'];
            $obj->objectProperties = $symbolInfo['objectProperties'];
            $obj->methodsInfo = $symbolInfo['methodsInfo'];
            foreach ($symbolInfo['extents'] as $extent) {
                $obj->addExtent(self::getClass($extent));
            }
        }
    }

    /**
     * 将符号表导入到一个文件中
     *
     * @param $path
     */
    public static function dump($path) {
        $arr = [];
        foreach (self::$resolveMap as $className => $symboTable) {
            $arr[$className] = $symboTable->toArray();
        }
        file_put_contents($path, json_encode($arr));
    }

    private function toArray() {
        $extents = [];
        foreach ($this->extents as $extent) {
            $extents []= $extent->className;
        }
        return [
            'className' => $this->className,
            'filePath' => $this->filePath,
            'solved' => $this->solved,
            'objectProperties' => $this->objectProperties,
            'methodsInfo' => $this->methodsInfo,
            'extents' => $extents
        ];
    }
}