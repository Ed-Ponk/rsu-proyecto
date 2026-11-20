import React from 'react';
import { Pressable, RefreshControl, ScrollView, StatusBar, StyleSheet, Text, TextInput, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

export function Pantalla({ titulo, subtitulo, volver, menu, recargar, ocupado, pie, children }) {
    return (
        <SafeAreaView style={estilos.pantalla}>
            <StatusBar barStyle="dark-content" />
            <View style={estilos.cabecera}>
                {(volver || menu) && <Pressable accessibilityRole="button" accessibilityLabel={volver ? 'Volver' : 'Abrir menú'}
                    style={estilos.navegar} onPress={volver || menu}><Text style={estilos.icono}>{volver ? '‹' : '☰'}</Text></Pressable>}
                <View style={{ flex: 1 }}>
                    <Text accessibilityRole="header" style={estilos.titulo}>{titulo}</Text>
                    {!!subtitulo && <Text style={estilos.subtitulo}>{subtitulo}</Text>}
                </View>
            </View>
            <ScrollView keyboardShouldPersistTaps="handled" contentContainerStyle={estilos.contenido}
                refreshControl={recargar ? <RefreshControl refreshing={!!ocupado} onRefresh={recargar} /> : undefined}>
                {children}
            </ScrollView>
            {pie}
        </SafeAreaView>
    );
}

export function Campo({ etiqueta, ...props }) {
    return <View style={{ gap: 5 }}>
        <Text style={estilos.texto}>{etiqueta}</Text>
        <TextInput accessibilityLabel={etiqueta} placeholderTextColor="#53667f" style={estilos.campo} {...props} />
    </View>;
}

export function Mensaje({ texto, error = false }) {
    return texto ? <Text accessibilityLiveRegion="polite" accessibilityRole={error ? 'alert' : undefined}
        style={error ? estilos.error : estilos.texto}>{texto}</Text> : null;
}

export function Pestañas({ opciones, actual, elegir }) {
    return <View style={estilos.pestanas}>{opciones.map(([id, etiqueta]) =>
        <Pressable key={id} accessibilityRole="tab" accessibilityState={{ selected: actual === id }} style={estilos.pestana} onPress={() => elegir(id)}>
            <Text style={[estilos.texto, actual === id && { color: '#0056a4', fontWeight: '700' }]}>{etiqueta}</Text>
        </Pressable>)}</View>;
}

export function Boton({ texto, onPress, disabled = false, secundario = false }) {
    return (
        <Pressable accessibilityRole="button" accessibilityState={{ disabled }} disabled={disabled} onPress={onPress}
            style={({ pressed }) => [estilos.boton, secundario && estilos.secundario, (pressed || disabled) && estilos.atenuado]}>
            <Text style={secundario ? estilos.texto : estilos.textoBoton}>{texto}</Text>
        </Pressable>
    );
}

export const estilos = StyleSheet.create({
    pantalla: { flex: 1, backgroundColor: '#f1f5f9' },
    contenido: { width: '100%', maxWidth: 680, alignSelf: 'center', padding: 20, gap: 16 },
    cabecera: { paddingHorizontal: 16, paddingVertical: 12, backgroundColor: '#07549a', flexDirection: 'row', alignItems: 'center', gap: 10 },
    navegar: { minWidth: 44, minHeight: 44, justifyContent: 'center', alignItems: 'center' },
    icono: { color: '#fff', fontSize: 25 },
    pestanas: { flexDirection: 'row', backgroundColor: '#fff', borderTopWidth: 1, borderColor: '#cbd5e1' },
    pestana: { flex: 1, alignItems: 'center', justifyContent: 'center', minHeight: 52, padding: 6 },
    titulo: { color: '#fff', fontSize: 21, fontWeight: '800' },
    subtitulo: { color: '#e2e8f0', fontSize: 16, lineHeight: 23 },
    tarjeta: { backgroundColor: '#fff', borderRadius: 14, padding: 16, gap: 10, borderWidth: 1, borderColor: '#e2e8f0' },
    encabezado: { color: '#0f172a', fontSize: 20, fontWeight: '700' },
    texto: { color: '#334155', fontSize: 16, lineHeight: 23 },
    estado: { color: '#166534', fontSize: 16, fontWeight: '700' },
    error: { color: '#991b1b', fontSize: 16, padding: 12, backgroundColor: '#fef2f2', borderRadius: 10 },
    boton: { minHeight: 48, justifyContent: 'center', alignItems: 'center', borderRadius: 8, backgroundColor: '#0759ae', padding: 12 },
    secundario: { backgroundColor: '#e2e8f0' },
    textoBoton: { color: '#fff', fontSize: 16, fontWeight: '700' },
    atenuado: { opacity: 0.65 },
    campo: { minHeight: 48, borderWidth: 1, borderColor: '#64748b', color: '#0f172a', padding: 12, borderRadius: 10, fontSize: 16 },
});
