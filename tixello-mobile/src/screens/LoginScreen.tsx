import React, { useState } from 'react';
import {
  KeyboardAvoidingView,
  Platform,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useApp } from '@/store/AppContext';
import { PrimaryButton } from '@/components/ui';
import { palette, radius, accentFor } from '@/theme/colors';

export default function LoginScreen() {
  const { signIn } = useApp();
  const accent = accentFor('organizer');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const onSubmit = async () => {
    setError(null);
    setLoading(true);
    try {
      await signIn(email.trim().toLowerCase(), password);
    } catch (e) {
      setError(
        e instanceof Error ? e.message : 'Autentificare eșuată. Verifică datele.',
      );
    } finally {
      setLoading(false);
    }
  };

  return (
    <SafeAreaView style={styles.safe}>
      <KeyboardAvoidingView
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        style={styles.wrap}
      >
        <View style={styles.brandWrap}>
          <View style={[styles.logo, { backgroundColor: accent.base }]}>
            <Text style={styles.logoGlyph}>🎟</Text>
          </View>
          <Text style={styles.brand}>tixello</Text>
          <Text style={styles.tagline}>Aplicația pentru organizatori & staff</Text>
        </View>

        <View style={styles.form}>
          <TextInput
            style={styles.input}
            placeholder="email@organizator.ro"
            placeholderTextColor={palette.hint}
            autoCapitalize="none"
            keyboardType="email-address"
            value={email}
            onChangeText={setEmail}
          />
          <TextInput
            style={styles.input}
            placeholder="Parolă"
            placeholderTextColor={palette.hint}
            secureTextEntry
            value={password}
            onChangeText={setPassword}
          />
          {error && <Text style={styles.error}>{error}</Text>}
          <PrimaryButton
            label="Autentificare"
            onPress={onSubmit}
            accent={accent}
            loading={loading}
            disabled={!email || !password}
          />
          <Text style={styles.forgot}>Ai uitat parola?</Text>
        </View>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: palette.bg },
  wrap: { flex: 1, justifyContent: 'center', paddingHorizontal: 24, gap: 28 },
  brandWrap: { alignItems: 'center', gap: 8 },
  logo: {
    width: 58,
    height: 58,
    borderRadius: 16,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 6,
  },
  logoGlyph: { fontSize: 26 },
  brand: { color: palette.text, fontSize: 28, fontWeight: '900', letterSpacing: -0.5 },
  tagline: { color: palette.muted, fontSize: 12 },
  form: { gap: 12 },
  input: {
    backgroundColor: palette.surface2,
    borderWidth: 1.5,
    borderColor: palette.border2,
    borderRadius: radius.md,
    paddingHorizontal: 14,
    paddingVertical: 14,
    color: palette.text,
    fontSize: 15,
    fontWeight: '600',
  },
  error: { color: palette.danger, fontSize: 12.5, fontWeight: '600' },
  forgot: { color: palette.hint, fontSize: 12, textAlign: 'center', marginTop: 4 },
});
