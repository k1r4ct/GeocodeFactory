
CREATE TABLE IF NOT EXISTS `#__geofactory_ggmaps` (
  `id` INTEGER NOT NULL auto_increment,
  `name` VARCHAR(255) NOT NULL DEFAULT '',
  `alias` VARCHAR(255) NOT NULL DEFAULT '',
  `template` TEXT NOT NULL,
  `extrainfo` TEXT NOT NULL,  
  `mapwidth` VARCHAR(75) NOT NULL default 'px',
  `mapheight` VARCHAR(75) NOT NULL default 'px',
  `totalmarkers` INTEGER NOT NULL DEFAULT '0',
  `centerlat` VARCHAR(33) NOT NULL default '45.543',
  `centerlng` VARCHAR(33) NOT NULL default '-73.604',
  `state` TINYINT(3) NOT NULL DEFAULT '0',
  `language` VARCHAR(255) NOT NULL DEFAULT '*',
  `checked_out` int(11) NOT NULL DEFAULT '0',
  `checked_out_time` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `params_map_cluster` text NOT NULL,
  `params_map_radius` text NOT NULL,
  `params_additional_data` text NOT NULL,
  `params_map_types` text NOT NULL,
  `params_map_controls` text NOT NULL,
  `params_map_settings` text NOT NULL,
  `params_map_mouse` text NOT NULL,
  `params_extra` text NOT NULL,
  PRIMARY KEY  (`id`),
  INDEX `idx_state` (`state`)
)  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `#__geofactory_markersets` (
  `id` INTEGER NOT NULL auto_increment,
  `name` VARCHAR(255) NOT NULL DEFAULT '',
  `extrainfo` TEXT NOT NULL,
  `template_bubble` TEXT NOT NULL,
  `template_sidebar` TEXT NOT NULL,
  `typeList` VARCHAR(33) NOT NULL default '',
  `ordering` INTEGER NOT NULL DEFAULT 0,
  `state` TINYINT(3) NOT NULL DEFAULT '0',
  `language` VARCHAR(255) NOT NULL DEFAULT '*',
  `mslevel` int(11) NOT NULL DEFAULT '0',
  `checked_out` int(11) NOT NULL DEFAULT '0',
  `checked_out_time` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `params_markerset_settings` text NOT NULL,
  `params_markerset_radius` text NOT NULL,
  `params_markerset_icon` text NOT NULL,
  `params_markerset_type_setting` text NOT NULL,
  `params_extra` text NOT NULL,
  PRIMARY KEY  (`id`),
  INDEX `idx_state` (`state`)
)  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `#__geofactory_link_map_ms` (
  `id_map` INTEGER NOT NULL DEFAULT '0',
  `id_ms` INTEGER NOT NULL DEFAULT '0'
)  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `#__geofactory_assignation` (
  `id` INTEGER NOT NULL auto_increment,
  `name` VARCHAR(255) NOT NULL DEFAULT '',
  `typeList` VARCHAR(33) NOT NULL default '',
  `extrainfo` TEXT NOT NULL,
  `field_latitude` VARCHAR(255) NOT NULL DEFAULT '',
  `field_longitude` VARCHAR(255) NOT NULL DEFAULT '',
  `field_street` VARCHAR(255) NOT NULL DEFAULT '',
  `field_postal` VARCHAR(255) NOT NULL DEFAULT '',
  `field_city` VARCHAR(255) NOT NULL DEFAULT '',
  `field_county` VARCHAR(255) NOT NULL DEFAULT '',
  `field_state` VARCHAR(255) NOT NULL DEFAULT '',
  `field_country` VARCHAR(255) NOT NULL DEFAULT '',
  `state` TINYINT(3) NOT NULL DEFAULT '0',
  `checked_out` int(11) NOT NULL DEFAULT '0',
  `checked_out_time` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY  (`id`),
  INDEX `idx_state` (`state`)
  )  DEFAULT CHARSET=utf8;

