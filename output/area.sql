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