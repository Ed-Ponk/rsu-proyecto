import React, { useEffect, useRef, useState } from 'react';
import { Image, Text, View } from 'react-native';
import * as Location from 'expo-location';
import { api, hora, laboratorio } from './api';
import { useSesion, validarAcceso } from './sesion';
import { Boton, Campo, Mensaje, Pantalla, Pestañas, estilos } from './ui';
import Mapa from './Mapa';
import { borrarConsultas, guardarConsulta, leerConsulta } from './cacheCiudadana';

const estados = { programado: 'Programado', reprogramado: 'Reprogramado', en_curso: 'En curso', completado: 'Finalizado', sin_confirmacion: 'Sin confirmación' };
const titulos = { login: 'RSU Ciudadano', registro: 'Registro de Ciudadano', zonas: 'Selecciona Zona', inicio: 'Mis Programaciones', detalle: 'Detalle de Programación', seguimiento: 'Seguimiento del Recorrido', calificar: 'Calificar Servicio', historial: 'Historial de Recorridos', historico: 'Detalle del Recorrido', perfil: 'Mi perfil', servidor: 'Configurar servidor' };
const campos = [['dni', 'DNI'], ['name', 'Nombres'], ['last_name', 'Apellidos'], ['email', 'Correo electrónico'], ['password', 'Contraseña']];
const obtenerMes = (fecha = new Date()) => new Intl.DateTimeFormat('sv-SE', { timeZone: 'America/Lima', year: 'numeric', month: '2-digit' }).format(fecha);

