<?php

/**
 * Created by PhpStorm.
 * User: wangxk1991@gmail.com
 * Date: 17/3/28
 * Time: 下午9:01
 * Desc: 系统核心功能文件
 */

namespace fire;

use const app\common\APP_MSG;
use const fire\common\FIRE_MSG;
use fire\common\FireCode;
use fire\core\exception\ErrorException;

class Fire {
    const RUNTIME_AUTH       = 0775;
    const DEFAULT_CONTROLLER =
        '<?php 
namespace app\\%s\\controller;

use fire\\Controller;

class Index extends Controller{

    public function show() {
        $this->result["data"]="Welcome to Fire World!";
        
        return $this->result;
    }
}';
    const DEFAULT_ROUTE      = '<?php 
return [
    "index"         => [
        "controller" => "%s/controller/Index.php",
        "method"     => [
            "get" => [
                "cp"     => [],
                "action" => "show"
            ]
        ],
    ],
];';
    const DEFAULT_CONFIG     =
        '<?php 
return [

];';

    static public function run() {
        // 加载系统配置文件
        \fire\Config::set(include CONF_PATH . 'config.php');
        $configFiles = \fire\Config::get('fire_common_config_files');

        if (!empty($configFiles)) {
            foreach ($configFiles as $name => $configFile) {
                // 加载系统配置文件
                \fire\Config::load($configFile, $name);
            }
        }
        // 系统缓存检查
        self::checkRuntime();
    }

    /**
     * 检查运行文件夹是否存在，不存在则进行初始化工作
     */
    public static function checkRuntime() {
        // TODO 优化此处的路径获取方式
        if (is_dir(RUNTIME_PATH)) {
            try {
                if (!is_dir(LOG_PATH)) {
                    File::makeDir(LOG_PATH, fire::RUNTIME_AUTH);
                }
                if (!is_dir(TEMP_PATH)) {
                    File::makeDir(TEMP_PATH, fire::RUNTIME_AUTH);
                }
                if (!is_dir(DATA_PATH)) {
                    File::makeDir(DATA_PATH, fire::RUNTIME_AUTH);
                }
                if (!is_dir(CACHE_PATH)) {
                    File::makeDir(CACHE_PATH, fire::RUNTIME_AUTH);
                }
            } catch (ErrorException $e) {
                Logger::error("RUNTIME_INIT_FAILED", [$e->getMessage()]);
                Response::sendException(FireCode::ERR_CHECK_RUNTIME, FIRE_MSG[FireCode::ERR_CHECK_RUNTIME], $e);
            }
        } else {
            try {
                //注意第一次创建runtime文件夹不要使用File::makeDir函数，会导致报错不准确
                File::makeDir(RUNTIME_PATH, fire::RUNTIME_AUTH);
                File::makeDir(LOG_PATH, fire::RUNTIME_AUTH);
                File::makeDir(TEMP_PATH, fire::RUNTIME_AUTH);
                File::makeDir(DATA_PATH, fire::RUNTIME_AUTH);
                File::makeDir(CACHE_PATH, fire::RUNTIME_AUTH);

            } catch (ErrorException $e) {
                Logger::error("RUNTIME_INIT_FAILED", [$e->getMessage()]);
                Response::sendException(FireCode::ERR_CHECK_RUNTIME, FIRE_MSG[FireCode::ERR_CHECK_RUNTIME], $e);
            }
        }
    }

    /**
     * 检查app是否创建，未创建则创建
     */
    public static function checkApp() {
        // TODO 优化此处的路径获取方式
        if (!is_dir(APP_PATH)) {
            try {
                File::makeDir(APP_PATH, fire::RUNTIME_AUTH);
            } catch (ErrorException $e) {
                Response::sendException(FireCode::ERR_CHECK_APPS, APP_MSG[FireCode::ERR_CHECK_APPS], $e);
            }
        }
        $apps = Config::get('apps');
        if (!empty($apps)) {
            foreach ($apps as $app) {
                if (!is_dir(APP_PATH . "$app")) {
                    try {
                        File::makeDir(APP_PATH . "$app", fire::RUNTIME_AUTH);
                        File::makeDir(APP_PATH . "$app/controller", fire::RUNTIME_AUTH);
                        File::makeDir(APP_PATH . "$app/service", fire::RUNTIME_AUTH);
                        File::makeDir(APP_PATH . "$app/model", fire::RUNTIME_AUTH);
                        File::makeDir(APP_PATH . "$app/conf", fire::RUNTIME_AUTH);
                    } catch (ErrorException $e) {
                        Response::sendException(FireCode::ERR_CHECK_APPS, APP_MSG[FireCode::ERR_CHECK_APPS], $e);
                    }
                }
                // 创建每个app的默认控制器Index,如果已经存在控制器则不创建默认的控制器
                if (!glob(APP_PATH . "$app/controller/Index.php")) {
                    try {
                        $code = sprintf(fire::DEFAULT_CONTROLLER, $app);
                        File::write(APP_PATH . "$app/controller/Index.php", $code);

                        $code = fire::DEFAULT_CONFIG;
                        File::write(APP_PATH . "$app/conf/config.php", $code);

                        $code = sprintf(fire::DEFAULT_ROUTE, $app);
                        File::write(APP_PATH . "$app/route.php", $code);
                    } catch (ErrorException $e) {
                        Response::sendException(FireCode::ERR_CHECK_APPS, APP_MSG[FireCode::ERR_CHECK_APPS], $e);
                    }

                }
            }
        } else {
            Response::sendError(FireCode::ERR_CONF_NO_APP, FIRE_MSG[FireCode::ERR_CONF_NO_APP]);
        }
    }


