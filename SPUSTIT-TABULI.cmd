@echo off
setlocal
set "MINILIDI_URL=http://127.0.0.1:8091/"
set "MINILIDI_PHP=D:\laragon\bin\php\php-8.5.12-nts-Win32-vs17-x64\php.exe"
call :check
if not errorlevel 1 goto open
if not exist "%MINILIDI_PHP%" (
  where php >nul 2>nul
  if errorlevel 1 (
    echo PHP nebylo nalezeno. Nastav cestu k php.exe v tomto souboru.
    pause
    exit /b 1
  )
  set "MINILIDI_PHP=php"
)
start "Minilidi - mistni server" /min "%MINILIDI_PHP%" -S 127.0.0.1:8091 -t "%~dp0tabule\public"
curl.exe -fsS --noproxy "*" --connect-timeout 1 --max-time 2 --retry 5 --retry-connrefused --retry-delay 1 "%MINILIDI_URL%" >nul 2>nul
call :check
if errorlevel 1 (
  echo Tabuli se nepodarilo spustit na %MINILIDI_URL%
  echo Zkontroluj okno Minilidi - mistni server.
  pause
  exit /b 1
)
:open
start "" "%MINILIDI_URL%"
exit /b 0

:check
curl.exe -fsS --noproxy "*" --connect-timeout 1 --max-time 2 "%MINILIDI_URL%" 2>nul | findstr.exe /l /c:"<h1>Minilidi</h1>" >nul
exit /b %errorlevel%
