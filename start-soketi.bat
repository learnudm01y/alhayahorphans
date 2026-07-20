@echo off
echo Starting Local WebSockets Server (Soketi) for Real-time Sync...
echo Using Isolated Node v20 Engine to avoid Node v22 compatibility issues.
echo =================================================================
.\node18.exe node_modules\@soketi\soketi\bin\server.js start
pause
