# region

[![Latest Stable Version](https://poser.pugx.org/ionepub/region/v/stable)](https://packagist.org/packages/ionepub/region)

[![Total Downloads](https://poser.pugx.org/ionepub/region/downloads)](https://packagist.org/packages/ionepub/region)

[![Latest Unstable Version](https://poser.pugx.org/ionepub/region/v/unstable)](https://packagist.org/packages/ionepub/region)

[![License](https://poser.pugx.org/ionepub/region/license)](https://packagist.org/packages/ionepub/region)

> A Chinese region search helper.
>
> 中国地区信息查询辅助类。根据国家统计局统计用区划代码查询地区信息，目前数据最小单位为街道、乡镇，支持编号查询/所在省市区查询/下级查询/中文搜索。

## Requirement 依赖

- catfan/medoo 1.5+
- MySQL

## 安装

```
# 稳定版本
composer require ionepub/region
composer require --prefer-dist ionepub/region

# 开发版本
composer require ionepub/region:dev-master
```

> tip

如果 `composer require ionepub/region` 时报错：

```
[InvalidArgumentException]                                                   
  Could not find package ionepub/region at any version for your minimum-stabi  
  lity (stable). Check the package spelling or your minimum-stability
```

需要先执行一次 `composer update nothing`，再执行require命令就可以了

由于region包依赖于[Medoo](https://medoo.in/)，安装同时也会安装[Medoo](https://medoo.in/)。

## 使用

### 引用

```php
require 'vendor/autoload.php';
```

### 示例

```php
use Ionepub\Region;
try {
	// get a medoo instance
	$db = new medoo([
	    'database_type' => 'mysql',
	    'database_name' => 'test',
	    'server' => 'localhost',
	    'username' => 'root',
	    'password' => 'root',
	    'charset' => 'utf8'
	]);
	$table_name = 'region';
	// get a region instance
	// 第三个参数为true，表示首次安装，将在数据库中创建名为 $table_name 的表，并初始化地区数据
	$region = Region::init($db, $table_name, true);
	
	// 获取所有省份
	$province = $region->get();
    
	// 获取北京市信息
	$city_of_beijing = $region->get('110000000000');
    
	// 获取北京市下属区域信息
	$child_of_beijing = $region->child('110000000000');
    
	// 获取东华门街道办事处所在位置：北京市/市辖区/东城区/东华门街道办事处
	$position_of_region = $region->position('110101001000');
    
	// 查询名称中包含北京的地区信息
	$search_of_region = $region->search('北京');
    
	// 在省份中查询名称包含北京的地区信息
	$search_of_province = $region->search('北京', Region::LEVEL_PROVINCE);
    
	// 在所有地区中查询名称为“北京市”的地区信息（非模糊查询）
	$search3 = $region->search('北京市', Region::LEVEL_DEFAULT, Region::STRICT);
    
} catch (\Exception $e) {
	echo 'Error catched: ' . $e->getMessage();
}
```

返回值示例：

```
Array
(
    [regionId] => 355
    [regionCode] => 120000000000
    [regionName] => 天津市
    [regionLevel] => 2
)
```

### 首次初始化

```php
$region = Region::init($db, $region_name, true);
```

首次初始化时，需向init静态方法传递第三个参数为`true`，此时将使用内置的sql文件向MySQL数据库中创建名为 `$table_name` 的表，并导入所有地区数据。首次初始化时，可能耗时较长。

>  当数据库中已有此表时，即使第三个参数为true，也不会再次初始化。

表结构如下：

```mysql
CREATE TABLE `region` (
`regionId` int(11) NOT NULL AUTO_INCREMENT,
`regionCode` char(12) DEFAULT '' COMMENT '12位地区编码',
`regionName` varchar(120) DEFAULT '' COMMENT '地区名',
`regionLevel` tinyint(1) DEFAULT '0' COMMENT '地区层级，1国家2省3市4县5乡镇6村委会',
PRIMARY KEY (`regionId`),
UNIQUE KEY `code` (`regionCode`),
KEY `regionName` (`regionName`),
KEY `regionLevel` (`regionLevel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='地区信息表';
```

### 获取实例

```
$region = Region::init($db, $region_name);
$region = Region::init($db, $region_name, true);
```

无论第三个参数是否为true，init()都将返回一个Region实例。

### 公共常量

- Region::LEVEL_DEFAULT 地区层级-默认层级
- Region::LEVEL_PROVINCE 地区层级-省级
- Region::LEVEL_CITY 地区层级-市级
- Region::LEVEL_DISTRICT 地区层级-县、区级
- Region::LEVEL_TOWN 地区层级-街道、乡镇级
- Region::STRICT 严格搜索模式
- Region::NOT_STRICT 非严格搜索模式

### 获取省份列表

```php
$region->get();
```

### 获取单个地区信息

```php
$region->get(1);
$region->get('110000000000');
$region->get('110000000000', Region::LEVEL_PROVINCE); // 明确获取编码为110000000000的省份地区信息
```

### 获取多个地区信息

```php
$region->get(['110000000000', 355]); // 同时获取两个地区的信息
$region->get([], Region::LEVEL_PROVINCE); // 获取所有省份信息
```

### 获取子级地区信息

```php
$region->child(3);
$region->child('110000000000');
```

child方法第二个参数接收地区层级参数，表示想要获取的子地区层级，默认为第一个参数所在层级的下一级，一般不使用

```php 
$region->child(3, Region::LEVEL_DEFAULT);
```

### 获取地区所在位置

```php
$region->position('110101001000');
```

position方法同样支持地区ID和地区编码传入，如市则返回省市，区则返回省市区。返回的数据按层级增序排序，包含参数所在地区。

### 按地区名称搜索

```php
$region->search('北京');
```

search方法接收3个参数：

- 地区名
- 地区层级
- 搜索模式，默认 Region::NOT_STRICT 非严格搜索模式，即模糊搜索

```php
// 查询名称中包含北京的地区信息
$region->search('北京');
// 在省份中查询名称包含北京的地区信息
$region->search('北京', Region::LEVEL_PROVINCE);
// 在所有地区中查询名称为“北京市”的地区信息（非模糊查询）
$region->search('北京市', Region::LEVEL_DEFAULT, Region::STRICT);
```

### License

MIT license.