import * as SQLite from 'expo-sqlite';
import { digestStringAsync, CryptoDigestAlgorithm } from 'expo-crypto';

const vigencia = 6 * 60 * 60 * 1000;
let conexion;
function base() {
    return conexion ??= SQLite.openDatabaseAsync('consulta-ciudadana.db').then(async db => {
        await db.execAsync('PRAGMA secure_delete=ON; CREATE TABLE IF NOT EXISTS consultas (sesion TEXT, alcance TEXT, ruta TEXT, fecha INTEGER, datos TEXT, PRIMARY KEY (sesion, alcance, ruta));');
        return db;
    }).catch(error => { conexion = null; throw error; });
}

function clave({ token, origen }) {
    return digestStringAsync(CryptoDigestAlgorithm.SHA256, origen + '\n' + token);
}

function minimizar(ruta, datos) {
    if (ruta === 'perfil') {
        const { id, zone_id } = datos.ciudadano;
        return { ciudadano: { id, zone_id } };
    }
    if (/^programaciones\/\d+$/.test(ruta)) {
        const { conductor, ayudantes, placa, recorrido, ...resumen } = datos.data;
        const viaje = recorrido ? { ...recorrido, valoracion_propia: recorrido.valoracion_propia
            ? { stars: recorrido.valoracion_propia.stars } : null } : null;
        return { data: { ...resumen, ayudantes: [], recorrido: viaje } };
    }
    const { data, current_page, last_page } = datos;
    return { data, current_page, last_page };
}

export async function guardarConsulta(sesion, alcance, ruta, datos, vigente = () => true) {
    const firma = await clave(sesion);
    const db = await base();
    const fecha = Date.now();
    const contenido = JSON.stringify(minimizar(ruta, datos));
    if (contenido.length > 750_000) return;
    await db.withExclusiveTransactionAsync(async tx => {
        if (!vigente()) return;
        await tx.runAsync('DELETE FROM consultas WHERE fecha < ?', fecha - vigencia);
        await tx.runAsync('INSERT OR REPLACE INTO consultas VALUES (?,?,?,?,?)', firma, String(alcance), ruta, fecha, contenido);
        await tx.runAsync('DELETE FROM consultas WHERE sesion=? AND rowid NOT IN (SELECT rowid FROM consultas WHERE sesion=? ORDER BY fecha DESC, rowid DESC LIMIT 40)', firma, firma);
    });
}

export async function leerConsulta(sesion, alcance, ruta) {
    const db = await base();
    const dato = await db.getFirstAsync('SELECT fecha, datos FROM consultas WHERE sesion=? AND alcance=? AND ruta=? AND fecha>=?',
        await clave(sesion), String(alcance), ruta, Date.now() - vigencia);
    return dato ? { fecha: dato.fecha, datos: JSON.parse(dato.datos) } : null;
}

export async function borrarConsultas(sesion) {
    const db = await base();
    await db.runAsync('DELETE FROM consultas WHERE sesion=?', await clave(sesion));
}
