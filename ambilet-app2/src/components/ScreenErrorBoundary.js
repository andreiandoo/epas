import React from 'react';
import { View, Text, TouchableOpacity, StyleSheet, ScrollView } from 'react-native';
import { colors } from '../theme/colors';
import { captureError } from '../services/crashReporter';

// Per-screen error boundary. Isolates a single screen crash so the other
// tabs stay usable. Contrast with the root <ErrorBoundary> which is a
// last-resort catch — this one is scoped so the operator can navigate
// away from a broken screen instead of losing the whole app.
export default class ScreenErrorBoundary extends React.Component {
  constructor(props) {
    super(props);
    this.state = { hasError: false, error: null };
  }

  static getDerivedStateFromError(error) {
    return { hasError: true, error };
  }

  componentDidCatch(error, errorInfo) {
    captureError(error, {
      screen: this.props.screenName || 'unknown',
      componentStack: errorInfo?.componentStack?.substring(0, 2000),
    });
  }

  handleRetry = () => {
    this.setState({ hasError: false, error: null });
  };

  render() {
    if (!this.state.hasError) return this.props.children;
    return (
      <View style={styles.container}>
        <ScrollView contentContainerStyle={styles.scroll}>
          <View style={styles.iconWrap}>
            <Text style={styles.iconEmoji}>⚠️</Text>
          </View>
          <Text style={styles.title}>Ecran indisponibil</Text>
          <Text style={styles.subtitle}>
            Ecranul „{this.props.screenName || 'necunoscut'}" a întâmpinat o eroare.
            Poți încerca din nou sau treci temporar pe alt tab.
          </Text>
          {__DEV__ && this.state.error ? (
            <View style={styles.devBox}>
              <Text style={styles.devLabel}>Eroare (dev only):</Text>
              <Text style={styles.devText}>
                {this.state.error?.message || String(this.state.error)}
              </Text>
            </View>
          ) : null}
          <TouchableOpacity style={styles.retryBtn} onPress={this.handleRetry} activeOpacity={0.85}>
            <Text style={styles.retryBtnText}>Reîncearcă</Text>
          </TouchableOpacity>
        </ScrollView>
      </View>
    );
  }
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  scroll: {
    flexGrow: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 32,
    gap: 16,
  },
  iconWrap: {
    width: 72, height: 72, borderRadius: 36,
    backgroundColor: colors.amberBg,
    alignItems: 'center', justifyContent: 'center',
    marginBottom: 8,
  },
  iconEmoji: { fontSize: 32 },
  title: {
    fontSize: 22,
    fontWeight: '800',
    color: colors.textPrimary,
    textAlign: 'center',
  },
  subtitle: {
    fontSize: 14,
    color: colors.textSecondary,
    textAlign: 'center',
    lineHeight: 20,
    maxWidth: 320,
  },
  devBox: {
    marginTop: 8,
    padding: 12,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.surface,
    alignSelf: 'stretch',
  },
  devLabel: {
    fontSize: 11,
    fontWeight: '700',
    color: colors.red,
    textTransform: 'uppercase',
    letterSpacing: 0.4,
    marginBottom: 4,
  },
  devText: {
    fontSize: 12,
    color: colors.textPrimary,
    fontFamily: 'monospace',
  },
  retryBtn: {
    marginTop: 8,
    backgroundColor: colors.purple,
    paddingVertical: 14,
    paddingHorizontal: 32,
    borderRadius: 12,
    shadowColor: colors.purple,
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.28,
    shadowRadius: 10,
    elevation: 4,
  },
  retryBtnText: {
    color: colors.white,
    fontSize: 15,
    fontWeight: '700',
    letterSpacing: 0.2,
  },
});
