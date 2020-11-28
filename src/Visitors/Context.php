<?php
/**
 * @author cheng.xueming
 * @since  2020-11-20
 */
namespace CodeScanner\Visitors;

use CodeScanner\{
    SymbolTable,
    CalledRelation
};

use PhpParser\Node\Stmt;

class Context {
    /**
     * @var Stmt\Class_
     */
    public $class = null;

    /**
     * @var Stmt\ClassMethod
     */
    public $method = null;

    /**
     * @var CalledRelation
     */
    public $called_relation = null;

    /**
     * @var SymbolTable
     */
    public $symbol_table = null;

    /**
     * @var string[]
     */
    public $variables = [];
}