import { useEffect, useState } from 'react';
import * as SecureStore from 'expo-secure-store';
import { api, fijarOrigen, laboratorio, servidor, validarOrigen } from './api';

export function useSesion(rol) {
    const clave = 'rsu.v3.' + rol;
    const [token, ponerToken] = useState(null);
    const [origen, ponerOrigen] = useState(servidor ?? '');
    const [lista, preparar] = useState(false);
    const [errorSesion, ponerError] = useState('');

    useEffect(() => {
        SecureStore.getItemAsync(clave).then(valor => {
            const anterior = valor ? JSON.parse(valor) : null;
            if (!anterior || (!laboratorio && anterior.origen !== servidor)) return;
            fijarOrigen(anterior.origen);
            ponerOrigen(anterior.origen);
            ponerToken(anterior.token);
        }).catch(() => ponerError('No se pudo recuperar la sesión guardada.')).finally(() => preparar(true));
    }, [clave]);

    async function guardar(nuevo, destino = origen) {
        await SecureStore.setItemAsync(clave, JSON.stringify({ origen: destino, token: nuevo }));
        ponerToken(nuevo);
    }

    async function cambiarOrigen(valor) {
        const destino = validarOrigen(valor);
        const salud = await api('v1/salud', { origen: destino });
        if (salud.servicio !== 'rsu' || salud.version !== 3) throw new Error('El servidor no ofrece la API RSU V3.');
        if (destino === origen) return;
        await guardar(null, destino);
        fijarOrigen(destino);
        ponerOrigen(destino);
    }

    return { token, origen, lista, guardar, cambiarOrigen, errorSesion };
}

export function validarAcceso(email, password) {
    return [
        !email.trim() ? 'Ingrese su correo.' : !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim()) ? 'Ingrese un correo válido.' : '',
        !password ? 'Ingrese su contraseña.' : '',
    ].filter(Boolean).join('\n');
}
