-- phpMyAdmin SQL Dump
-- version 3.3.10.4
-- http://www.phpmyadmin.net
--
-- Хост: mysql.best-chisinau.org
-- Время создания: Авг 20 2012 г., 13:45
-- Версия сервера: 5.1.53
-- Версия PHP: 5.2.17

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- База данных: `bestch_toolspace`
--

-- --------------------------------------------------------

--
-- Структура таблицы `files`
--

CREATE TABLE IF NOT EXISTS `files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `last_version` tinyint(4) NOT NULL DEFAULT '1',
  `any_approved` tinyint(1) NOT NULL DEFAULT '0',
  `all_approved` tinyint(1) NOT NULL DEFAULT '0',
  `title` varchar(127) NOT NULL,
  `name` varchar(63) CHARACTER SET armscii8 NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=23 ;

-- --------------------------------------------------------

--
-- Структура таблицы `files_tags`
--

CREATE TABLE IF NOT EXISTS `files_tags` (
  `file_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Структура таблицы `files_versions`
--

CREATE TABLE IF NOT EXISTS `files_versions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `file_id` int(11) NOT NULL,
  `version` tinyint(3) NOT NULL DEFAULT '1',
  `has_thumb` tinyint(1) NOT NULL DEFAULT '0',
  `size` int(11) NOT NULL,
  `name` varchar(63) CHARACTER SET ascii NOT NULL,
  `extension` varchar(7) CHARACTER SET ascii DEFAULT NULL,
  `extension_thumb` varchar(7) CHARACTER SET ascii NOT NULL,
  `mime_type` varchar(31) CHARACTER SET ascii NOT NULL,
  `approved` tinyint(1) NOT NULL DEFAULT '0',
  `added_at` datetime NOT NULL,
  `added_by` int(7) NOT NULL,
  `approved_at` datetime NOT NULL,
  `approved_by` int(7) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=53 ;

-- --------------------------------------------------------

--
-- Структура таблицы `tags`
--

CREATE TABLE IF NOT EXISTS `tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(15) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `title` (`title`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=29 ;

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(7) NOT NULL AUTO_INCREMENT,
  `email` varchar(31) NOT NULL,
  `name` varchar(31) NOT NULL,
  `password` varchar(31) NOT NULL,
  `role` varchar(15) NOT NULL DEFAULT 'user',
  `approved` tinyint(1) NOT NULL DEFAULT '0',
  `blocked` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=9 ;

-- --------------------------------------------------------

--
-- Структура таблицы `user_activities`
--

CREATE TABLE IF NOT EXISTS `user_activities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `action` varchar(31) CHARACTER SET ascii NOT NULL,
  `element_id` int(11) NOT NULL,
  `datetime` datetime NOT NULL,
  `details` varchar(63) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=237 ;
