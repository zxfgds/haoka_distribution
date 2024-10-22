<?php

namespace app\logic;

use app\library\RedisCache;
use app\model\Region;
use Illuminate\Support\Arr;
use RedisException;

// todo: 用腾讯地图接口  重新搞一搞 数据库表
class RegionLogic extends BaseLogic
{
	protected static string $model = Region::class;
	
	protected static string $nameCacheKey = "REGION_CACHE_REGIONS";
	
	
	public static function getList(array $conditions = [], int $pageSize = 20, int $page = 1, string $sortBy = 'id', string $sortOrder = 'asc')
	: array {
		$keyword = Arr::pull($conditions, 'keyword');
		$level   = Arr::pull($conditions, 'level');
		if (!empty($keyword)) {
			$conditions[] = [
				'name',
				'like',
				$keyword,
			];
		}
		if ($level !== NULL) {
			$conditions[] = [
				'level',
				$level,
			];
		}
		
		return parent::getList($conditions, $pageSize, $page, $sortBy, $sortOrder); // TODO: Change the autogenerated stub
	}
	
	/**
	 * 获取或创建 regions_name_code_map 缓存
	 * 如果传入 $forceRebuild 为 true，则强制重建缓存
	 * 否则，尝试从缓存中获取数据，如果不存在，则创建并存储缓存
	 *
	 * @param bool $forceRebuild 是否强制重建缓存，默认为 false
	 *
	 * @return array 返回 regions_name_code_map 缓存数据
	 * @throws RedisException
	 */
	public static function getRegionNameCodeMap(bool $forceRebuild = FALSE)
	: array {
		$cacheKey = 'regions_name_code_map';
		
		// 尝试从缓存中获取数据
		$regionsNameCodeMap = RedisCache::get($cacheKey);
		
		// 如果强制重建或缓存不存在
		if ($forceRebuild || $regionsNameCodeMap === NULL) {
			// 从数据库中获取所有区域数据
			$regions = Region::all();
			
			// 构建 regions_name_code_map 缓存数据
			$regionsNameCodeMap = [];
			foreach ($regions as $region) {
				if ($region->level > 2)
					continue;
				$regionsNameCodeMap[$region->level][$region->name]       = $region->code;
				$regionsNameCodeMap[$region->level][$region->short_name] = $region->code;
			}
			
			// 将 regions_name_code_map 缓存数据存储到 RedisCache
			RedisCache::forever($cacheKey, $regionsNameCodeMap);
		}
		
		return $regionsNameCodeMap;
	}
	
