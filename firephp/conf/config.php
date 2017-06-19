<?php
/**
 * Created by PhpStorm.
 * User: wangxk1991@gmail.com
 * Date: 17/3/29
 * Time: 上午9:28
 * Desc: 系统配置
 */
return [
    // 默认时区
    'default_timezone'         => 'Asia/Shanghai',

    // log文件前缀
    'log_file_prefix'          => 'log',

    /**
     * 自定义系统级别的配置文件
     * key是配置的参数名，value是配置文件的路径
     */
    'fire_common_config_files' => [
        'fire_log'    => CONF_PATH . 'log.php',
        'data_source' => CONF_PATH . 'datasource.php',
        'charts_info' => CONF_PATH . 'charts.php',
    ],

    /**
     * session配置
     */
    'session'                  => [
        'prefix'     => 'module',
        'type'       => '',
        'auto_start' => true,
    ],

];