    /**
     * @param Route|null $check
     *
     * @throws ErrorException
     *
     * 检查路由
     */
    public static function checkRoute(Route $route = null, $config = []) {
        if (!is_null($route)) {
            // 获取路由表
            $action    = '';
            $class     = '';
            $namespace = '';
            $inputs    = $route->getInputs();
            $routeInfo = $route->getRoute();
            if (!empty($routeInfo)) {
                if (!is_dir(APP_PATH . $routeInfo['app'])) {
                    Response::sendError(FireCode::ERR_ROUTE_APP, FIRE_MSG[FireCode::ERR_ROUTE_APP]);
                }
                $routeTmp = trim($routeInfo['resource'], '/');
                if (array_key_exists($routeTmp, $config)) {
                    $routeConfig = $config["$routeTmp"];
                    $class       = $routeConfig["controller"];
                    $namespace   = str_replace("/", "\\", substr($class, 0, -4));
//                    // 判断请求方式是否出错
//                    if (!array_key_exists($route->getRequest()->getType(), $routeConfig["method"])) {
//                        Response::sendError(FireCode::ERR_REQUEST_METHOD, FIRE_MSG[FireCode::ERR_REQUEST_METHOD]);
//                    }
                    $action = empty($routeConfig["method"][$route->getMethod()]['action']) ? $route->getMethod() : $routeConfig["method"][$route->getMethod()]['action'];
                    // 校验请求参数
                    foreach ($routeConfig['method'][$route->getMethod()]['cp'] as $param => $input) {
                        try {

                            if ($input[1] == 1) {
                                $validate = Validate::validate($inputs[trim($param)], strtoupper(trim($input[0])), !isset($input[2]) ? '' : $input[2]);
                                if ($validate != 0) {
                                    Logger::error("ERR_REQUEST_PARAM_VALIDATE", [$param]);
                                    Response::sendError($validate, FIRE_MSG[$validate]);
                                }
                                //过滤参数中的空格
                                $inputs[$param] = trim($inputs[$param]);
                            }
                            if ($input[1] == 0 && (!empty($inputs[$param]) || isset($inputs[$param]))) {
                                $validate = Validate::validate($inputs[trim($param)], strtoupper(trim($input[0])), !isset($input[2]) ? '' : $input[2]);
                                if ($validate != 0) {
                                    Logger::error("ERR_REQUEST_PARAM_VALIDATE", [$param, $inputs[$param]]);
                                    Response::sendError($validate, FIRE_MSG[$validate]);
                                }
                                //过滤参数中的空格
                                $inputs[$param] = trim($inputs[$param]);
                            }

                        } catch (ErrorException $e) {
                            Logger::error("ERR_REQUEST_PARAM_VALIDATE", [$e->getMessage()]);
                            Response::sendException(FireCode::ERR_REQUEST_PARAM_VALIDATE, FIRE_MSG[FireCode::ERR_REQUEST_PARAM_VALIDATE], $e);
                        }
                    }
                }
            }
            $routeInfo['action']    = $action;
            $routeInfo['class']     = $class;
            $routeInfo['inputs']    = $inputs;
            $routeInfo['namespace'] = join('\\', [Config::get('app_namespace'), $namespace]);

            return $routeInfo;
        } else {
            //TODO 添加http请求错误相关的异常和日志
            Logger::error("ERR_REQUEST_ROUTE");
            Response::sendError(FireCode::ERR_REQUEST_ROUTE, FIRE_MSG[FireCode::ERR_REQUEST_ROUTE]);
        }

        return true;
    }
}