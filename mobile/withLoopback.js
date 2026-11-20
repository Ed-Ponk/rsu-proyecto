const { withAndroidManifest, withDangerousMod } = require('expo/config-plugins');
const fs = require('node:fs/promises');
const path = require('node:path');

module.exports = config => {
    config = withAndroidManifest(config, mod => {
        mod.modResults.manifest.application[0].$['android:networkSecurityConfig'] = '@xml/rsu_network';
        return mod;
    });
    return withDangerousMod(config, ['android', async mod => {
        const carpeta = path.join(mod.modRequest.platformProjectRoot, 'app/src/main/res/xml');
        await fs.mkdir(carpeta, { recursive: true });
        await fs.writeFile(path.join(carpeta, 'rsu_network.xml'), `<?xml version="1.0" encoding="utf-8"?>
<network-security-config>
    <base-config cleartextTrafficPermitted="true" />
</network-security-config>
`);
        return mod;
    }]);
};
