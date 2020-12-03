# 计划
获取某个方法使用到的所有类：方法
- 1.goback的情况
- 2.链式调用
  - 找到链式调用走不通的节点
- 3.call_user_func_array
- 4.通过get方法调用

缓存：
根据目录和exclude缓存
符号表和callrelation 分开缓存

命令：
phpscan -c some.yaml -dep 

功能
- 1.扫描整个目录，找到一个类有被哪些类调用
- 2.从一个入口找到所有调用链
- 3.

遍历两次
- 生成属性表（符号表）
- 生成方法调用表

# 日程
## 2020-11-19
  > PropertyProperty 获取 name的方法是 (string)p->name
  > 注意类型写在类属性注释中的情况

## 2020-11-20
  - [ ] 分析上下文变量,推导变量类型

## 2020-11-21
  > x 1.获取函数变量的类型 
  > x 2.推导没有注释的成员变量类型
  > x 3.解析函数返回值类型
  函数注释，
  注意parent::method 和 静态调用

## 2020-11-22
   ```
   dot e.dot -Tpng > call_graph.png
   ```
## 2020-11-28
   问题：
   1.yaml里目录的指定应不应该用相对目录（会有歧义么）
   2.目前对于\Redis 和 Redis当做两个类，怎么解决
   3.php里的异常机制是怎样的
   进展：
   1.支持yaml解析
  
## 2020-12-03
   问题
    - 1.把文件输出出来什么形式比较好，目录为分析目录的一级目录 或 二级目录？
    - 2.数组类型的返回需要解析
    - 3.带命名空间的情况兼容不好
    
# TODO
## 主线 TODO
* [x] 扫描目录生成符号表
* [x] 根据符号表解析一个链式调用
* [x] 递归解析一个函数的所有调用
* [x] 将一个调用链绘制成dot文件

## 方法调用中的类型解析 TODO
* [x] 类型通过分析类注释解决
* [x] 类型通过分析属性注释解决
* [x] 解析局部变量类型
   - [x] 成员函数调用（this 或 局部变量)
     ```php
	 $a = $this->getMethod(); // 需要根据getMethod的注释 或 return语句来解析
	 ```
   - [ ] 静态方法调用
     ```
	 $a = Class::staticMethod(); // 需要根据staticMethod的注释 或 return语句来解析
	 ```
   - [ ] 三元表达式
     ```
	 $a = 1 + 1 > 2? $b : $c; // 要综合$b 或 $c的类型来解析
	 ```
   - [ ] new 运算符
     ```
	 $a = new Class();
	 ```
   - [ ] foreach的情况 根据数组的类型 推导变量的类型
     ```
	 $a = []; // SomeClass[]
	 foreach($a as $b) {
		 $b->method(); // $b 的类型为SomeClass
	 }
	 ```
   - [ ] 变量（参数或返回值）采用了php的强类型
     ```
	 function some(SomeClass $b) {
		 $b->method();
	 }
	 ```
* [x] 类型需要通过分析构造方法解决

# 参考
- [alom/graphviz](https://packagist.org/packages/alom/graphviz)
- []()
# 问题
- Identifier 和 Variable 有什么区别