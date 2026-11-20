import Constants from 'expo-constants';
import { Platform } from 'react-native';

const configuracion = Constants.expoConfig?.extra;
export const laboratorio = Platform.OS === 'android' && configuracion?.laboratorio;
export let servidor = configuracion?.servidor?.replace(/\/+$/, '');

export function validarOrigen(valor) {
    const partes = /^(https?):\/\/([0-9a-z.-]+)(?::([0-9]+))?\/?$/i.exec(valor.trim());
    if (!partes || (partes[3] && (+partes[3] < 1 || +partes[3] > 65535))) {
        throw new Error('La dirección debe empezar con http:// o https:// e incluir un servidor válido.');
    }
    const host = partes[2].toLowerCase();
    const ip = host.split('.').map(Number);
    const privada = /^\d{1,3}(\.\d{1,3}){3}$/.test(host) && ip.every(n => n <= 255)
        && (ip[0] === 10 || ip[0] === 127 || (ip[0] === 192 && ip[1] === 168) || (ip[0] === 172 && ip[1] >= 16 && ip[1] <= 31));
    if (partes[1].toLowerCase() === 'http' && !(laboratorio && privada)) {
        throw new Error('Usa HTTPS. El laboratorio Android admite HTTP únicamente en una red privada.');
    }
    return `${partes[1].toLowerCase()}://${host}${partes[3] ? ':' + partes[3] : ''}`;
}

export function fijarOrigen(valor) {
    servidor = validarOrigen(valor);
}

export async function api(ruta, { token, datos, origen = servidor } = {}) {
    if (!origen) throw new Error('Configura la dirección del servidor RSU.');
    const destino = validarOrigen(origen);
    const abortar = new AbortController();
    const limite = setTimeout(() => abortar.abort(), 12000);
    try {
        const respuesta = await fetch(destino + '/api/' + ruta, {
            method: datos === undefined ? 'GET' : 'POST',
            headers: {
                Accept: 'application/json',
                ...(datos !== undefined && { 'Content-Type': 'application/json' }),
                ...(token && { Authorization: 'Bearer ' + token }),
            },
            body: datos === undefined ? undefined : JSON.stringify(datos),
            signal: abortar.signal,
            redirect: 'error',
        });
        if (respuesta.status === 204) return {};
        const contenido = await respuesta.json().catch(() => null);
        if (!respuesta.ok) {
            const error = new Error(Object.values(contenido?.errors ?? {}).flat().join('\n') || contenido?.message || 'El servidor no pudo completar la operación.');
            error.status = respuesta.status;
            throw error;
        }
        if (!contenido) throw new Error('El servidor devolvió una respuesta inválida.');
        return contenido;
    } catch (error) {
        if (['AbortError', 'TypeError'].includes(error.name)) throw new Error('No se pudo conectar con el servidor. Comprueba tu conexión.');
        throw error;
    } finally {
        clearTimeout(limite);
    }
}

export function hora(fecha) {
    return new Intl.DateTimeFormat('es-PE', {
        timeZone: 'America/Lima', weekday: 'short', day: 'numeric', month: 'short',
        hour: 'numeric', minute: '2-digit',
    }).format(new Date(fecha));
}
