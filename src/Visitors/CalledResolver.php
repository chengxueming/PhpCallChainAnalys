<?php
/**
 * @author cheng.xueming
 * @since  2020-11-19
 */
namespace CodeScanner\Visitors;

use PhpParser\{
    Node, NodeFinder, NodeTraverser, NodeVisitorAbstract, Parser
};

use PhpParser\Node\{
    Identifier
};

use PhpParser\Node\Expr\{
    PropertyFetch,
    MethodCall,
    StaticCall,
    Variable,
    Assign
};

use PhpParser\Node\Stmt;
use CodeScanner\{
    SymbolTable,
    CalledRelation
};
use CodeScanner\Error\Level;

class CalledResolver extends NodeVisitorAbstract {
    private $nodeFinder;
    /**
     * @var Parser
     */
    private $parser;
    /**
     * @var Context
     */
    private $context;

    public function beforeTraverse(array $nodes) {
        parent::beforeTraverse($nodes);
    }
    public function __construct($parser) {
        $this->nodeFinder = new NodeFinder();
        $this->context = new Context();
        $this->parser = $parser;
    }

    public function enterNode(Node $node) {
        // 记录正在遍历的类（上下文）
        if (classLike($node)) {
            $callRelation = CalledRelation::getClass((string)$node->name);
            if($callRelation->isSolved()) {
                // 已经解决了 不重复处理
                $this->log('traverse repeat class ' . (string)$node->name, Level::WARNING);
            }
            $this->context->called_relation = $callRelation;
            $this->context->class = $node;
            $this->context->symbol_table = SymbolTable::getClass(((string)$node->name));
        }
        // 记录正在遍历的方法（上下文）
        if ($node instanceof Stmt\ClassMethod) {
            $this->context->method = $node;
            $this->context->variables = $this->context->symbol_table->getMethodSolvedParams((string)$node->name);
        }
        return null;
	}
	
    public function leaveNode(Node $node) {
        if(!is_null($this->context->method)) {
            // 计算符号表
            $this->calcVarTable($node);
            // 计算方法调用
            $this->calcMethodCall($node);
        }
        if($node instanceof Stmt\ClassMethod) {
            // 清理上下文
            $this->context->method = null;
        }
        // 清理上下文
        if (classLike($node)) {
            if(is_null($this->context->called_relation)) {
                throw new \RuntimeException('wired called_relation emtpy ' . (string)$node->name);
            }
            $this->context->class = null;
            $this->context->called_relation->setSolved(true);
            // 解决每一个递归调用的类
            $this->context->called_relation->solveCallRelationDeeply($this->parser);
            $this->context->called_relation = null;
            $this->context->symbol_table = null;
        }
        return null;
    }

    /**
     * 计算符号表，回写到类的属性表或方法的局部变量表中，从而在发现方法调用时找到调用的类
     *
     * @param Node $node
     * @return null
     */
    private function calcVarTable($node) {
        if(!$node instanceof Assign) {
            return;
        }
        // TODO Assign 和 AssignOp有啥区别
        if($node->expr instanceof MethodCall) {
            $exprClass = $this->getPropertyType($node->expr->var, $node->expr->name->name, true);
            // $this->log(sprintf('method call %s, %s', $exprClass, (string)$node->expr->name->name), Level::WARNING);
        } else if($node->expr instanceof PropertyFetch) {
            $exprClass = $this->getPropertyType($node->expr->var, $node->expr->name, false);
            // $this->log(sprintf('prop fetch %s, %s', $exprClass, (string)$node->expr->name), Level::WARNING);
        } else {
            return;
        }
        if(empty($exprClass)) {
            $this->log('type of right value in assign expr not find', Level::WARNING);
            // TODO 需要考虑数组或多返回值的情况
            return;
        }
        if($node->var instanceof PropertyFetch) {
            if($node->var->var instanceof Variable && (string)$node->var->var->name == 'this') {
                // 直接加到类的符号表里
                $this->context->symbol_table->objectProperties[(string)$node->var->name] = $exprClass;
            } else {
                $this->log("fuck, find no this fetch property" . (string)$node->var->name, Level::WARNING);
            }
        }
        if($node->var instanceof Variable) {
            // 加到方法的符号表里
            $this->context->variables[(string)$node->var->name] = $exprClass;
        }
        return;
    }

