<?php

include_once 'Areas.php';

$areas = new Areas();

/**
 * needTowns 用于开启生成街道/乡镇等第四级位置信息，type 字段国家为 1，故街道对应 type = 5
 * tmp/area.json 文件仅对应三级位置信息，文件来自淘宝开放平台，第四级通过淘宝物流接口一一获取，消耗时间较长
 *
 * 如：广东省 - 广州市 - 白云区 - 三元里街道
 */
$areas->needTowns(true)->generate();
