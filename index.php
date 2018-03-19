<?php
include "area.php";
include "area_ext.php";
// 淘宝收货地址页面
$js_url = 'https://g.alicdn.com/vip/address/6.0.14/index-min.js';

// 生成 JS DATA
$c = new area();
$c->setUrl($js_url);
$c->setIsCountry(true);
// 扩展数据，
$c->setExtData($ext);
$c->setMakeJsData(true);
$c->process();

//生成 SQL 和CSV
$c = new area();
$c->setUrl($js_url);
$c->setIsCountry(true);
$c->setMakeCsv(false);
$c->setMakeSql(true);
$c->process();