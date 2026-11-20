import React from 'react';
import { registerRootComponent } from 'expo';
import Constants from 'expo-constants';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import Ciudadania from './src/Ciudadania';
import Conductor from './src/Conductor';

function App() {
    return (
        <SafeAreaProvider>
            {Constants.expoConfig?.extra?.variante === 'conductor' ? <Conductor /> : <Ciudadania />}
        </SafeAreaProvider>
    );
}

registerRootComponent(App);
