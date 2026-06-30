@echo off
setlocal

set APP_HOME=%~dp0
set PROPS_FILE=%APP_HOME%gradle\wrapper\gradle-wrapper.properties

if not exist "%PROPS_FILE%" (
  echo Missing %PROPS_FILE%
  exit /b 1
)

for /f "tokens=1,* delims==" %%A in ('findstr /b "distributionUrl=" "%PROPS_FILE%"') do set DIST_URL=%%B
set DIST_URL=%DIST_URL:\=%

for %%F in ("%DIST_URL%") do set ZIP_NAME=%%~nxF
set DIST_NAME=%ZIP_NAME:.zip=%
set CACHE_DIR=%USERPROFILE%\.slid-gradle-wrapper
set ZIP_PATH=%CACHE_DIR%\%ZIP_NAME%
set EXTRACT_DIR=%CACHE_DIR%\%DIST_NAME%

if not exist "%CACHE_DIR%" mkdir "%CACHE_DIR%"

if not exist "%EXTRACT_DIR%\bin\gradle.bat" (
  if not exist "%ZIP_PATH%" (
    powershell -Command "Invoke-WebRequest -Uri '%DIST_URL%' -OutFile '%ZIP_PATH%'"
  )
  powershell -Command "Expand-Archive -Path '%ZIP_PATH%' -DestinationPath '%CACHE_DIR%' -Force"
)

call "%EXTRACT_DIR%\bin\gradle.bat" --project-dir "%APP_HOME%" %*
