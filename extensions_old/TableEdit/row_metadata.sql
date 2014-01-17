-- phpMyAdmin SQL Dump
-- version 2.10.2
-- http://www.phpmyadmin.net
-- 
-- Host: trimer.local:3306
-- Generation Time: Jul 29, 2007 at 12:09 PM
-- Server version: 5.0.41
-- PHP Version: 5.2.2

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

-- 
-- Database: `wikibox_db`
-- 

-- --------------------------------------------------------

-- 
-- Table structure for table `row_metadata`
-- 

CREATE TABLE IF NOT EXISTS `row_metadata` (
  `row_metadata_id` int(10) unsigned NOT NULL auto_increment,
  `row_id` int(10) unsigned NOT NULL default '0',
  `row_metadata` varchar(255) NOT NULL default '',
  PRIMARY KEY  (`row_metadata_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=141958 ;
