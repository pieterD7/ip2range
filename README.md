
# Ip2Range
----
## What is Ip2Range?
>Ip2Range creates or queries a mysql database mapping ipV4 addresses to countries with the option of overriding the country code by ranges in cidr notation. For example the ip address of google-bot becomes 'google-bot' instead of 'us'. You are free to enter other ranges.

-----
## Installation

   <code>$ composer install</code>
   
   <code>$ php ip2range.php </code>
   
   <code>$ nano ip2range.ini </code>
   

----
## Usage
Configuration of ip2range.php can be changed by editing the iniFile. The stem of the program name is used determining the default iniFile name. 

Ip2range.php can be called with input from a pipe and to create an ip address information table.

### Create ip ranges database
If a database is given in an iniFile a table with ip ranges can be built from ftp registries of ip address ranges assigned to countries.

   <code>$ php ip2range.php -b</code>

Or with a logfile:

   <code>$ php ip2range.php -b -l logfile &</code>
   
   <code>$ tail -f logfile</code>

Instead of ftp the database can be created using the zip file from ip2nation.com. This database cannot be used for inserting a cidr to.

   <code>$ php ip2range.php -z</code>

----
### Insert cidrs from assets/
Name will be derived from filename. 

   <code>$ php ip2range.php -c</code>

----
### Lookup an ip address

   <code>$ php ip2range.php -ip 104.132.0.1</code>
   
   <code>A 104.132.0.1 (googlebot)</code>

----
### Extend Apache2 log file 

   <code>$ cat access.log | php ip2range.php</code>

----
### As CustomLog in httpd.conf

<code>CustomLog "|/usr/bin/php -f /home/pi/ip2range/ip2range.php >> ${APACHE_LOG_DIR}/extended_access.log" combined</code>

Warning and or error messages are put to stderr and will be visible in http error log file.
