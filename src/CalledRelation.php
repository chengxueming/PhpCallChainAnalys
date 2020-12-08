<?php
/**
 * @author cheng.xueming
 * @since  2020-11-19
 */
namespace CodeScanner;

use PhpParser\{
    NodeTraverser,
    NodeVisitor,
    Parser
};

use CodeScanner\Visitors;
use CodeScanner\Error\Level;

class CalledRelation {
    private $className = '';

    /**
     * @var bool
     */
    private $solved = false;

    /**
     * @var CalledRelation[]
     */
    public static $resolveMap = [];

    /**
     * @var CalledRelation[]
     */
    private $extents = [];


    private static $NO_TRAVERS_CLASS = [];
    private static $NO_TRAVERS_DIRS = [];

    /**
     * 方法名 => 类名 => 方法名[]
     *
     * @var array
     */
    public $methods = [];

    private function __construct($className) {
        $this->className = $className;
    }

    /**
     * @param            $className
     * @return CalledRelation
     */
    public static function getClass($className) {
        if(self::$resolveMap[$className] ?? false) {
            return self::$resolveMap[$className];
        }
        $callRelation                       = new CalledRelation($className);
        $callRelation->solved               = false;
        self::$resolveMap[$className] = $callRelation;
        return $callRelation;
    }

    public function addExtent(CalledRelation $callRelation) {
        $this->extents []= $callRelation;
    }

    /**
     * @param Parser $parser
     * @param $filePath
     */
    public static function parser($parser, $filePath) {
        $code = file_get_contents($filePath);
        $traverser = new NodeTraverser;
        $traverser->addVisitor(new NodeVisitor\ParentConnectingVisitor());
        $nameResolver = new Visitors\CalledResolver($parser);
        $traverser->addVisitor($nameResolver);
        $ast    = $parser->parse($code);
        $traverser->traverse($ast);
    }

    /**
     * @return array
     */
    public static function dumpCallRelation() {
        $dumpResult = [];
        foreach (self::$resolveMap as $className => $calledRelation) {
            $dumpResult[$className] = $calledRelation->methods;
        }
        return $dumpResult;
    }

    public function setSolved($solved) {
        $this->solved = $solved;
    }

    /**
     * 获得一个类 或 方法在哪里调用过
     *
     * @param string $className
     * @param string $methodName
     * @param bool $yieldClass
     */
    public static function getCalledPosition($className, $methodName, $yieldClass) {
       foreach (self::$resolveMap as $resolvedClass => $callRelation) {
           foreach ($callRelation->methods as $resolveMethod => $calledInfo) {
               foreach ($calledInfo as $calledClass => $calledFunctions) {
                   if($className == $calledClass) {
                       $find = false;
                       if($methodName) {
                           foreach ($calledFunctions as $calledFunction) {
                               if($calledFunction == $methodName) {
                                   $find = true;
                                   if(!$yieldClass) {
                                       yield [$resolvedClass, $resolveMethod];
                                   }
                               }
                           }
                       } else {
                           $find = true;
                       }
                       if($yieldClass && $find) {
                           yield $resolvedClass;
                       }
                   }
               }
           }
       }
    }

    public function listCalledInfo($method) {
        foreach (($this->methods[$method] ?? []) as  $calledClass => $calledMethod) {
            yield [$calledClass, $calledMethod];
        }
    }

    public static function listCallerInfo($className, $methodName) {
        foreach (self::$resolveMap as $resolvedClass => $callRelation) {
            foreach ($callRelation->methods as $resolveMethod => $calledInfo) {
                foreach ($calledInfo as $calledClass => $calledFunctions) {
                    if($className == $calledClass) {
                        foreach ($calledFunctions as $calledFunction) {
                            if($calledFunction == $methodName) {
                                yield [$resolvedClass, $resolveMethod];
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * @return bool
     */
    public function isSolved() {
        return $this->solved;
    }

    /**
     * 递归解决每个调用的类
     *
     * @param Parser $parser
     */
    public function solveCallRelationDeeply($parser) {
        foreach ($this->methods as $method) {
            foreach ($method as $calledClassName => $calledMethods) {
                if(in_array($calledClassName, self::$NO_TRAVERS_CLASS)) {
                    continue;
                }
                $filePath = SymbolTable::getClass($calledClassName)->getFilePath();
                if(empty($filePath)) {
                    $this->log('class not find ' . $calledClassName, Level::WARNING);
                    continue;
                }
                $noTraverse = false;
                foreach (self::$NO_TRAVERS_DIRS as $noTraversDir) {
                    if(strstr($filePath, $noTraversDir)) {
                        // $this->log('jump no traverse file ' . $filePath, Level::INFO);
                        $noTraverse = true;
                        break;
                    }
                }
                if($noTraverse) {
                    continue;
                }
                $callRelation = self::getClass($calledClassName);
                if($callRelation->isSolved()) {
                    continue;
                }
                $this->log('start travser '. $calledClassName, Level::INFO);
                self::parser($parser, $filePath);
                $this->log('end travser '. $calledClassName, Level::INFO);
            }
        }
    }

    public static function setNoTraversClass($classes) {
        self::$NO_TRAVERS_CLASS = $classes;
    }

    public static function setNoTraversDirs($dirs) {
        self::$NO_TRAVERS_DIRS = $dirs;
    }

    /**
     * 打印日志
     *
     * @param $msg
     * @param $level
     */
    private function log($msg, $level) {
        echo sprintf("%s:%s $level $msg" . PHP_EOL, __CLASS__, $this->className);
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
            $obj->solved = $symbolInfo['solved'];
            $obj->methods = $symbolInfo['methods'];
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
        foreach (self::$resolveMap as $className => $callRelation) {
            $arr[$className] = $callRelation->toArray();
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
            'solved' => $this->solved,
            'methods' => $this->methods,
            'extents' => $extents
        ];
    }
}