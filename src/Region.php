<?php 
/**
 * Region.php
 * A Chinese region search helper.
 * 中国地区信息查询辅助类
 * 根据国家统计局统计用区划代码查询地区信息，目前数据最小单位为街道、乡镇，支持编号查询/所在省市区查询/下级查询/中文搜索。
 * @author ionepub
 * @version 1.0
 * GitHub repo: https://github.com/ionepub/region
 * @date 2018-09-05
 */
namespace Ionepub;
use Medoo\Medoo;

/**
 * class Region
 */
class Region
{
	/**
	 * 定义地区表中各字段名
	 * @access public
	 * @var string
	 */
	const REGION_ID = 'regionId'; // 地区id

	const REGION_CODE = 'regionCode'; // 地区编码

	const REGION_NAME = 'regionName'; // 地区名

	const REGION_LEVEL = 'regionLevel'; // 地区层级

	/**
	 * 定义各地区层级
	 * @access public
	 * @var int
	 */
	const LEVEL_DEFAULT = 0; // 默认层级

	const LEVEL_COUNTRY = 1; // 国家级

	const LEVEL_PROVINCE = 2; // 省级
	
	const LEVEL_CITY = 3; // 市级

	const LEVEL_DISTRICT = 4; // 县、区级

	const LEVEL_TOWN = 5; // 街道、乡镇级

	/**
	 * 定义是否严格模式
	 * @access public
	 * @var bool
	 */
	const STRICT = true;

	const NOT_STRICT = false;

	/**
	 * 单例实例
	 * @access private
	 * @var class object
	 */
	private static $_instance;

	/**
	 * Medoo数据库实例
	 * @access private
	 * @var Medoo object
	 */
	private static $_db;

	/**
	 * 数据库中地区表表名
	 * @access private
	 * @var string
	 */
	private static $_table;

	/**
	 * 地区层级可选项
	 * @access private
	 * @var array
	 */
	private static $regionLevelArr = [
		self::LEVEL_DEFAULT, 
		self::LEVEL_COUNTRY, 
		self::LEVEL_PROVINCE, 
		self::LEVEL_CITY, 
		self::LEVEL_DISTRICT, 
		self::LEVEL_TOWN, 
	];

	/**
	 * 特殊的地级市编码，这些地级市没有直接的下属县、区，只有直接的下属乡镇
	 * @access private
	 * @var array
	 */
	private static $specialRegionCode = ['441900000000', '442000000000', '460400000000'];

	/**
	 * 构造函数
	 * @access private
	 */
	private function __construct(){}

	/**
	 * 初始化函数，构造单例
	 * @access public
	 * @param Medoo $db Medoo数据库实例
	 * @param string $table 地区表表名
	 * @param bool $install 是否初始化地区表和数据
	 * @return object
	 */
	public static function init(Medoo $db, $table='', $install = false){
		if(!(self::$_instance instanceof self)){
			self::$_instance = new self();
		}

		// get instance of Medoo
		self::$_db = $db;

		if(trim($table)){
			self::$_table = trim($table);
		}else{
			throw new \Exception("Table Name required.");
		}

		// 初始化 创建表并插入数据
		$install && self::install();

		return self::$_instance;
	}

	/**
	 * 根据地区ID或地区编码获取单个或多个地区信息
	 * 当参数均为默认时，返回省份列表数据
	 * @access public
	 * @param string|int|array $regionCode 地区ID，12位地区编码或地区ID、地区编码的数组
	 * @param int $regionLevel 地区层级，0不限，2省份，3城市，4县区，5乡镇
	 * @return array[regionId, regionCode, regionName, regionLevel]
	 */
	public function get($regionCode = '', $regionLevel = 0){
		$condition = [];

		if (is_array($regionCode)) {
			// 传入多个地区ID或编码
			if(!empty($regionCode)){
				$condition['OR'] = [
					self::REGION_ID	=>	$regionCode,
					self::REGION_CODE	=>	$regionCode,
				];
			}
		}elseif (preg_match('/^\d{12}$/', $regionCode)) {
			// 传入的是地区编码
			$condition[ self::REGION_CODE ] = $regionCode;
		}elseif(preg_match('/^[1-9]\d*$/', $regionCode)){
			// 传入的是地区ID
			$condition[ self::REGION_ID ] = $regionCode;
		}elseif ($regionCode) {
			// 无效的地区编码数据
			throw new \Exception("Invalid region code");
		}

		if(!in_array($regionLevel, self::$regionLevelArr)){
			throw new \Exception("Invalid region level");
		}

		// 是否默认获取省份列表
		$default = false;
		if(!$regionCode && !$regionLevel){
			$regionLevel = 2; // province
			$default = true;
		}

		if($regionLevel){
			$condition[ self::REGION_LEVEL ] = $regionLevel;
		}

		$region = self::$_db->select( self::$_table, [self::REGION_ID, self::REGION_CODE, self::REGION_NAME, self::REGION_LEVEL], $condition );
		if(!$region){
			$region = [];
		}

		return !$default && !is_array($regionCode) && !empty($region) ? $region[0] : $region;
	}

