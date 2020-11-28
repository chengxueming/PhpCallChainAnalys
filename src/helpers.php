<?php
/**
 * @author cheng.xueming
 * @since  2020-11-22
 */

use PhpParser\Node\Stmt\{
    Class_,
    Interface_
};

use PhpParser\Node;

/**
 * 防止对没有实现魔术方法的类调用 __toString 导致fatal
 *
 * @param $name
 * @return string
 */
if (!function_exists('safeToString')) {
    function safeToString($name) {
        if(!is_string($name) || (is_object($name) && !method_exists($name, '__toString'))) {
            return '';
        }
        return (string)$name;
    }
}

/**
 * 防止对没有实现魔术方法的类调用 __toString 导致fatal
 *
 * @param $name
 * @return string
 */
if (!function_exists('classLike')) {
    /**
     * @param Node $node
     * @return bool
     */
    function classLike($node) {
        return $node instanceof Class_ || $node instanceof Interface_;
    }
}

if (!function_exists('stringAble')) {
    /**
     * @param $obj
     * @return bool
     */
    function stringAble($obj) {
        if(!is_array($obj) && !is_object($obj)) {
            return true;
        }
        return is_object($obj) && method_exists($obj, '__toString');
    }
}

