import io, os

ROOT = r'C:\xampp\htdocs\alhayahorphans'
V3 = ROOT + r'\android-v3\app\src\main\java'
V4 = ROOT + r'\android-v4\app\src\main\java'

files = [
    'com/aso/app/ApiConfig.java',
    'com/aso/app/AutoUploadApplication.java',
    'com/aso/app/CameraActivity.java',
    'com/aso/app/ChunkedUploadWorker.java',
    'com/aso/app/DriveStatusWorker.java',
    'com/aso/app/FileSyncWorker.java',
    'com/aso/app/MainActivity.java',
    'com/aso/app/PhotoActivity.java',
    'com/aso/app/SmartMediaWorker.java',
    'com/aso/app/SponsorshipSyncWorker.java',
    'com/aso/app/UnifiedNetworkMonitor.java',
    'com/aso/app/UploadDatabaseHelper.java',
    'com/aso/app/UploadTaskScheduler.java',
    'org/alhayah/sponsorships/BackgroundSyncPlugin.java',
    'org/alhayah/sponsorships/CivilRegistryStore.java',
    'org/alhayah/sponsorships/ConsoleMessageInterceptor.java',
    'org/alhayah/sponsorships/DataSyncForegroundService.java',
    'org/alhayah/sponsorships/DownloadForegroundService.java',
    'org/alhayah/sponsorships/JavaScriptBridge.java',
    'org/alhayah/sponsorships/NetworkConnectedWorker.java',
]

for rel in files:
    a = io.open(os.path.join(V3, rel), encoding='utf-8', errors='replace').read().splitlines()
    b = io.open(os.path.join(V4, rel), encoding='utf-8', errors='replace').read().splitlines()
    bs = set(x.strip() for x in b)
    missing = [x for x in a if x.strip() and x.strip() not in bs]
    print('---- %s : %d ----' % (rel, len(missing)))
    for x in missing:
        print('   |', x.strip()[:160])