	/**
	 * 根据地区ID或地区编码获取下一级地区列表
	 * @access public
	 * @param string|int $regionCode 地区ID或12位地区编码
	 * @param int $regionLevel 想要获取的子地区层级，0默认下一级，2省份，3城市，4县区，5乡镇，一般不用
	 * @return array[regionId, regionCode, regionName, regionLevel] 返回的始终是一个二维数组，即使查询结果只有一个
	 */
	public function child($regionCode = '', $regionLevel = 0){
		$condition = [];

		if (preg_match('/^\d{12}$/', $regionCode)) {
			// 传入的是地区编码
			$condition[ self::REGION_CODE ] = $regionCode;
		}elseif(preg_match('/^[1-9]\d*$/', $regionCode)){
			// 传入的是地区ID
			$condition[ self::REGION_ID ] = $regionCode;
		}else {
			// 无效的地区编码数据
			throw new \Exception("Invalid region code");
		}

		if(!in_array($regionLevel, self::$regionLevelArr)){
			throw new \Exception("Invalid region level");
		}

		// 查询当前地区信息
		$parent = self::$_db->get( self::$_table, [ self::REGION_ID, self::REGION_CODE, self::REGION_LEVEL ], $condition );
		if(!$parent){
			// 未找到地区信息
			throw new \Exception("Region not found");
		}

		$max_code = self::getMaxCode($parent[self::REGION_CODE], $parent[self::REGION_LEVEL]);

		$min_code = self::getMinCode($parent[self::REGION_CODE], $parent[self::REGION_LEVEL]);

		$condition = [
			self::REGION_LEVEL	=>	$regionLevel ? $regionLevel : $parent[self::REGION_LEVEL] + 1, // 往下一层
			self::REGION_CODE.'[<>]' 	=>	[ $min_code, $max_code ],
		];

		// 某些特殊的地级市没有下属的县区，直接下属乡镇，如4419, 4420, 4604
		if(!$regionLevel && in_array($parent[self::REGION_CODE], self::$specialRegionCode)){
			$condition[ self::REGION_LEVEL ] = $parent[self::REGION_LEVEL] + 2;
		}

		// 查询子地区信息
		return self::$_db->select( self::$_table, [self::REGION_ID, self::REGION_CODE, self::REGION_NAME, self::REGION_LEVEL], $condition );
	}

	/**
	 * 根据地区ID或地区编码获取地区所在的位置，如市则返回省市，区则返回省市区
	 * 返回的数据按层级增序排序，包含参数所在地区
	 * @access public
	 * @param string|int $regionCode 地区ID或12位地区编码
	 * @return array[regionId, regionCode, regionName, regionLevel] 返回的始终是一个二维数组，即使查询结果只有一个
	 */
	public function position($regionCode = ''){
		$condition = [];

		if (preg_match('/^\d{12}$/', $regionCode)) {
			// 传入的是地区编码
			$condition[ self::REGION_CODE ] = $regionCode;
		}elseif(preg_match('/^[1-9]\d*$/', $regionCode)){
			// 传入的是地区ID
			$condition[ self::REGION_ID ] = $regionCode;
		}else {
			// 无效的地区编码数据
			throw new \Exception("Invalid region code");
		}

		// 查询当前地区信息
		$region = self::$_db->get( self::$_table, [ self::REGION_ID, self::REGION_CODE, self::REGION_NAME, self::REGION_LEVEL ], $condition );
		if(!$region){
			// 未找到地区信息
			throw new \Exception("Region not found");
		}

		// 返回数据
		$data = [];

		// 根据当前不同层级获取上级信息
		if( $region[ self::REGION_LEVEL ] == 2 ){
			// 当前为省
			$data[] = $region;
			
		}else {
			// 当前不为省
			$parent_code = self::getMinCode( $region[self::REGION_CODE], $region[ self::REGION_LEVEL ]-1 );
			$data = $this->position( $parent_code );
			array_push($data, $region);
		}

		return $data;
	}

	/**
	 * 根据地区名称搜索地区信息
	 * @access public
	 * @param string $regionName 地区名
	 * @param int $regionLevel 地区层级 0不限，2省份，3城市，4县区，5乡镇
	 * @param bool $strictMode 是否严格模式，严格模式下搜索将精准匹配，非严格模式下搜索将模糊匹配，默认非严格模式
	 * @return array[regionId, regionCode, regionName, regionLevel] 返回的始终是一个二维数组，即使查询结果只有一个
	 */
	public function search($regionName = '', $regionLevel = 0, $strictMode = self::NOT_STRICT){
		if(!trim($regionName)){
			throw new \Exception("Region name required");
		}

		if(!in_array($regionLevel, self::$regionLevelArr)){
			throw new \Exception("Invalid region level");
		}

		if($strictMode){
			// 严格模式，精准查询
			$condition = [
				self::REGION_NAME 	=>	trim($regionName),
			];
		}else{
			// 非严格模式，模糊查询
			$condition = [
				self::REGION_NAME.'[~]'	=>	trim($regionName), // like '%$regionName%'
			];
		}

		if($regionLevel){
			$condition[ self::REGION_LEVEL ] = $regionLevel;
		}

		return self::$_db->select( self::$_table, [self::REGION_ID, self::REGION_CODE, self::REGION_NAME, self::REGION_LEVEL], $condition );
	}

