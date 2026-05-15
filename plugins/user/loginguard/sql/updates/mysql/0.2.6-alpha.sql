ALTER TABLE `#__loginguard_attempts` ADD COLUMN `country_code` varchar(10) NOT NULL DEFAULT '' AFTER `country`;
ALTER TABLE `#__loginguard_attempts` ADD COLUMN `region` varchar(100) NOT NULL DEFAULT '' AFTER `country_code`;
ALTER TABLE `#__loginguard_attempts` ADD COLUMN `city` varchar(100) NOT NULL DEFAULT '' AFTER `region`;
ALTER TABLE `#__loginguard_attempts` ADD COLUMN `isp` varchar(255) NOT NULL DEFAULT '' AFTER `city`;
ALTER TABLE `#__loginguard_attempts` ADD COLUMN `asn` varchar(50) NOT NULL DEFAULT '' AFTER `isp`;
