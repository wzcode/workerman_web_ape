<?php

namespace lib\base;

/**
 * 数据库封装,不用orm，api追求性能
 */
class DBBase {
	protected static $created_at = "created_at";
	protected static $updated_at = "updated_at";
	protected static $deleted_at = "deleted_at";
	
	/**
	 * 添加
	 */
	public static function add(&$arr = null) {
		global $db;
		global $database;
		global $global;
		if ($arr == null) {
			return false;
		}
		$tm = date ( "Y-m-d H:i:s", time () );
		// 写入创建时间和更新时间
		$arr [self::$created_at] = $tm;
		$arr [self::$updated_at] = $tm;
		
		$arr ["id"] = $db->insert ( static::$table )->cols ( $arr )->query ();
		// 添加存入缓存
	
		if ($database ["cache"] === true) {
			$_key = "id_cache_" . static::$table . "_" . $arr ["id"];
			$global->$_key = $arr;
		}
	}
	
	/**
	 * 修改
	 * $id可以不传，默认采用$arr里面的id
	 *
	 * @param unknown $arr        	
	 */
	public static function update(&$arr = null, $id = null) {
		global $db;
		global $database;
		global $global;
		if ($arr == null) {
			return false;
		}
		if (! isset ( $arr ["id"] )) {
			return false;
		}
		if ($id == null) {
			$id = $arr ["id"];
		}
		$whereStr = "id='" . $id . "'";
		
		// 判断缓存的数据和新数据是否一模一样，一样的话，不更新
		if ($database ["cache"] === true) {
			$_key = "id_cache_" . static::$table . "_" . $id;
			var_export ( $global->$_key ,true);
			if (isset ( $global->$_key ) && $id == $arr ["id"] && $global->$_key === $arr) {
				return true;
			}
		}
		
		// 设置更新时间
		$tm = date ( "Y-m-d H:i:s", time () );
		$arr [self::$updated_at] = $tm;
		
		if (array_key_exists ( self::$deleted_at, $arr )) {
			if ($arr [self::$deleted_at] == null) {
				unset ( $arr [self::$deleted_at] );
			}
		}
		
		if ($db->update ( static::$table )->cols ( $arr )->where ( $whereStr )->query () == null) {
			return false;
		} else {
			if ($database ["cache"] === true) {
				// 缓存删除修改前的数据
				$_key = "id_cache_" . static::$table . "_" . $id;
				unset ( $global->$_key );
				// 缓存存入新的数据
				$_key = "id_cache_" . static::$table . "_" . $arr ["id"];
				$global->$_key = $arr;
			}
			return true;
		}
	}
	
	/**
	 * 删除 传入id删除
	 */
	public static function delete($id = null) {
		global $db;
		global $database;
		global $global;
		if ($id == null) {
			return false;
		}
		
		// 在缓存中删除
		if ($database ["cache"] === true) {
			$_key = "id_cache_" . static::$table . "_" . $id;
			unset ( $global->$_key );
		}
		
		// 是否使用软删除
		if (static::$softDelete) {
			$tm = date ( "Y-m-d H:i:s", time () );
			$model = self::find ( $id );
			$model [self::$deleted_at] = $tm;
			return self::update ( $model );
		}
		
		// 真删除
		$db->delete ( static::$table )->where ( "id='$id'" )->query ();
	}
	
	/**
	 * 通过id或者where差一个
	 *
	 * @param string $order        	
	 * @return array|mixed|NULL|string
	 */
	public static function find($id = null) {
		global $db;
		global $database;
		global $global;
		if ($id == null) {
			return null;
		}
		// 如果是$where是数字类型，那么判断缓存中是否有
		// 如果有缓存，直接返回缓存值
		if ($database ["cache"] === true) {
			$_key = "id_cache_" . static::$table . "_" . $id;
			if (isset ( $global->$_key )) {
				var_export ( $global->$_key ,true);
				return $global->$_key;
			}
		}
		$where = "id='$id'";
		
		if (static::$softDelete) {
			$where = "$where and " . self::$deleted_at . " is null";
		}
		$res = $db->row ( "SELECT * FROM `" . static::$table . "` WHERE $where" );
		if ($res) {
			// 将数据存入缓存
			if ($database ["cache"] === true) {
				$_key = "id_cache_" . static::$table . "_" . $id;
				$global->$_key = $res;
			}
			return $res;
		} else {
			return null;
		}
	}
	
	/**
	 * 查询全部
	 */
	public static function all($where = "1=1", $order = "id desc") {
		global $db;
		if (static::$softDelete) {
			$where = "$where and " . self::$deleted_at . " is null";
		}
		$arr = $db->query ( "select * FROM `" . static::$table . "` where $where order by $order" );
		if ($arr == null) {
			return array ();
		} else {
			return $arr;
		}
	}
	
	/**
	 * 查询全部
	 */
	public static function count($where = "1=1") {
		global $db;
		if (static::$softDelete) {
			$where = "$where and " . self::$deleted_at . " is null";
		}
		return $db->column ( "select count(*)  FROM `" . static::$table . "` where $where" ) [0];
	}
	
	/**
	 * 分页查询
	 *
	 * @param number $index        	
	 * @param number $length        	
	 * @param string $where        	
	 * @param string $order        	
	 * @return number|unknown
	 */
	public static function page($index = 0, $length = 0, $where = " 1=1 ", $order = "id desc") {
		global $db;
		if (static::$softDelete) {
			$where = "$where and " . self::$deleted_at . " is null";
		}
		
		$cs = $db->query ( "select * FROM `" . static::$table . "` where $where order by $order LIMIT $length OFFSET $index" );
		$arr ["list"] = $cs;
		$allCount = $db->column ( "select count(*)  FROM `" . static::$table . "` where $where" ) [0];
		$arr ["all_count"] = $allCount;
		if ($length != 0) {
			$yu = 0;
			if ($allCount > $length) {
				if ($allCount % $length > 0) {
					$yu = $allCount / $length + 1;
				} else {
					$yu = $allCount / $length;
				}
			} else {
				$yu = 1;
			}
			$arr ["all_page_num"] = ( int ) $yu;
		} else {
			$arr ["all_page_num"] = 1;
		}
		return $arr;
	}
}