	/**
	 * 获取某个地区的最大地区编码
	 * @access private
	 * @param string $regionCode 12位地区编码
	 * @param int $regionLevel 地区层级
	 * @return string 返回12位地区编码
	 */
	private static function getMaxCode($regionCode = '', $regionLevel = 0){
		$letter_len = self::getCodeLetterLength($regionLevel);

		if(!$letter_len){
			return $regionCode;
		}

		return substr($regionCode, 0, $letter_len) . str_repeat('9', 12 - $letter_len);
	}

	/**
	 * 获取某个地区的最小地区编码
	 * @access private
	 * @param string $regionCode 12位地区编码
	 * @param int $regionLevel 地区层级
	 * @return string 返回12位地区编码
	 */
	private static function getMinCode($regionCode = '', $regionLevel = 0){
		$letter_len = self::getCodeLetterLength($regionLevel);

		if(!$letter_len){
			return $regionCode;
		}

		return substr($regionCode, 0, $letter_len) . str_repeat('0', 12 - $letter_len);
	}

	/**
	 * 根据地区层级获取有效地区编码长度，如省级有效编码长度为2
	 * @access private
	 * @param int $regionLevel 地区层级
	 * @return int 返回长度数字
	 */
	private static function getCodeLetterLength($regionLevel = 0){
		
		switch ($regionLevel) {
			case 2:
				$letter_len = 2;
				break;
			case 3:
				$letter_len = 4;
				break;
			case 4:
				$letter_len = 6;
				break;
			case 5:
				$letter_len = 9;
				break;
			default:
				$letter_len = 0;
				break;
		}

		return $letter_len;
	}

	/**
	 * 初始化创建地区表并导入地区数据
	 * @access private
	 * @return bool true成功或跳过，false则失败
	 */
	private static function install(){
		$region_data_dir = dirname(dirname(__FILE__)) . '/res/sql/';
		if(!is_dir($region_data_dir)){
			// sql文件目录不存在 直接返回
			return false;
		}
		if(file_exists($region_data_dir.'region_data_1.sql') && !self::checkTableExists() ){
			// start transaction
			self::$_db->action(function() use ($region_data_dir){
				// 表不存在 创建表
				if(!self::installTable()){
					// 表创建失败
					// throw new \Exception("Install table failed, please try again later");
					return false; // rollback
				}

				// 添加数据
				if(!self::installData($region_data_dir)){
					// throw new \Exception("Add region data failed, please try again later");
					return false; // rollback
				}
			});
		}
		return true;
	}

	/**
	 * 当数据表不存在时，创建表
	 * @access private
	 * @return bool true成功或跳过，false则失败
	 */
	private static function installTable(){
		self::$_db->query("CREATE TABLE `". self::$_table ."` (
			  `regionId` int(11) NOT NULL AUTO_INCREMENT,
			  `regionCode` char(12) DEFAULT '' COMMENT '12位地区编码',
			  `regionName` varchar(120) DEFAULT '' COMMENT '地区名',
			  `regionLevel` tinyint(1) DEFAULT '0' COMMENT '地区层级，1国家2省3市4县5乡镇6村委会',
			  PRIMARY KEY (`regionId`),
			  UNIQUE KEY `code` (`regionCode`),
			  KEY `regionName` (`regionName`),
			  KEY `regionLevel` (`regionLevel`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='地区信息表';");

		// 再次检查是否成功
		return self::checkTableExists();
	}

	/**
	 * 检查地区表是否存在
	 * @access private
	 * @return bool true存在，false不存在
	 */
	private static function checkTableExists(){
		return self::$_db->count(
			self::$_table, 
			[self::REGION_CODE => self::$specialRegionCode[0]]
		) !== false;
	}

	/**
	 * 初始化地区数据
	 * sql文件目录：/res/sql/
	 * @access private
	 * @param $region_data_dir 地区数据文件目录
	 * @return bool true成功或跳过，false失败
	 */
	private static function installData($region_data_dir){
		for ($i=1; $i < 16; $i++) { 
			$sql = file_get_contents( $region_data_dir . 'region_data_'. $i .'.sql' );
			$sql = mb_ereg_replace("`region`", "`". self::$_table ."`", $sql); // 替换表名
			self::$_db->query($sql); // 执行sql
		}
		if(!self::$_db->count(self::$_table, [self::REGION_CODE => self::$specialRegionCode[0]])){
			// 添加数据失败
			return false;
		}
		// success
		return true;
	}
	
}