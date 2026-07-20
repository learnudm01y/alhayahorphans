@echo off
:loop
echo Starting Laravel Queue Worker...
php artisan queue:work
echo Worker crashed or stopped. Restarting in 3 seconds...
timeout /t 3 /nobreak > nul
goto loop