	/**
	 * @param $name
	 * @param $level
	 * @return string
	 * @throws RedisException
	 */
	public static function getRegionCode($name, $level)
	: string {
		$codeMap = [];
		if ($level == 0) {
			$codeMap = static::provinceCodeMap();
		} else if ($level == 1) {
			$codeMap = static::cityCodeMap();
		} else if ($level == 2) {
			$codeMap = static::districtCodeMap();
		}
		
		$code = Arr::get($codeMap, $name);
		
		return $code ?? '';
	}
	
	
	/**
	 * @param bool $flush
	 * @return array
	 * @throws RedisException
	 */
	public static function provinceCodeMap(bool $flush = FALSE)
	: array {
		$cacheName = "REGION_PROVINCE_CODE_MAP";
		if ($flush)
			RedisCache::forget($cacheName);
		$data = RedisCache::get($cacheName);
		if (empty($data)) {
			$data  = [];
			$array = Region::where('level', 0)->select('name', 'code', 'parent', 'level', 'short_name')->get();
			foreach ($array as $region) {
				$data[$region->name] = $region->code;
				if (!empty($region->short_name)) {
					$data[$region->short_name] = $region->code;
				}
			}
			RedisCache::forever($cacheName, $data);
		}
		return $data;
	}
	
	
	/**
	 * 生成城市名称与城市代码的映射关系
	 *
	 * @param bool $flush
	 * @return array
	 * @throws RedisException
	 */
	public static function cityCodeMap(bool $flush = FALSE)
	: array {
		// 缓存名称
		$cacheName = "REGION_CITY_CODE_MAP";
		if ($flush)
			RedisCache::forget($cacheName);
		// 尝试从缓存中获取数据
		$data = RedisCache::get($cacheName);
		// 如果缓存中没有数据，则重新生成并存储
		if (empty($data)) {
			$data = [];
			// 查询所有省份信息
			$array = Region::where('level', 1)->select('name', 'code', 'parent', 'level', 'short_name')->get();
			// 遍历所有省份，将省份名称、简称作为数组键，省份代码为数组值，存入映射关系数组中
			foreach ($array as $region) {
				$data[$region->name] = $region->code;
				if (!empty($region->short_name)) {
					$data[$region->short_name] = $region->code;
				}
			}
			// 将映射关系数组存入缓存中
			RedisCache::forever($cacheName, $data);
		}
		return $data;
	}
	
	
	/**
	 * @param bool $flush
	 * @return array
	 * @throws RedisException
	 */
	public static function districtCodeMap(bool $flush = FALSE)
	: array {
		// 定义缓存名称
		$cacheName = "REGION_DISTRICT_CODE_MAP";
		if ($flush)
			RedisCache::forget($cacheName);
		// 从 Redis 中获取区县代码映射表
		$data = RedisCache::get($cacheName);
		// 如果 Redis 中不存在该缓存，则重新生成并设置缓存
		if (empty($data)) {
			// 定义空数组以保存区县代码映射表
			$data = [];
			// 从数据库获取所有属于区县的 Region 对象
			$array = Region::where('level', 2)->select('name', 'code', 'parent', 'level', 'short_name')->get();
			// 遍历 Region 对象，并将其名称与短名称添加到区县代码映射表中
			foreach ($array as $region) {
				$data[$region->name] = $region->code;
				if (!empty($region->short_name)) {
					$data[$region->short_name] = $region->code;
				}
			}
			// 将区县代码映射表缓存至 Redis 中
			RedisCache::forever($cacheName, $data);
		}
		return $data;
	}
	
	
	/**
	 * @throws RedisException
	 */
	public static function rebuildRegionTree()
	: void
	{
		RedisCache::forget("REGION_TREE_DATA");
	}
	
	/**
	 * @throws RedisException
	 */
	public static function regionTree()
	{
		$cacheName = "REGION_TREE_DATA";
		$treeData  = RedisCache::get($cacheName);
		
		if (empty($treeData)) {
			$treeData = [];
			$array    = Region::select('name', 'code', 'parent', 'level', 'short_name')->get();
			foreach ($array as $region) {
				
				if ($region->level == 0) {
					$treeData['p'][$region->code] = $region->name;
				}
				if ($region->level == 1) {
					$treeData['c'][$region->parent][$region->code] = $region->short_name ?? $region->name;
				}
				if ($region->level == 2) {
					$treeData['d'][$region->parent][$region->code] = $region->name;
				}
			}
			RedisCache::forever($cacheName, $treeData);
		}
		return $treeData;
	}
	
	/**
	 * @throws RedisException
	 */
	public static function getFullRegion()
	{
		return RedisCache::get(static::$nameCacheKey);
	}
	
	/**
	 * 创建缓存，将所有省份以及其包含的城市信息存入 RedisCache
	 * @throws RedisException
	 */
	public static function createCache()
	: void
	{
		// 获取所有省份
		$provinces = Region::where('level', 0)->get();
		
		// 用于存储所有省份及其城市信息的数组
		$provinceArray = [];
		
		// 遍历所有省份
		foreach ($provinces as $province) {
			// 构造省份数据
			$provinceData = [
				'code'   => $province->code,
				'name'   => $province->name,
				'cities' => static::getCities($province->code),
			];
			
			// 将省份数据放入数组中
			// 键为省份的 code 和 name，值为省份数据
			$provinceArray[$province->code] = $provinceData;
			$provinceArray[$province->name] = $provinceData;
		}
		
		// 清除旧的缓存
		RedisCache::forget(static::$nameCacheKey);
		
		// 存储新的缓存数据
		RedisCache::forever(static::$nameCacheKey, $provinceArray);
	}
	
	
	/**
	 * @param $provinceCode
	 *
	 * @return array
	 */
	public static function getCities($provinceCode)
	: array {
		$cities    = Region::where('parent', $provinceCode)->where('level', 1)->get();
		$cityArray = [];
		
		foreach ($cities as $city) {
			$cityData = [
				'code' => $city->code,
				'name' => $city->name,
			];
			
			$cityArray[$city->code] = $cityData;
			$cityArray[$city->name] = $cityData;
		}
		
		return $cityArray;
	}
}