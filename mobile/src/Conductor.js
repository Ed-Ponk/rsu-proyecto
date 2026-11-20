import React, { useEffect, useRef, useState } from 'react';
import { Alert, AppState, Text, View } from 'react-native';
import * as Location from 'expo-location';
import { api, hora, laboratorio } from './api';
import { useSesion, validarAcceso } from './sesion';
import { Boton, Campo, Mensaje, Pantalla, estilos } from './ui';
import Mapa from './Mapa';
import { cierrePendiente, detenerGPS, enviarGPS, existeCierre, guardarCierre, iniciarGPS, pendientesGPS, rechazadasGPS, terminarCierre } from './gps';

const titulos = { login: 'RSU Conductor', servidor: 'Configurar servidor', lista: 'Mis Programaciones', hoy: 'Programación de Hoy', pendiente: 'Programación pendiente', activo: 'Recorrido en curso', cerrar: 'Finalizar recorrido', historial: 'Historial de recorridos', detalle: 'Detalle del recorrido', mapa: 'Mapa del recorrido', perfil: 'Mi perfil' };
const horaCorta = fecha => new Intl.DateTimeFormat('es-PE', { timeZone: 'America/Lima', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).format(new Date(fecha));
const hoyLima = () => new Intl.DateTimeFormat('sv-SE', { timeZone: 'America/Lima' }).format(new Date());

export default function Conductor() {
    const sesion = useSesion('conductor');
    const [vista, ver] = useState('login');
    const [menu, abrirMenu] = useState(false);
    const [email, escribirEmail] = useState('');
    const [password, escribirPassword] = useState('');
    const [visible, mostrar] = useState(false);
    const [origen, escribirOrigen] = useState('');
    const [perfil, guardarPerfil] = useState(null);
    const [programaciones, guardarProgramaciones] = useState(null);
    const [programacion, guardarProgramacion] = useState(null);
    const [activo, guardarActivo] = useState(null);
    const [viaje, guardarViaje] = useState(null);
    const [historial, guardarHistorial] = useState(null);
    const [observacion, escribirObservacion] = useState('');
    const [error, ponerError] = useState('');
    const [aviso, ponerAviso] = useState('');
    const [ocupado, marcar] = useState(false);
    const [gps, estadoGPS] = useState('');
    const [pendientes, ponerPendientes] = useState(0);
    const [rechazadas, ponerRechazadas] = useState(0);
    const [ahora, tic] = useState(Date.now());
    const [cierre, ponerCierre] = useState(null);
    const sincronizandoCierre = useRef(false);
    const enviar = (ruta, datos, token = sesion.token) => api('conductor/' + ruta, { token, datos, origen: sesion.origen });

    async function operar(accion) {
        marcar(true); ponerError(''); ponerAviso('');
        try { await accion(); }
        catch (fallo) {
            if (fallo.status === 401 && sesion.token) {
                await detenerGPS(); await sesion.guardar(null); guardarActivo(null); guardarPerfil(null); ponerCierre(null); ver('login');
            }
            ponerError(fallo.message);
        } finally { marcar(false); }
    }
    async function cargar(token = sesion.token, vigente = () => true) {
        const [lista, asignacion, ruta] = await Promise.all([
            enviar('programaciones', undefined, token), enviar('programaciones/hoy', undefined, token), enviar('recorridos/actual', undefined, token),
        ]);
        if (vigente()) {
            guardarPerfil(lista.conductor); guardarProgramaciones(lista.data); guardarProgramacion(asignacion.data); guardarActivo(ruta.data);
        }
        return ruta.data;
    }
    useEffect(() => {
        if (!sesion.token || vista !== 'login') return;
        let vivo = true;
        let consultando = false;
        async function revalidarSesion() {
            if (!vivo || consultando) return;
            consultando = true;
            try {
                await operar(async () => {
                    try {
                        await cargar(sesion.token, () => vivo);
                    } catch (fallo) {
                        if (vivo) throw fallo;
                    }
                    if (vivo) ver('lista');
                });
            } finally { consultando = false; }
        }
        revalidarSesion();
        const reintento = setInterval(revalidarSesion, 5000);
        return () => { vivo = false; clearInterval(reintento); };
    }, [sesion.token, sesion.origen, vista]);
    useEffect(() => {
        if (!sesion.token) return;
        let vivo = true;
        async function recuperarCierre() {
            if (!vivo || sincronizandoCierre.current) return;
            sincronizandoCierre.current = true;
            let solicitud;
            try {
                solicitud = await cierrePendiente(sesion.origen, sesion.token, perfil?.id);
                if (!vivo) return;
                ponerCierre(solicitud);
                if (!solicitud || solicitud.conflicto) return;
                while (await pendientesGPS(sesion.origen, solicitud)) await enviarGPS(sesion.origen, sesion.token, solicitud);
                const cerrado = (await enviar('recorridos/' + solicitud.id + '/finalizar', {
                    observacion: solicitud.observacion, operacion: solicitud.operacion,
                })).data;
                await terminarCierre(solicitud);
                const fuera = await rechazadasGPS(sesion.origen, solicitud);
                ponerCierre(null); guardarActivo(null); guardarViaje(cerrado); escribirObservacion(''); ver('detalle');
                ponerError('');
                ponerAviso('Recorrido finalizado a las ' + horaCorta(cerrado.fin) + '.' +
                    (fuera ? ' ' + fuera + ' ubicaciones fuera de ventana conservadas en el dispositivo.' : ''));
            } catch (fallo) {
                if (solicitud && [403, 404, 409, 422].includes(fallo.status)) {
                    await terminarCierre(solicitud, fallo.message);
                    ponerCierre({ ...solicitud, conflicto: fallo.message });
                } else if (fallo.status === 401 && vivo) {
                    await sesion.guardar(null); guardarActivo(null); guardarPerfil(null); ponerCierre(null); ver('login');
                    ponerAviso('Inicia sesión con el mismo conductor para recuperar el cierre.');
                } else if (vivo && !solicitud) ponerError(fallo.message);
            } finally { sincronizandoCierre.current = false; }
        }
        recuperarCierre();
        const reloj = setInterval(recuperarCierre, 5000);
        return () => { vivo = false; clearInterval(reloj); };
    }, [sesion.token, sesion.origen, perfil?.id]);
    useEffect(() => {
        if (!sesion.token || !activo?.id) return;
        let vivo = true;
        let consultando = false;
        let proximo = 0;
        let reintento = 5000;
        iniciarGPS(sesion.origen, activo).catch(fallo => { if (vivo) estadoGPS(fallo.message); });
        async function actualizar() {
            if (consultando || !vivo || Date.now() < proximo) return;
            consultando = true;
            try {
                if (Date.now() >= Date.parse(activo.captura_hasta)) await detenerGPS();
                await enviarGPS(sesion.origen, sesion.token, activo);
                const actual = (await enviar('recorridos/' + activo.id)).data;
                if (actual.estado !== 'en_curso') await detenerGPS();
                if (vivo) {
                    guardarActivo(actual.estado === 'en_curso' ? actual : null);
                    estadoGPS(actual.puntos.length ? 'Registrando ubicación cada 5 segundos…' : 'Buscando señal GPS…');
                }
                reintento = 5000; proximo = 0;
            } catch (fallo) {
                if (vivo) estadoGPS(fallo.message.includes('No se pudo conectar con el servidor') ? 'Sin conexión: ubicaciones por enviar' : fallo.message);
                if (fallo.status === 401 && vivo) {
                    await detenerGPS(); await sesion.guardar(null); guardarActivo(null); guardarPerfil(null); ponerCierre(null); ver('login');
                }
                proximo = Date.now() + reintento; reintento = Math.min(reintento * 2, 30000);
            } finally {
                consultando = false;
                if (vivo) {
                    ponerPendientes(await pendientesGPS(sesion.origen, activo));
                    ponerRechazadas(await rechazadasGPS(sesion.origen, activo));
                }
            }
        }
        actualizar();
        const envio = setInterval(actualizar, 5000);
        const reloj = setInterval(() => tic(Date.now()), 1000);
        const estado = AppState.addEventListener('change', valor => { if (valor === 'active') { proximo = 0; actualizar(); } });
        return () => { vivo = false; clearInterval(envio); clearInterval(reloj); estado.remove(); };
    }, [sesion.token, activo?.id]);

    function ir(destino) {
        abrirMenu(false); ponerError(''); ponerAviso(''); ver(destino);
        if (destino === 'hoy' || destino === 'lista') operar(() => cargar());
        if (destino === 'historial') operar(async () => guardarHistorial(await enviar('recorridos')));
    }
    async function ingresar() {
        const mensaje = validarAcceso(email, password);
        if (mensaje) throw new Error(mensaje);
        const { token } = await enviar('login', { email: email.trim(), password, dispositivo: 'RSU Conductor' });
        await cargar(token); ver('lista'); await sesion.guardar(token); escribirPassword('');
    }
    async function iniciar() {
        if (!(await Location.hasServicesEnabledAsync())) throw new Error('Active la ubicación (GPS) del teléfono…');
        if (!(await Location.requestForegroundPermissionsAsync()).granted) throw new Error('Debe permitir el acceso a la ubicación para iniciar el recorrido.');
        if (!(await Location.requestBackgroundPermissionsAsync()).granted) throw new Error('Debe permitir el acceso a la ubicación en segundo plano para iniciar el recorrido.');
        const ruta = (await enviar('programaciones/' + programacion.id + '/recorrido', {})).data;
        guardarActivo(ruta); ver('activo');
    }
    function confirmarInicio() {
        Alert.alert('¡Todo el personal ha asistido!', 'Puede iniciar el recorrido.', [
            { text: 'Cancelar', style: 'cancel' }, { text: 'Aceptar', onPress: () => operar(iniciar) },
        ]);
    }
    function confirmarCierre() {
        const texto = observacion.trim();
        if (!texto) { ponerError('La observación es obligatoria.'); return; }
        if (texto.length < 3) { ponerError('Escriba al menos 3 caracteres.'); return; }
        Alert.alert('¿Finalizar recorrido?', 'Se registrará la hora del servidor y se detendrá la adquisición GPS.', [
            { text: 'Cancelar', style: 'cancel' }, { text: 'Aceptar', onPress: () => operar(async () => {
                if (!perfil?.id || !activo) throw new Error('Recarga la asignación antes de solicitar el cierre.');
                ponerCierre(await guardarCierre(sesion.origen, sesion.token, perfil.id, activo, texto));
            }) },
        ]);
    }
    function salir() {
        operar(async () => {
            if (activo || await cierrePendiente(sesion.origen, sesion.token, perfil?.id)) throw new Error('Finalice el recorrido antes de cerrar sesión.');
            await enviar('logout', {}); await detenerGPS(); await sesion.guardar(null);
            guardarPerfil(null); guardarProgramaciones(null); guardarHistorial(null); guardarViaje(null); abrirMenu(false); ver('login');
        });
    }
    const volver = { servidor: 'login', hoy: 'lista', perfil: 'lista', pendiente: 'lista', activo: 'hoy', cerrar: 'activo', historial: 'lista', detalle: 'historial', mapa: 'detalle' }[vista];
    const ultima = activo?.puntos.at(-1);
    const recargar = ['lista', 'hoy'].includes(vista) ? () => operar(() => cargar()) : undefined;
    const segundos = activo ? Math.max(0, Math.floor((ahora - Date.parse(activo.inicio)) / 1000)) : 0;
    const reloj = [Math.floor(segundos / 3600), Math.floor(segundos / 60) % 60, segundos % 60].map(n => String(n).padStart(2, '0')).join(':');

    return <Pantalla titulo={titulos[vista]} subtitulo={vista === 'login' ? 'Aplicación para conductores' : undefined}
        volver={volver && (() => ir(volver))} menu={sesion.token && (() => abrirMenu(!menu))} recargar={recargar} ocupado={ocupado}>
        <Mensaje texto={error || sesion.errorSesion} error /><Mensaje texto={ocupado || !sesion.lista ? 'Cargando…' : aviso} />
        {cierre && <Mensaje texto={cierre.conflicto
            ? 'Cierre en conflicto. Guardado para revisión: ' + cierre.conflicto
            : 'Cierre guardado. Pendiente de confirmación del servidor.'} />}
        {menu && <View style={estilos.tarjeta}>
            {[['lista', 'Mis programaciones'], ['hoy', 'Programación de hoy'], ['historial', 'Historial'], ['perfil', 'Mi perfil']].map(([id, texto]) => <Boton key={id} texto={texto} secundario onPress={() => ir(id)} />)}
            <Boton texto="Cerrar sesión" onPress={salir} />
        </View>}
        {vista === 'login' && <View style={estilos.tarjeta}>
            <Campo etiqueta="Correo" value={email} onChangeText={escribirEmail} keyboardType="email-address" autoCapitalize="none" />
            <Campo etiqueta="Contraseña" value={password} onChangeText={escribirPassword} secureTextEntry={!visible} autoCapitalize="none" />
            <Boton texto={visible ? 'Ocultar contraseña' : 'Mostrar contraseña'} onPress={() => mostrar(!visible)} secundario />
            <Boton texto="Ingresar" disabled={ocupado || !sesion.lista} onPress={() => operar(ingresar)} />
            {laboratorio && <Boton texto="⚙ Configurar servidor" secundario onPress={() => { escribirOrigen(sesion.origen); ir('servidor'); }} />}
            {!!sesion.origen && <Mensaje texto={'Servidor: ' + sesion.origen} />}
        </View>}
        {vista === 'servidor' && <View style={estilos.tarjeta}>
            <Campo etiqueta="Dirección del servidor" value={origen} onChangeText={escribirOrigen} autoCapitalize="none" keyboardType="url" />
            <Boton texto="Probar y guardar" disabled={ocupado} onPress={() => operar(async () => {
                if (await existeCierre(sesion.origen)) throw new Error('Resuelve el cierre pendiente antes de cambiar de servidor.');
                await sesion.cambiarOrigen(origen); ir('login');
            })} />
        </View>}
        {vista === 'lista' && <>
            {perfil && <View style={estilos.tarjeta}><Text style={estilos.encabezado}>{perfil.nombre_completo}</Text><Mensaje texto={'DNI: ' + perfil.dni} />
                <Boton texto="Mi perfil" secundario onPress={() => ir('perfil')} /></View>}
            <Boton texto="Programación de hoy" secundario onPress={() => ir('hoy')} />
            {programaciones?.length === 0 && <Mensaje texto="No tiene programaciones pendientes." />}
            {programaciones?.map(item => <View style={estilos.tarjeta} key={item.id}>
                <Text style={estilos.estado}>{item.fecha === hoyLima() ? 'Hoy · ' : ''}{activo?.programacion_id === item.id || item.recorrido?.estado === 'en_curso' ? 'En ruta' : 'Programado'}</Text>
                <Resumen item={item} />
                <Boton texto="Ver programación" secundario disabled={ocupado} onPress={() => operar(async () => {
                    guardarProgramacion((await enviar('programaciones/' + item.id)).data); ver(item.fecha === hoyLima() ? 'hoy' : 'pendiente');
                })} />
            </View>)}
        </>}
        {['hoy', 'pendiente'].includes(vista) && (programacion ? <View style={estilos.tarjeta}>
            <Mensaje texto={vista === 'hoy' ? 'Hay programación para hoy' : 'Programación pendiente'} />
            <Resumen item={programacion} />
            <Text style={estilos.encabezado}>Estado de asistencia del personal</Text>
            {programacion.asistencia.map(item => <Text key={item.id} style={item.presente ? estilos.estado : estilos.error}>{item.nombre} · {item.presente ? 'Presente' : 'Ausente'}</Text>)}
            {!!programacion.motivo && <Text style={[estilos.texto, { color: '#92530a' }]}>{programacion.motivo}</Text>}
            {activo?.programacion_id === programacion.id ? <Boton texto="Ver recorrido en curso" onPress={() => ir('activo')} />
                : <Boton texto={programacion.recorrido?.fin ? 'Recorrido completado a las ' + horaCorta(programacion.recorrido.fin) : 'Iniciar recorrido'}
                    disabled={ocupado || !programacion.puede_iniciar || !!activo || !!cierre || vista === 'pendiente'} onPress={confirmarInicio} />}
        </View> : <Mensaje texto={ocupado ? '' : 'No hay programación para hoy'} />)}
        {vista === 'activo' && activo && <>
            <View style={estilos.tarjeta}><Text style={estilos.estado}>En ruta · {reloj}</Text><Resumen item={activo} /></View>
            <Mapa vertices={activo.zona_vertices} puntos={activo.puntos} />
            <Mensaje texto={cierre ? 'Captura detenida para el cierre.' : gps || 'Buscando señal GPS…'} />
            {pendientes > 0 && <Mensaje texto={pendientes + ' ubicaciones por enviar'} />}
            {rechazadas > 0 && <Mensaje texto={rechazadas + ' ubicaciones fuera de ventana guardadas para revisión.'} />}
            {ultima && <Mensaje texto={ultima.lat.toFixed(5) + ', ' + ultima.lng.toFixed(5)} />}
            <Boton texto="Finalizar recorrido" disabled={!!cierre} onPress={() => ir('cerrar')} />
        </>}
        {vista === 'cerrar' && activo && <View style={estilos.tarjeta}>
            <Resumen item={activo} /><Mensaje texto={'Inicio del recorrido: ' + hora(activo.inicio)} />
            <Campo etiqueta="Observación del recorrido" value={observacion} onChangeText={escribirObservacion} maxLength={500} multiline />
            <Mensaje texto={observacion.length + '/500'} />
            <Boton texto="Finalizar recorrido" disabled={ocupado || !!cierre} onPress={confirmarCierre} />
            <Boton texto="Cancelar" secundario disabled={ocupado} onPress={() => ir('activo')} />
        </View>}
        {vista === 'historial' && <>
            {historial?.data.length === 0 && <Mensaje texto="Todavía no tiene recorridos registrados." />}
            {historial?.data.map(r => <View style={estilos.tarjeta} key={r.id}>
                <Text style={estilos.estado}>{r.estado_texto}</Text><Resumen item={r} />
                <Mensaje texto={horaCorta(r.inicio) + (r.fin ? ' – ' + horaCorta(r.fin) : '')} />
                <Boton texto="Ver detalle" disabled={ocupado} onPress={() => operar(async () => { guardarViaje((await enviar('recorridos/' + r.id)).data); ver('detalle'); })} />
            </View>)}
            {historial && historial.pagina < historial.ultima_pagina && <Boton texto="Cargar más" disabled={ocupado} onPress={() => operar(async () => {
                const siguiente = await enviar('recorridos?page=' + (historial.pagina + 1)); guardarHistorial({ ...siguiente, data: [...historial.data, ...siguiente.data] });
            })} />}
        </>}
        {['detalle', 'mapa'].includes(vista) && viaje && <>
            <View style={estilos.tarjeta}><Text style={estilos.estado}>{viaje.estado_texto}</Text><Resumen item={viaje} />
                <Mensaje texto={viaje.fin ? 'Fin: ' + hora(viaje.fin) : ''} /><Mensaje texto={viaje.observacion && 'Observación: ' + viaje.observacion} /></View>
            <Mapa vertices={viaje.zona_vertices} puntos={viaje.puntos} finalizado={!!viaje.fin} altura={vista === 'mapa' ? 430 : 240} />
            {!viaje.puntos.length && <Mensaje texto="Este recorrido no tiene ubicaciones registradas." />}
            {vista === 'detalle' && <Boton texto="Ver recorrido en mapa" onPress={() => ir('mapa')} />}
        </>}
        {vista === 'perfil' && perfil && <View style={estilos.tarjeta}>
            <Text style={estilos.encabezado}>{perfil.nombre_completo}</Text>
            <Mensaje texto={'DNI: ' + perfil.dni + '\nLicencia: ' + (perfil.licencia || '—') + '\nCorreo: ' + perfil.correo + '\nTeléfono: ' + (perfil.telefono || '—')} />
            <Boton texto="Cerrar sesión" onPress={salir} />
        </View>}
    </Pantalla>;
}

function Resumen({ item }) {
    return <>
        <Text style={estilos.encabezado}>{item.zona?.nombre}</Text>
        <Mensaje texto={item.inicio && hora(item.inicio)} />
        <Mensaje texto={[item.vehiculo?.nombre, item.vehiculo?.placa].filter(Boolean).join(' · ')} />
        <Mensaje texto={item.turno?.nombre && item.turno.nombre + ' · ' + item.turno.hora_inicio + '–' + item.turno.hora_fin} />
        <Mensaje texto={item.ayudantes?.length ? 'Ayudantes: ' + item.ayudantes.map(p => p.nombre).join(', ') : ''} />
        <Mensaje texto={item.dias?.join(', ')} />
    </>;
}
