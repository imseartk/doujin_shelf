# Personal Doujin Helper Android App

Native-first Android client for Circle.ms catalog data, offline maps, favorites, and circles.

The Android app is intentionally independent from the existing Doujin Shelf website. Its catalog data is designed to be downloaded into a local SQLite database after Circle.ms app authorization. The current debug build includes a C108 preview mode while the C109 API authorization interval is unavailable.

## Local Setup

The repository already contains the Gradle wrapper and Android project. A JDK 17 and Android SDK 35 installation are enough to build it; Android Studio is optional.

```powershell
cd mobile/c108-helper/android
$env:JAVA_HOME = 'C:\Android\jdk17\jdk-17.0.20+8'
./gradlew.bat assembleDebug
```

The debug APK is generated at `android/app/build/outputs/apk/debug/app-debug.apk`.

## Build Flow

The Circle.ms callback placeholder is `tw.artick.doujinhelper://auth?status=success`. The final authorization URL and token exchange must be connected after developer registration provides the private API specification.
