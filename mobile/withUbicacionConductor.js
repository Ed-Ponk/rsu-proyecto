const { withAndroidManifest } = require('@expo/config-plugins');

const permisosDeCaptura = [
    'android.permission.ACCESS_BACKGROUND_LOCATION',
    'android.permission.FOREGROUND_SERVICE',
    'android.permission.FOREGROUND_SERVICE_LOCATION',
];

module.exports = config => withAndroidManifest(config, resultado => {
    for (const permiso of resultado.modResults.manifest['uses-permission'] ?? []) {
        if (permisosDeCaptura.includes(permiso.$['android:name']) && permiso.$['tools:node'] === 'remove') {
            delete permiso.$['tools:node'];
        }
    }
    return resultado;
});
