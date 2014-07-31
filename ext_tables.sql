#
# Table structure for table 'irc'
#
CREATE TABLE irc (
	uid int(11) NOT NULL auto_increment,
	pid int(11) DEFAULT '0' NOT NULL,
	tstamp int(11) DEFAULT '0' NOT NULL,
	filename varchar(255) DEFAULT '' NOT NULL

	PRIMARY KEY (uid)
);