    /**
     * 找到方法调用的类，并回写到这个类的调用关系中
     *
     * @param Node $node
     */
    private function calcMethodCall($node) {
        if($node instanceof MethodCall) {
            $type = $this->getCalledMethodClass($node);
        } elseif($node instanceof StaticCall) {
            if($node->class instanceof Variable) {
                // self::staticmethod();
                if((string)$node->class->name == 'self') {
                    return (string)$this->context->class->name;
                } else if(isset($this->context->variables[(string)$node->class->name])) { // $var::staticmethod
                    // 分析上下文变量
                    return $this->context->variables[(string)$node->class->name];
                }
            } else {
                $type = (string)$node->class;
            }
        } else {
            return;
        }
        if(empty($type)) {
            $this->log(sprintf('method %s called type not find', (string)$node->name->name), Level::WARNING);
            return;
        }
        // 方法 -> 另一个类或自己 -> 方法
        // TODO 如果调用的是父类的方法，考虑这里写父类
        $contextMethodName = (string)$this->context->method->name;
        $this->context->called_relation->methods[$contextMethodName][$type][] =  (string)($node->name->name);
    }

    /**
     * @param MethodCall $methodCall
     */
    private function getCalledMethodClass($methodCall) {
        if($methodCall->var instanceof PropertyFetch) {
            return $this->getPropertyType($methodCall->var->var, $methodCall->var->name, false);
        } else if($methodCall->var instanceof Variable) {
            if((string)$methodCall->var->name == 'this') {
                return (string)$this->context->class->name;
            } else if(isset($this->context->variables[(string)$methodCall->var->name])) {
                // 分析上下文变量
                return $this->context->variables[(string)$methodCall->var->name];
            }
        } else if($methodCall->var instanceof MethodCall) {
            return $this->getPropertyType($methodCall->var->var, $methodCall->var->name->name, true);
        }
        $this->log("called method class unknown " . (string)$methodCall->name->name, Level::WARNING);
        return '';
    }

    /**
     * @param Variable|PropertyFetch|MethodCall $var
     * @param $name
     * @param $isMethod
     * @return null|string
     */
    function getPropertyType($var, $name, $isMethod) {
        if($name instanceof Variable) {
            $name = (string)$name->name;
        }
        if(!is_string($name) && (is_object($name) && !method_exists($name, '__toString'))) {
            $this->log('property type unknown ' . get_class($name), Level::WARNING);
            return '';
        }
        $name = (string)$name;
        // $this or $a
        if($var instanceof Variable) {
            if((string)$var->name == 'this' && $this->context->class) {
                $type = (string)$this->context->class->name;
            }else if(isset($this->context->variables[(string)$var->name])) {
                // 分析上下文变量
                $type = $this->context->variables[(string)$var->name];
            } else {
                $this->log("find undefined variable " . (string)$var->name, Level::WARNING);
            }
        } else if($var instanceof PropertyFetch) {
            // $this->def
            $type = $this->getPropertyType($var->var, $var->name, false);
        } else if($var instanceof MethodCall) {
            // $this->def->getAbc()
            $type = $this->getPropertyType($var->var, $var->name->name, true);
        } else if($var instanceof StaticCall) {
            $type = (string)$var->class;
        }
        if(empty($type)) {
            $this->log("called chain break at " . safeToString($name), Level::WARNING);
            return '';
        }
        // $this->log("called chain at " . (string)$name . " type " . $type, Level::WARNING);
        if($isMethod) {
            $class = SymbolTable::getClass($type);
            $tmp = $class->getMethodSolvedType($name);
        } else {
            $class = SymbolTable::getClass($type);
            $tmp = $class->getPropertySolvedType($name);
            // echo 'hhhh ' . (string)$name . " ".$tmp . " " . $type. PHP_EOL;
        }
        return $tmp;
    }

    /**
     * 打印日志
     *
     * @param $msg
     * @param $level
     */
    private function log($msg, $level) {
        // return;
        $className = 'UnKnown';
        $methodName = 'UnKnown';
        if($this->context->class) {
            $className = (string)$this->context->class->name;
        }
        if($this->context->method) {
            $methodName = (string)$this->context->method->name;
        }
        echo sprintf("%s=>%s $level: $msg" . PHP_EOL, $className, $methodName);
    }
}