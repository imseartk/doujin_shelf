# C108 Helper Android Shell

Personal Android client for Doujin Shelf C108 data.

The app reads only the private Doujin Shelf API under `/api/app/c108/*`. It does not call Circle.ms directly and does not store Circle.ms OAuth credentials.

## Local Setup

Install Node.js LTS and Android Studio on your development PC.

```powershell
cd mobile/c108-helper
npm install
npm run build
npm run cap:add:android
npm run cap:sync
npm run cap:open
```

In the app settings, set:

- API URL: `https://doujin.artick.tw`
- Passcode: your Doujin Shelf app/admin passcode

## Build Flow

After changing the web app:

```powershell
npm run build
npm run cap:sync
npm run cap:open
```

Then build/run the APK from Android Studio.
