SET NAMES utf8;
SET time_zone = '+00:00';

DROP DATABASE IF EXISTS `depkasa`;
CREATE DATABASE `depkasa` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `depkasa`;

DROP TABLE IF EXISTS `transactions`;
CREATE TABLE `transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(255) NOT NULL,
  `ts_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `amount` int(10) unsigned NOT NULL,
  `currency` char(3) NOT NULL,
  `transaction_foreign` char(32) DEFAULT NULL,
  `pending_count` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `status` varchar(100) NOT NULL DEFAULT 'init',
  `comment` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `transactions_reference_no_IDX` (`reference_no`,`ts_created`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DELIMITER ;;

CREATE TRIGGER `after_insert_transaction` AFTER INSERT ON `transactions` FOR EACH ROW
BEGIN
	INSERT INTO transaction_statuses ( transaction_id, status ) VALUES (NEW.id, NEW.status);
END;;

CREATE TRIGGER `after_update_transaction` AFTER UPDATE ON `transactions` FOR EACH ROW
BEGIN
	IF NEW.status <> OLD.status THEN
		INSERT INTO transaction_statuses ( transaction_id, status ) VALUES (NEW.id, NEW.status);
	END IF;
END;;

DELIMITER ;

DROP TABLE IF EXISTS `transaction_statuses`;
CREATE TABLE `transaction_statuses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `transaction_id` bigint(20) unsigned NOT NULL,
  `status` varchar(100) NOT NULL,
  `ts_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `transaction_statuses_transaction_id_IDX` (`transaction_id`,`ts_created`) USING BTREE,
  CONSTRAINT `transaction_statuses_transactions_FK` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;