export default function Ciudadania() {
    const sesion = useSesion('ciudadano');
    const [pantalla, ver] = useState('login');
    const [formulario, escribir] = useState({});
    const [mostrarClave, mostrar] = useState(false);
    const [usuario, guardarUsuario] = useState(null);
    const [zonas, guardarZonas] = useState([]);
    const [seleccion, seleccionar] = useState(null);
    const [ubicacion, guardarUbicacion] = useState(null);
    const [mes, cambiarMes] = useState(obtenerMes);
    const [lista, guardarLista] = useState(null);
    const [historial, guardarHistorial] = useState(null);
    const [detalle, guardarDetalle] = useState(null);
    const [estrellas, ponerEstrellas] = useState(0);
    const [comentario, ponerComentario] = useState('');
    const [error, ponerError] = useState('');
    const [aviso, ponerAviso] = useState('');
    const [ocupado, marcar] = useState(false);
    const [origen, escribirOrigen] = useState('');
    const [horaActual, ponerHora] = useState(Date.now());
    const [consultaGuardada, guardarFechaConsulta] = useState(null);
    const versionConsulta = useRef(0);
    const enviar = (ruta, datos) => api('v1/ciudadano/' + ruta, { token: sesion.token, origen: sesion.origen, datos });
    const escribirCampo = (campo, valor) => escribir(anterior => ({ ...anterior, [campo]: valor }));
    const limpiar = () => { guardarUsuario(null); guardarDetalle(null); guardarLista(null); guardarHistorial(null); guardarUbicacion(null); guardarFechaConsulta(null); escribir({}); };

    async function invalidarConsultas() {
        versionConsulta.current++;
        if (sesion.token) await borrarConsultas(sesion).catch(() => {});
    }

    async function consultar(ruta) {
        const version = versionConsulta.current;
        const vigente = () => versionConsulta.current === version;
        const alcance = ruta === 'perfil' ? 'perfil' : usuario?.zone_id;
        try {
            const datos = await enviar(ruta);
            if (!vigente()) throw new Error('La sesión o la zona cambió.');
            await guardarConsulta(sesion, alcance, ruta, datos, vigente).catch(() => {});
            if (!vigente()) throw new Error('La sesión o la zona cambió.');
            if (ruta !== 'perfil' || pantalla === 'perfil') guardarFechaConsulta(null);
            return datos;
        } catch (fallo) {
            if (!vigente() || (fallo.status >= 400 && fallo.status < 500)) throw fallo;
            const copia = await leerConsulta(sesion, alcance, ruta).catch(() => null);
            if (!copia || !vigente()) throw fallo;
            guardarFechaConsulta(copia.fecha);
            return copia.datos;
        }
    }

    async function operar(accion) {
        ponerError(''); ponerAviso(''); marcar(true);
        try { await accion(); }
        catch (fallo) {
            if (fallo.status === 401 && sesion.token) { await invalidarConsultas(); await sesion.guardar(null); limpiar(); ver('login'); }
            ponerError(fallo.message);
        } finally { marcar(false); }
    }
    function ir(destino) { ponerError(''); ponerAviso(''); ver(destino); }
    useEffect(() => {
        if (sesion.token && !usuario) operar(async () => { guardarUsuario((await consultar('perfil')).ciudadano); ver('inicio'); });
    }, [sesion.token]);
    useEffect(() => {
        if (sesion.token && pantalla === 'inicio') operar(async () => guardarLista(await consultar('programaciones?mes=' + mes)));
        if (sesion.token && pantalla === 'historial') operar(async () => guardarHistorial(await consultar('historial')));
        if (sesion.token && pantalla === 'perfil') operar(async () => guardarUsuario((await consultar('perfil')).ciudadano));
    }, [pantalla, mes, usuario?.zone_id]);
    useEffect(() => {
        if (!consultaGuardada || ocupado || !sesion.token || !['inicio', 'historial', 'perfil', 'detalle', 'historico'].includes(pantalla)) return;
        const volverAConsultar = setTimeout(() => {
            if (pantalla === 'inicio') operar(async () => guardarLista(await consultar('programaciones?mes=' + mes)));
            if (pantalla === 'historial') operar(async () => guardarHistorial(await consultar('historial')));
            if (pantalla === 'perfil') operar(async () => guardarUsuario((await consultar('perfil')).ciudadano));
            if (detalle?.id && ['detalle', 'historico'].includes(pantalla)) abrir(detalle.id, pantalla);
        }, 5000);
        return () => clearTimeout(volverAConsultar);
    }, [consultaGuardada, pantalla, ocupado, mes, detalle?.id, sesion.token]);
    useEffect(() => {
        if (pantalla !== 'seguimiento' || !detalle?.id) return;
        let activo = true;
        let consultando = false;
        const cada = setInterval(async () => {
            if (consultando) return;
            consultando = true;
            try {
                const dato = (await consultar('programaciones/' + detalle.id)).data;
                if (activo) { guardarDetalle(dato); ponerError(''); }
            } catch (fallo) {
                if (activo && fallo.status === 401) { await invalidarConsultas(); await sesion.guardar(null); limpiar(); ver('login'); }
                if (activo && [403, 404].includes(fallo.status)) { guardarDetalle(null); guardarFechaConsulta(null); ver('inicio'); }
                if (activo) ponerError(fallo.message);
            }
            finally { consultando = false; }
        }, 5000);
        const reloj = setInterval(() => ponerHora(Date.now()), 1000);
        return () => { activo = false; clearInterval(cada); clearInterval(reloj); };
    }, [pantalla, detalle?.id, sesion.token]);

    function acceso(registro) {
        operar(async () => {
            if (!registro) {
                const mensaje = validarAcceso(formulario.email ?? '', formulario.password);
                if (mensaje) throw new Error(mensaje);
            }
            const datos = await enviar(registro ? 'registro' : 'login', registro ? { ...formulario, zone_id: seleccion?.id } : { email: formulario.email.trim(), password: formulario.password });
            await guardarConsulta({ origen: sesion.origen, token: datos.token }, 'perfil', 'perfil', datos).catch(() => {});
            await sesion.guardar(datos.token);
            guardarUsuario(datos.ciudadano); escribir({}); ver('inicio');
        });
    }
    function abrirZonas() {
        operar(async () => { guardarZonas((await enviar('zonas')).data); seleccionar(null); ver('zonas'); });
    }
    async function localizar(detectar) {
        if (!(await Location.hasServicesEnabledAsync())) throw new Error('Activa el GPS o selecciona la zona manualmente.');
        if (!(await Location.requestForegroundPermissionsAsync()).granted) throw new Error('Permiso de ubicación denegado. Puedes seleccionar la zona manualmente.');
        let temporizador;
        const pos = await Promise.race([
            Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.High, mayShowUserSettingsDialog: false }),
            new Promise((_, reject) => { temporizador = setTimeout(() => reject(new Error('Sin señal GPS. Puedes seleccionar la zona manualmente.')), 12000); }),
        ]).finally(() => clearTimeout(temporizador));
        const punto = { lat: pos.coords.latitude, lng: pos.coords.longitude };
        guardarUbicacion(punto);
        if (detectar) {
            const opciones = (await enviar('zonas/ubicar', punto)).data;
            seleccionar(opciones.length === 1 ? opciones[0] : null);
            ponerAviso(opciones.length === 0 ? 'No se encontró una zona. Selecciona una manualmente.' : opciones.length > 1 ? 'Varias zonas coinciden: ' + opciones.map(z => z.name).join(', ') : 'Confirma tu zona.');
        }
    }
    function abrir(id, destino) {
        operar(async () => { guardarDetalle((await consultar('programaciones/' + id)).data); ver(destino); });
    }
    const volver = { registro: 'login', zonas: usuario ? 'perfil' : 'registro', servidor: 'login', detalle: 'inicio', seguimiento: 'detalle', calificar: 'seguimiento', historico: 'historial' }[pantalla];
    const tabs = [['inicio', 'Inicio'], ['historial', 'Historial'], ['perfil', 'Perfil']];
    const viaje = detalle?.recorrido;
    const recarga = pantalla === 'inicio' ? () => operar(async () => guardarLista(await consultar('programaciones?mes=' + mes))) : undefined;

    return <Pantalla titulo={titulos[pantalla]} subtitulo={pantalla === 'login' ? 'Servicio municipal de recolección' : undefined}
        volver={volver && (() => ir(volver))} recargar={recarga} ocupado={ocupado}
        pie={usuario && tabs.some(([id]) => id === pantalla) ? <Pestañas opciones={tabs} actual={pantalla} elegir={ir} /> : null}>
        <Mensaje texto={error || sesion.errorSesion} error />
        <Mensaje texto={ocupado || !sesion.lista ? 'Cargando…' : aviso} />
        {consultaGuardada && <Mensaje texto={'Sin conexión. Última consulta confirmada: ' + hora(consultaGuardada)} />}
        {(pantalla === 'login' || pantalla === 'registro') && <View style={estilos.tarjeta}>
            {(pantalla === 'registro' ? campos : campos.slice(3)).map(([clave, etiqueta]) => <Campo key={clave} etiqueta={etiqueta}
                value={formulario[clave] ?? ''} onChangeText={v => escribirCampo(clave, v)} maxLength={clave === 'dni' ? 9 : 150}
                keyboardType={clave === 'dni' ? 'number-pad' : clave === 'email' ? 'email-address' : 'default'}
                autoCapitalize={['email', 'password'].includes(clave) ? 'none' : 'words'} secureTextEntry={clave === 'password' && !mostrarClave} />)}
            <Boton texto={mostrarClave ? 'Ocultar contraseña' : 'Mostrar contraseña'} onPress={() => mostrar(!mostrarClave)} secundario />
            {pantalla === 'registro' && <Boton texto={seleccion?.name ?? 'Seleccionar zona'} onPress={abrirZonas} secundario />}
            <Boton texto={pantalla === 'login' ? 'Ingresar' : 'Registrarse'} disabled={ocupado || !sesion.lista} onPress={() => acceso(pantalla === 'registro')} />
            {pantalla === 'login' && <Boton texto="Crear cuenta" onPress={() => ir('registro')} secundario />}
            {pantalla === 'login' && laboratorio && <Boton texto="⚙ Configurar servidor" secundario onPress={() => { escribirOrigen(sesion.origen); ir('servidor'); }} />}
            {!!sesion.origen && <Mensaje texto={'Servidor: ' + sesion.origen} />}
        </View>}
        {pantalla === 'servidor' && <View style={estilos.tarjeta}>
            <Campo etiqueta="Dirección del servidor" value={origen} onChangeText={escribirOrigen} autoCapitalize="none" keyboardType="url" />
            <Boton texto="Probar y guardar" disabled={ocupado} onPress={() => operar(async () => { await sesion.cambiarOrigen(origen); limpiar(); ver('login'); })} />
        </View>}
        {pantalla === 'zonas' && <>
            <Boton texto="Encontrar mi ubicación" disabled={ocupado} onPress={() => operar(() => localizar(true))} />
            {(seleccion || ubicacion) && <Mapa vertices={seleccion?.vertices} ubicacion={ubicacion} />}
            <Mensaje texto={seleccion?.description} />
            {seleccion && <DatosZona zona={seleccion} origen={sesion.origen} />}
            {zonas.map(z => <Boton key={z.id} texto={z.name + ' · ' + z.district} secundario onPress={() => seleccionar(z)} />)}
            {seleccion && <Boton texto="Seleccionar esta zona" disabled={ocupado} onPress={() => operar(async () => {
                if (usuario) {
                    const nuevo = await enviar('zona', { zone_id: seleccion.id });
                    await invalidarConsultas();
                    await guardarConsulta(sesion, 'perfil', 'perfil', nuevo).catch(() => {});
                    guardarUsuario(nuevo.ciudadano); guardarLista(null); guardarHistorial(null); guardarDetalle(null); guardarFechaConsulta(null); ver('inicio');
                }
                else ver('registro');
            })} />}
        </>}
        {pantalla === 'inicio' && <>
            <View style={estilos.tarjeta}>
                <Campo etiqueta="Mes de programación (AAAA-MM)" defaultValue={mes} onEndEditing={evento => cambiarMes(evento.nativeEvent.text)} />
                <Boton texto="Actualizar programaciones" secundario disabled={ocupado} onPress={recarga} />
            </View>
            {lista?.data.length === 0 && <Mensaje texto="No hay programaciones para este mes en tu zona." />}
            {lista?.data.map(item => <Fila key={item.id} item={item} abrir={() => abrir(item.id, 'detalle')} />)}
            {lista && lista.current_page < lista.last_page && <Boton texto="Cargar más" disabled={ocupado} onPress={() => operar(async () => {
                const siguiente = await consultar('programaciones?mes=' + mes + '&page=' + (lista.current_page + 1)); guardarLista({ ...siguiente, data: [...lista.data, ...siguiente.data] });
            })} />}
        </>}
        {pantalla === 'historial' && <>
            {historial?.data.length === 0 && <Mensaje texto="Todavía no hay recorridos finalizados en tus zonas autorizadas." />}
            {historial?.data.map(item => <Fila key={item.id} item={item} abrir={() => abrir(item.id, 'historico')} />)}
            {historial && historial.current_page < historial.last_page && <Boton texto="Cargar más" disabled={ocupado} onPress={() => operar(async () => {
                const siguiente = await consultar('historial?page=' + (historial.current_page + 1)); guardarHistorial({ ...siguiente, data: [...historial.data, ...siguiente.data] });
            })} />}
        </>}
        {['detalle', 'seguimiento', 'historico', 'calificar'].includes(pantalla) && detalle && <>
            <View style={estilos.tarjeta}>
                <Text style={estilos.encabezado}>{detalle.zona}</Text>
                <DatosZona zona={detalle} origen={sesion.origen} />
                <Text style={estilos.estado}>{estados[detalle.estado]}</Text>
                <Mensaje texto={detalle.turno + ' · ' + hora(detalle.inicio) + ' a ' + hora(detalle.fin)} />
                <Mensaje texto={[detalle.vehiculo, detalle.placa].filter(Boolean).join(' · ')} />
                <Mensaje texto={detalle.conductor && 'Conductor: ' + detalle.conductor} />
                <Mensaje texto={detalle.ayudantes.length ? 'Ayudantes: ' + detalle.ayudantes.join(', ') : ''} />
            </View>
            {pantalla !== 'calificar' && <Mapa vertices={detalle.vertices} puntos={pantalla === 'detalle' ? [] : viaje?.puntos}
                ubicacion={pantalla === 'seguimiento' ? ubicacion : undefined} finalizado={detalle.estado === 'completado'} />}
            {pantalla === 'detalle' && <Boton texto="Ver recorrido en mapa" onPress={() => ir('seguimiento')} />}
            {pantalla === 'seguimiento' && <>
                <Mensaje texto={viaje?.ultima_ubicacion ? 'Última ubicación: hace ' + Math.max(0, Math.floor((horaActual - Date.parse(viaje.ultima_ubicacion)) / 1000)) + ' s' : 'Aún no hay ubicaciones del recorrido.'} />
                <Boton texto="Mostrar mi ubicación" secundario disabled={ocupado} onPress={() => operar(() => localizar(false))} />
            </>}
            {pantalla === 'historico' && <Mensaje texto={!viaje?.puntos.length ? 'Este recorrido no tiene ubicaciones registradas.' : 'Inicio ' + hora(viaje.inicio) + ' · Fin ' + hora(viaje.fin)} />}
            {['historico', 'seguimiento'].includes(pantalla) && viaje?.estado === 'completado' && <View style={estilos.tarjeta}>
                <Mensaje texto={viaje.votos ? 'Calificación: ' + viaje.promedio + '/5 (' + viaje.votos + ')' : 'Sin valoraciones'} />
                {viaje.valoracion_propia ? <Mensaje texto={'Tu valoración: ' + viaje.valoracion_propia.stars + '/5 ' + (viaje.valoracion_propia.comment || '')} />
                    : <Boton texto="Calificar servicio" disabled={!!consultaGuardada} onPress={() => { ponerEstrellas(0); ponerComentario(''); ir('calificar'); }} />}
            </View>}
            {pantalla === 'calificar' && <View style={estilos.tarjeta}>
                <Text style={estilos.encabezado}>¿Cómo calificas el servicio?</Text>
                <Pestañas actual={estrellas} elegir={ponerEstrellas} opciones={[1, 2, 3, 4, 5].map(n => [n, (n <= estrellas ? '★ ' : '☆ ') + n])} />
                <Campo etiqueta="Comentario opcional" value={comentario} onChangeText={ponerComentario} maxLength={500} multiline />
                <Boton texto="Enviar valoración" disabled={ocupado} onPress={() => operar(async () => {
                    if (!estrellas) throw new Error('Selecciona una calificación de 1 a 5 estrellas.');
                    await enviar('programaciones/' + detalle.id + '/valoracion', { stars: estrellas, comment: comentario.trim() || null });
                    guardarDetalle((await enviar('programaciones/' + detalle.id)).data); ver('historico'); ponerAviso('Valoración registrada.');
                })} />
            </View>}
        </>}
        {pantalla === 'perfil' && usuario && <View style={estilos.tarjeta}>
            <Text style={estilos.encabezado}>{usuario.name ? usuario.name + ' ' + usuario.last_name : 'Perfil sin conexión'}</Text>
            <Mensaje texto={usuario.dni && 'DNI: ' + usuario.dni} /><Mensaje texto={usuario.email} />
            <Boton texto="Cambiar zona" secundario onPress={abrirZonas} />
            <Boton texto="Cerrar sesión" disabled={ocupado} onPress={() => operar(async () => {
                try { await enviar('logout', {}); } finally { await invalidarConsultas(); await sesion.guardar(null); limpiar(); ver('login'); }
            })} />
        </View>}
    </Pantalla>;
}

function DatosZona({ zona, origen }) {
    const foto = zona.foto?.startsWith(origen + '/uploads/zone_photos/') ? zona.foto : null;
    return <>
        {foto && <Image source={{ uri: foto }} accessibilityLabel="Fotografía de la zona"
            style={{ width: '100%', height: 160, borderRadius: 8 }} />}
        {!!zona.sectores?.length && <Mensaje texto={zona.sectores.join(' · ')} />}
    </>;
}

function Fila({ item, abrir }) {
    return <View style={estilos.tarjeta}>
        <Text style={estilos.estado}>{estados[item.estado]}</Text>
        <Text style={estilos.encabezado}>{hora(item.inicio)}</Text>
        <Mensaje texto={item.zona + ' · ' + item.turno} />
        {item.votos !== undefined && <Mensaje texto={item.votos ? '★ ' + item.promedio + ' (' + item.votos + ')' : 'Sin valoraciones'} />}
        <Boton texto="Ver detalle" onPress={abrir} secundario />
    </View>;
}
