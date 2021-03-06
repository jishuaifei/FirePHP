<?php
/**
 * Created by PhpStorm.
 * User: wangxk1991@gmail.com
 * Date: 17/3/30
 * Time: 上午10:16
 * Desc: fire自动加载器
 */

namespace fire;


use const fire\common\FIRE_MSG;
use fire\common\FireCode;

class Loader {
    protected static $instance = [];
    // 类名映射
    protected static $map = [];

    // PSR-4
    private static $prefixLengthsPsr4 = [];
    private static $prefixDirsPsr4    = [];
    private static $fallbackDirsPsr4  = [];

    // PSR-0
    private static $prefixesPsr0     = [];
    private static $fallbackDirsPsr0 = [];

    // 自动加载
    public static function autoload($class) {
        if ($file = self::findFile($class)) {

            // Win环境严格区分大小写
            if (IS_WIN && pathinfo($file, PATHINFO_FILENAME) != pathinfo(realpath($file), PATHINFO_FILENAME)) {
                return false;
            }
            __include_file($file);

            return true;
        } else {
            Response::sendError(FireCode::ERR_AUTOLOAD_FILE_UNEXISTED, FIRE_MSG[FireCode::ERR_AUTOLOAD_FILE_UNEXISTED]);
        }
    }

    /**
     * 查找文件
     *
     * @param $class
     *
     * @return bool
     */
    private static function findFile($class) {
        if (!empty(self::$map[$class])) {
            // 类库映射
            return self::$map[$class];
        }

        // 查找 PSR-4
        $logicalPathPsr4 = strtr($class, '\\', DS) . EXT;

        $first = $class[0];
        if (isset(self::$prefixLengthsPsr4[$first])) {
            foreach (self::$prefixLengthsPsr4[$first] as $prefix => $length) {
                if (0 === strpos($class, $prefix)) {
                    foreach (self::$prefixDirsPsr4[$prefix] as $dir) {
                        if (is_file($file = $dir . DS . substr($logicalPathPsr4, $length))) {
                            return $file;
                        }
                    }
                }
            }
        }

        // 查找 PSR-4 fallback dirs
        foreach (self::$fallbackDirsPsr4 as $dir) {
            if (is_file($file = $dir . DS . $logicalPathPsr4)) {
                return $file;
            }
        }

        // 查找 PSR-0
        if (false !== $pos = strrpos($class, '\\')) {
            // namespaced class name
            $logicalPathPsr0 = substr($logicalPathPsr4, 0, $pos + 1)
                . strtr(substr($logicalPathPsr4, $pos + 1), '_', DS);
        } else {
            // PEAR-like class name
            $logicalPathPsr0 = strtr($class, '_', DS) . EXT;
        }

        if (isset(self::$prefixesPsr0[$first])) {
            foreach (self::$prefixesPsr0[$first] as $prefix => $dirs) {
                if (0 === strpos($class, $prefix)) {
                    foreach ($dirs as $dir) {
                        if (is_file($file = $dir . DS . $logicalPathPsr0)) {
                            return $file;
                        }
                    }
                }
            }
        }

        // 查找 PSR-0 fallback dirs
        foreach (self::$fallbackDirsPsr0 as $dir) {
            if (is_file($file = $dir . DS . $logicalPathPsr0)) {
                return $file;
            }
        }

        return self::$map[$class] = false;
    }

    // 注册命名空间
    public static function addNamespace($namespace, $path = '') {
        if (is_array($namespace)) {
            foreach ($namespace as $prefix => $paths) {
                self::addPsr4($prefix . '\\', rtrim($paths, DS), true);
            }
        } else {
            self::addPsr4($namespace . '\\', rtrim($path, DS), true);
        }
    }

    // 添加Psr4空间
    private static function addPsr4($prefix, $paths, $prepend = false) {
        if (!$prefix) {
            // Register directories for the root namespace.
            if ($prepend) {
                self::$fallbackDirsPsr4 = array_merge(
                    (array)$paths,
                    self::$fallbackDirsPsr4
                );
            } else {
                self::$fallbackDirsPsr4 = array_merge(
                    self::$fallbackDirsPsr4,
                    (array)$paths
                );
            }
        } elseif (!isset(self::$prefixDirsPsr4[$prefix])) {
            // Register directories for a new namespace.
            $length = strlen($prefix);
            if ('\\' !== $prefix[$length - 1]) {
                Response::sendException(FireCode::ERR_NAMESPACE_PSR4_PREFIX, FIRE_MSG[FireCode::ERR_NAMESPACE_PSR4_PREFIX], ['A non-empty PSR-4 prefix must end with a namespace separator.']);
            }
            self::$prefixLengthsPsr4[$prefix[0]][$prefix] = $length;
            self::$prefixDirsPsr4[$prefix]                = (array)$paths;
        } elseif ($prepend) {
            // Prepend directories for an already registered namespace.
            self::$prefixDirsPsr4[$prefix] = array_merge(
                (array)$paths,
                self::$prefixDirsPsr4[$prefix]
            );
        } else {
            // Append directories for an already registered namespace.
            self::$prefixDirsPsr4[$prefix] = array_merge(
                self::$prefixDirsPsr4[$prefix],
                (array)$paths
            );
        }
    }

    // 注册classmap
    public static function addClassMap($class, $map = '') {
        if (is_array($class)) {
            self::$map = array_merge(self::$map, $class);
        } else {
            self::$map[$class] = $map;
        }
    }

    public static function register($autoload = '') {
        // 注册系统自动加载
        spl_autoload_register($autoload ?: 'fire\\Loader::autoload', true, true);
        // 注册命名空间定义
        self::addNamespace([
            'fire' => FIRE_PATH . DS,
        ]);
    }

    /**
     * 初始化类的实例
     * @return void
     */
    public static function clearInstance() {
        self::$instance = [];
    }

}

/**
 * 作用范围隔离
 *
 * @param $file
 *
 * @return mixed
 */
function __include_file($file) {
    return include $file;
}

function __require_file($file) {
    return require $file;
}
