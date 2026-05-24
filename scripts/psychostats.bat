@echo off
@SETLOCAL
pushd "%~dp0"
chcp 65001 > nul 2>&1

rem -- This file is for window's systems so a user can simply double-click 
rem -- on the icon to update their stats. This causes the dos window to pause
rem -- and not close instantly incase there are errors.
rem -----------------------------------------------------------------------

perl ..\stats.pl -version
echo.
perl ..\stats.pl -verbose
echo.

rem -- remove the line below if you plan on scheduling this through Window's explorer.
echo (remove the ^pause^ command from this file if you do not want it to pause here)
pause
