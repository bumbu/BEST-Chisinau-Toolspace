SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;


CREATE TABLE IF NOT EXISTS `files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `last_version` tinyint(4) NOT NULL DEFAULT '1',
  `title` varchar(127) NOT NULL,
  `name` varchar(63) CHARACTER SET armscii8 NOT NULL,
  `published` tinyint(4) NOT NULL DEFAULT '0',
  `author` int(10) NOT NULL,
  `approved` tinyint(2) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=29 ;

CREATE TABLE IF NOT EXISTS `files_tags` (
  `file_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `files_versions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `file_id` int(11) NOT NULL,
  `version` tinyint(3) NOT NULL DEFAULT '1',
  `size` int(11) NOT NULL,
  `extension` varchar(7) CHARACTER SET ascii DEFAULT NULL,
  `added_at` datetime NOT NULL,
  `added_by` int(7) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=86 ;

CREATE TABLE IF NOT EXISTS `tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(15) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `title` (`title`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=29 ;

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(7) NOT NULL AUTO_INCREMENT,
  `email` varchar(31) NOT NULL,
  `name` varchar(31) NOT NULL,
  `password` varchar(31) NOT NULL,
  `role` varchar(15) NOT NULL DEFAULT 'visitor',
  `approved` tinyint(1) NOT NULL DEFAULT '0',
  `blocked` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=9 ;

CREATE TABLE IF NOT EXISTS `user_activities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `action` varchar(31) CHARACTER SET ascii NOT NULL,
  `element_id` int(11) NOT NULL,
  `datetime` datetime NOT NULL,
  `details` varchar(63) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=274 ;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
