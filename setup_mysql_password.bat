@echo off
REM MySQL Root Password Setup Script
REM Run this as Administrator

echo Stopping MySQL service...
net stop MYSQL80

echo Starting MySQL in safe mode...
start "MySQL Safe Mode" mysqld --skip-grant-tables --user=mysql

echo.
echo MySQL is now running in safe mode.
echo In a new command prompt, run:
echo mysql -u root
echo Then in MySQL:
echo FLUSH PRIVILEGES;
echo ALTER USER 'root'@'localhost' IDENTIFIED BY 'yournewpassword';
echo EXIT;
echo.
echo After that, press Ctrl+C here to stop safe mode, then run:
echo net start MYSQL80
echo.
echo Then update config.inc.php with your password and run setup_database.php
echo.
pause