<?php

namespace CodeScanner\Visitors;

use CodeScanner\SymbolTable;
/**
 * @author cheng.xueming
 * @since  2020-11-15
 */

use PhpParser\{
    Node, NodeFinder, NodeVisitorAbstract
};
use PhpParser\Node\Stmt;
class SymbolResolver extends NodeVisitorAbstract {
    /**
     * @var NodeFinder
     */
    private $nodeFinder;
    private $filePath = '';
    private $traverseClasses = [];
    public function beforeTraverse(array $nodes) {
        parent::beforeTraverse($nodes);
    }

    /**
     * SymbolResolver constructor.
     *
     * @param string $filePath
     */
    public function __construct($filePath) {
        $this->nodeFinder = new NodeFinder();
        $this->filePath = $filePath;
    }

    /**
     * @return string
     */
    public function getFilePath() {
        return $this->filePath;
    }

    public function enterNode(Node $node) {
        // 只需要遍历class的子节点
        //if (!$node instanceof Class_) {
        //    return NodeTraverser::DONT_TRAVERSE_CHILDREN;
        //}
    }
    public function leaveNode(Node $node) {
    }
    public function afterTraverse(array $nodes) {
        parent::afterTraverse($nodes);
        // 将类标记为solved
        $classes = $this->nodeFinder->find($nodes, function(Node $node) {
            return classLike($node);
        });
        foreach ($classes as $class){
            $this->traverseClasses []= (string)$class->name;
            $symbolTable = SymbolTable::getClass((string)$class->name);
            $doc = $class->getDocComment();
            // 读取类的注释
            $properties = $this->readClassComment($doc);
            foreach ($class->stmts as $stmt) {
                // 读取属性的注释
                if($stmt instanceof Stmt\Property) {
                    foreach ($stmt->props as $prop) {
                        $propClassName = $this->readPropComment($stmt->getDocComment());
                        if($propClassName) {
                            $properties[(string)$prop->name] = $propClassName;
                        }
                    }
                }
                // 读取类方法的注释
                if($stmt instanceof Stmt\ClassMethod) {
                    $methodInfo = ['is_public' => $stmt->isPublic()];
                    $methodReturn = $this->readMethodComment($stmt->getDocComment());
                    if($methodReturn) {
                        $methodInfo = array_merge($methodInfo, $methodReturn);
                    }
                    $symbolTable->methodsInfo[(string)$stmt->name] = $methodInfo;
                }
            }
            if($class->extends) {
                $extends = $class->extends;
                if(is_string($extends)) {
                    $extends = [$extends];
                }
                foreach ($extends as $extendClassName) {
                    // 扫描父类的属性表
                    if(is_array($extendClassName) && stringAble($extendClassName[0])) {
                        $extendClassName = (string)$extendClassName[0];
                    } else if(stringAble($extendClassName)) {
                        $extendClassName = (string)($extendClassName);
                    }
                    if($extendClassName) {
                        $symbolTable->addExtent(SymbolTable::getClass($extendClassName));
                    } else {
                        echo 'find unknown extend class in ' . $this->filePath . PHP_EOL;
                    }
                }
            }
            $symbolTable->setFilePath($this->filePath);
            $symbolTable->objectProperties = $properties;
            $symbolTable->setSolved(true);
        }
    }

    /**
     * 获取遍历过的类
     *
     * @return array
     */
    public function getTraverseClasses() {
        return $this->traverseClasses;
    }

    private function readClassComment($doc) {
        $doc_arr = explode("\n", $doc);
        $action_arr = [];
        foreach($doc_arr as $line){
            if(strpos($line, "@property") !== false){
                preg_match('/.*@property\s*(\S*)\s*[\$]?(\S*)/', trim($line), $m);
                if(isset($m[2])) {
                    $action_arr[$m[2]] = $m[1];
                } else {
                    print_r($m);
                    echo $line . PHP_EOL;
                }
            }
        }
        return $action_arr;
    }

    private function readPropComment($doc) {
        $doc_arr = explode("\n", $doc);
        foreach($doc_arr as $line){
            if(strpos($line, "@var") !== false){
                preg_match('/.*@var\s*(\S*)\s*/', trim($line), $m);
                return $m[1] ?? '';
            }
        }
        return '';
    }

    /**
     * 提取类方法的返回值（可能存在a|b，a[]的情况)
     *
     * @param $doc
     * @return array
     */
    private function readMethodComment($doc) {
        $doc_arr = explode("\n", $doc);
        $ret = [];
        $params = [];
        foreach($doc_arr as $line){
            // 函数返回值类型
            if(strpos($line, "@return") !== false){
                preg_match('/.*@return\s*(\S*)\s*/', trim($line), $m);
                $ret['return'] = $m[1] ?? '';
            }
            // 函数参数类型
            if(strpos($line, "@param") !== false){
                preg_match('/.*@param\s*(\S*)\s*[\$]?(\S*)/', trim($line), $m);
                if(isset($m[2])) {
                    $params[$m[2]] = $m[1];
                } else {
                    print_r($m);
                    echo $line . PHP_EOL;
                }
            }
        }
        if($params) {
            $ret['params'] = $params;
        }
        return $ret;
    }
}