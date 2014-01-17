-- phpMyAdmin SQL Dump
-- version 2.10.0.2
-- http://www.phpmyadmin.net
-- 
-- Host: localhost:3306
-- Generation Time: May 08, 2007 at 01:10 PM
-- Server version: 5.0.24
-- PHP Version: 5.1.6

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

-- 
-- Database: `wikibox_db`
-- 

-- --------------------------------------------------------

-- 
-- Table structure for table `box`
-- 

CREATE TABLE `box` (
  `box_id` int(10) unsigned NOT NULL auto_increment,
  `template` varchar(255) NOT NULL,
  `page_name` varchar(255) NOT NULL,
  `page_uid` varchar(255) NOT NULL,
  `box_uid` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL,
  `headings` varchar(255) NOT NULL,
  `heading_style` varchar(255) NOT NULL,
  `box_style` varchar(255) NOT NULL,
  `timestamp` int(11) NOT NULL,
  PRIMARY KEY  (`box_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

-- 
-- Table structure for table `row`
-- 

CREATE TABLE `row` (
  `row_id` int(10) unsigned NOT NULL auto_increment,
  `box_id` int(10) unsigned NOT NULL,
  `owner_uid` int(10) default NULL,
  `row_data` text NOT NULL,
  `row_style` varchar(255) NOT NULL,
  `row_sort_order` int(11) NOT NULL,
  `timestamp` int(10) unsigned NOT NULL,
  PRIMARY KEY  (`row_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;
