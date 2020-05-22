<?php

/**
 * @noinspection SqlResolve
 * @noinspection SqlNoDataSourceInspection
 * @noinspection SqlDialectInspection
 */
class Areas {

    const AREAS_JSON_FILE = __DIR__ . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'area.json';
    const OUTPUT_FILE_PATH = __DIR__ . DIRECTORY_SEPARATOR . 'output' . DIRECTORY_SEPARATOR . 'area.sql';
    const GET_AREAS_URL = 'https://gw.api.tbsandbox.com/router/rest';
    const GET_TOWNS_URL = 'https://lsp.wuliu.taobao.com/locationservice/addr/output_address_town_array.do';
    const VALUE_SQL = 'INSERT INTO `area` VALUES ';

    // AUTO_INCREMENT
    const TABLE_SQL = <<<EOF
DROP TABLE IF EXISTS `area`;
CREATE TABLE `area` (
  `id` INT(10) UNSIGNED NOT NULL,
  `type` VARCHAR(8) NOT NULL COMMENT '区域类型， 1 - 国家；2 - 省/自治区/直辖市；3 - 地级市/辖区；4 - 县/县级市/区；5 - 街道/乡镇',
  `name` VARCHAR(64) NOT NULL COMMENT '地域名称',
  `parent_id` INT(10) UNSIGNED DEFAULT '0' COMMENT '父节点区域标识',
  PRIMARY KEY (`id`),
  KEY `name` (`name`),
  KEY `parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='地域表';

EOF;

    private $secret = 'test';

    private $requestParams = [
        'app_key' => 'test',
        'fields' => 'id,type,name,parent_id',
        'force_sensitive_param_fuzzy' => 'true',
        'format' => 'json',
        'method' => 'taobao.areas.get',
        'partner_id' => 'top-apitools',
        'sign_method' => 'md5',
        'v' => '2.0',
    ];

    private $getTowns = false;

    private $countries;
    private $provinces;
    private $cities;
    private $districts;
    private $abroad;

    public function __construct() {
        $this->requestParams['timestamp'] = date('Y-m-d H:i:s');
    }

    public function needTowns($need = false) {
        $this->getTowns = $need;
        return $this;
    }

    public function generate() {
        if ($this->getCountryProvinceCityDistrict()->makeSqlFile()) {
            echo "生成结束，SQL 文件位于 output 目录内\n";
        }
    }

    private function getSign($params) {
        ksort($params, SORT_STRING);

        $paramsStr = '';
        foreach ($params as $key => $param) {
            $paramsStr .= $key . $param;
        }

        return strtoupper(md5($this->secret . $paramsStr . $this->secret));
    }

    /**
     * @return bool|string
     */
    private function getAreasData() {
        if (file_exists(self::AREAS_JSON_FILE)) {
            // file size
            if (filesize(self::AREAS_JSON_FILE) / 1024 > 256) {
                return file_get_contents(self::AREAS_JSON_FILE);
            }
        }

        $this->requestParams['sign'] = $this->getSign($this->requestParams);
        $url = self::GET_AREAS_URL . '?' . http_build_query($this->requestParams);

        echo "请求地址：{$url}\n";

        $jsonFile = file_get_contents($url);

        file_put_contents(self::AREAS_JSON_FILE, $jsonFile);

        return $jsonFile;
    }

    private function getCountryProvinceCityDistrict() {
        $areasData = $this->getAreasData();

        if ($areasData) {
            $areasData = function_exists('jsond_decode')
                ? jsond_decode($areasData, true)
                : json_decode($areasData, true);

            if (isset($areasData['areas_get_response'])) {
                foreach ($areasData['areas_get_response']['areas'] as $areas) {
                    foreach ($areas as $area) {
                        switch ($area['type']) {
                            case 1:
                                $this->countries[] = $area;
                                break;
                            case 2:
                                $this->provinces[] = $area;
                                break;
                            case 3:
                                $this->cities[] = $area;
                                break;
                            case 4:
                                $this->districts[] = $area;
                                break;
                            default:
                                $this->abroad[] = $area;
                        }
                    }
                }
            }
        }

        return $this;
    }

    private function appendSqlValues($file_path, $areas, $last = false) {
        $count = count($areas);
        foreach ($areas as $key => $area) {
            $data = "({$area['id']},{$area['type']},'{$area['name']}',{$area['parent_id']}),";

            if ($last && $key === $count) {
                $data = rtrim($data, ',');
            }

            if (file_put_contents($file_path, $data, FILE_APPEND) === false) {
                unlink($file_path);
                die("追写文件失败，请重试\n");
            }
        }
    }

    private function getTowns() {
        $ret = [];
        foreach ($this->districts as $district) {
            $url = self::GET_TOWNS_URL . "?l1={$district['parent_id']}&l2={$district['id']}";
            for ($i = 0; $i < 3; $i++) {
                $towns = file_get_contents($url);
                if ($towns && stripos($towns, 'callback({success:true,result') !== false) {
                    break;
                }
                usleep(500);
                continue;
            }

            if (isset($towns) && $towns) {
                $towns = json_decode(str_replace("'", '"', substr($towns, 30, -3)), true);
                foreach ($towns as $town) {
                    $ret[] = [
                        'id' => $town[0],
                        'type' => 5,
                        'name' => $town[1],
                        'parent_id' => $town[2]
                    ];
                }
            } else {
                echo "该地址：{$url}，请求三次失败，请手动添加该数据\n";
            }
        }
        return $ret;
    }

    /**
     * @return bool
     */
    private function makeSqlFile() {
        if (empty($this->countries)) {
            exit('数据为空，无法生成文件');
        }

        if (file_put_contents(self::OUTPUT_FILE_PATH, self::TABLE_SQL) === false) {
            exit('写入文件失败，请检查权限');
        }

        file_put_contents(self::OUTPUT_FILE_PATH, self::VALUE_SQL, FILE_APPEND);

        echo "----- 生成国家数据 ------\n";
        $this->appendSqlValues(self::OUTPUT_FILE_PATH, $this->countries);
        echo "----- 生成省份数据 ------\n";
        $this->appendSqlValues(self::OUTPUT_FILE_PATH, $this->provinces);
        echo "----- 生成省市数据 ------\n";
        $this->appendSqlValues(self::OUTPUT_FILE_PATH, $this->cities);
        echo "----- 生成市区数据 ------\n";
        $this->appendSqlValues(self::OUTPUT_FILE_PATH, $this->districts);

        if ($this->getTowns) {
            $begin = date('Y-m-d H:i:s');
            echo "----- 生成街道数据开始，时间：{$begin} ------\n";
            ini_set('memory_limit', '512M');
            $towns = $this->getTowns();
            $this->appendSqlValues(self::OUTPUT_FILE_PATH, $towns);
            $end = date('Y-m-d H:i:s');
            echo "----- 生成街道数据结束，时间：{$end} ------\n";
        }

        file_put_contents(self::OUTPUT_FILE_PATH, ';', FILE_APPEND);

        return true;
    }
}
