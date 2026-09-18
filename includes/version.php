<?php
/**
 * 资源版本控制
 */

// 版本号 - 修改此版本号强制刷新所有静态资源
define('ASSET_VERSION', '10.15.49');  // 全站静态资源版本；专题页使用局部版本参数

/**
 * 生成带版本号的资源URL
 * @param string $path 资源路径
 * @return string 带版本号的完整URL
 */
function asset_url_auto($path) {
    // 如果路径已经包含查询参数，使用&连接
    $separator = strpos($path, '?') !== false ? '&' : '?';
    return $path . $separator . 'v=' . ASSET_VERSION;
}
