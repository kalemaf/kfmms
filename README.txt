Free-cmms is an open-source maintenance management suite.
Copyright (C) 2003  Chris Morris

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
*************************************************************************


INTORDUCTION 

Hello and thank you for trying free-cmms. Currently the free-cmms code
is functional but lacking good documentation. I am a Project Engineer
with a BS in mechanical engineering. This is my first attemp at
software development. I offer this document to help you set free-cmms
on your system and try it out.

Free-cmms was developed using MySQL 3.23.53 & 4.1.1, PHP 4.2.3 &
4.3.4, and Apache 2.0.43. If you are unfamiliar with these, you should
take the time to read about them. Some suggested web sites are:

http://www.onlamp.com/

http://sourceforge.net/projects/phptriad/ - for windows

And of course:

php.net

mysql.com

apache.org

INSTALLATION

0)You must have a working installation of MySQL, PHP, and Apache
  (other web servers will probably work also). Try Triad is using
  windows. If using Linux you probably already have these packages
  installed.

1)If you haven't already, download the latest free-cmms package from
  http://sourceforge.net/projects/free-cmms/. Unzip the file into your
  webserver's document root directory

2)Edit config.inc.php to include the name, user, and password for your
  MySQL server. Adjust any other configuration parameter now as well.

3)Open a web browser and point it to <web root>/free-cmms/setup.php
  where <web root> is the name of your web server. If you do not know
  what adress to use try 'http://localhost/free-cmms/setup.php'.

3b)setup.php will create the database & tables, populate the utility 
   tables, and add an example work order. If you have any errors make
   sure that your MySQL and PHP are properly installed and that
   config.inc.php is properly configured.

4)Populate the database with your equipment and personnel. I suggest
  using phpMyAdmin (http://sourceforge.net/projects/phpmyadmin/) for a
  MySQL frontend. Look for equipment and personnel editors to be
  native to free-cmms in future releases.

5)Point a browser to %webserver%/free-cmms/index.php and login as
  user=manager / password=manager.

6)Remove setup.php from a web facing directory.

7)Change user name and password in the MySQL tables.


PLEASE email me <mechtonia at users dot sourceforge dot net>if you
downloaded free-cmms. Tell me about your experience and what you are
looking for in a CMMS. 

SEND ME A POSTCARD

Also, I do not ask for money for this project but I would greatly
enjoy a postcard from your town, city, village, or locale. So if you
choose to send me a postcard, send it to:

Chris Morris
279 Hicks St
Bells, TN 38006
USA

Many thanks and enjoy.

NOTE (schema update): The `mechanics` table now includes two additional
columns used by the application: `shift` (TINYINT) and `active` (TINYINT).
If you restore the database from `database.sql`, those columns will be
created automatically. If you upgrade an existing installation manually,
run the provided `migrate_mechanics.php` script to add the missing columns.

PM FEATURES (new):
- New tables: `pm_schedules` and `pm_instances` support preventive maintenance.
- Use `migrate_pm.php` to add PM tables on existing installations.
- Use `generate_pm.php` to run PM generation (can be run manually or scheduled via cron).
- The `pm` tab now shows a basic dashboard with due items and compliance metrics.

