const conductor = process.env.EXPO_PUBLIC_RSU_APP === 'conductor';
const packageId = conductor ? 'pe.rsu.conductor' : 'pe.rsu.cliente';
const servidor = process.env.EXPO_PUBLIC_RSU_API_URL;
const laboratorio = process.env.RSU_MOBILE_LAB === '1';

const plugins = conductor ? [
    ['expo-location', { locationAlwaysAndWhenInUsePermission: 'RSU registra la ubicación solo durante un recorrido activo.', isAndroidBackgroundLocationEnabled: true, isAndroidForegroundServiceEnabled: true, isIosBackgroundLocationEnabled: true }],
    'expo-secure-store',
    'expo-sqlite',
    './withUbicacionConductor',
] : [['expo-location', { locationWhenInUsePermission: 'RSU usa tu ubicación para encontrar tu zona.' }], 'expo-secure-store'];
if (laboratorio) plugins.push('./withLoopback');

module.exports = {
    name: conductor ? 'RSU Conductor' : 'RSU Recolección',
    slug: conductor ? 'rsu-conductor' : 'rsu-recoleccion',
    version: '1.2.0',
    orientation: 'portrait',
    extra: { variante: conductor ? 'conductor' : 'cliente', servidor, laboratorio },
    android: {
        package: packageId,
        versionCode: 3,
        allowBackup: false,
        ...(conductor ? {} : {
            blockedPermissions: [
                'android.permission.ACCESS_BACKGROUND_LOCATION',
                'android.permission.FOREGROUND_SERVICE_LOCATION',
                'android.permission.FOREGROUND_SERVICE',
            ],
        }),
    },
    ios: { bundleIdentifier: packageId, supportsTablet: true },
    plugins,
};
