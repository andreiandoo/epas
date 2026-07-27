import React, { useCallback, useEffect, useRef } from 'react';
import { TextInput, View, StyleSheet, Keyboard, Platform } from 'react-native';

// Invisible input that captures keystrokes from a Bluetooth barcode /
// QR scanner acting as a HID keyboard. Most physical scanners (Zebra,
// Symbol, Honeywell, generic 2D scanners) type the code as ultra-fast
// keystrokes ending in Enter (\n) — indistinguishable from a keyboard
// at the OS level, but the SPEED is what tells us apart.
//
// Heuristic: if consecutive keystrokes arrive within ~35ms AND the sequence
// terminates in Enter → treat as scan and fire `onScan(buffer)`. Human
// typing hits nowhere near that cadence, so manual input in visible
// TextInputs isn't hijacked.
//
// Placement: mount ONE instance high in the tree (per active screen that
// wants scanner input — CheckIn, Sales). Auto-refocuses every 500ms so a
// user tap that moves focus elsewhere doesn't kill BT scanner input for
// good. Suspends while the software keyboard is up (visible TextInput
// focused) so it doesn't fight for keystrokes.
//
// This is READ-ONLY — the input is off-screen, no caret, no size. It
// exists solely to receive key events.

const FAST_KEYSTROKE_MS = 35;

export default function HardwareScannerInput({ onScan, enabled = true }) {
  const inputRef = useRef(null);
  const bufferRef = useRef('');
  const lastKeyAtRef = useRef(0);
  const softKeyboardVisibleRef = useRef(false);

  const refocus = useCallback(() => {
    if (!enabled) return;
    if (softKeyboardVisibleRef.current) return;
    try { inputRef.current?.focus(); } catch {}
  }, [enabled]);

  // Track soft-keyboard visibility. When user is typing in a visible
  // TextInput we suspend so their keystrokes reach that field.
  useEffect(() => {
    const show = Keyboard.addListener(
      Platform.OS === 'ios' ? 'keyboardWillShow' : 'keyboardDidShow',
      () => { softKeyboardVisibleRef.current = true; try { inputRef.current?.blur(); } catch {} }
    );
    const hide = Keyboard.addListener(
      Platform.OS === 'ios' ? 'keyboardWillHide' : 'keyboardDidHide',
      () => { softKeyboardVisibleRef.current = false; refocus(); }
    );
    return () => { show.remove(); hide.remove(); };
  }, [refocus]);

  // Poll-refocus — TextInputs elsewhere can steal focus (form fields on
  // some modals). Re-grabbing focus every 500ms is cheap and keeps the
  // scanner alive without needing every screen to know about it.
  useEffect(() => {
    if (!enabled) return;
    refocus();
    const id = setInterval(refocus, 500);
    return () => clearInterval(id);
  }, [enabled, refocus]);

  const flushBuffer = useCallback(() => {
    const buf = bufferRef.current.trim();
    bufferRef.current = '';
    if (buf.length >= 4) {
      try { onScan?.(buf); } catch {}
    }
  }, [onScan]);

  // We can't hook individual keystrokes across RN abstractions easily —
  // but onChangeText fires per key. Cadence check via timestamps works
  // as long as onChangeText fires per keystroke (it does on Android +
  // iOS for hardware keyboard input).
  const handleChange = useCallback((next) => {
    const now = Date.now();
    const gap = now - lastKeyAtRef.current;
    lastKeyAtRef.current = now;

    // If a human is typing (slow), drop buffer + ignore.
    if (gap > FAST_KEYSTROKE_MS && bufferRef.current.length > 0) {
      bufferRef.current = '';
    }

    // Enter / newline (some scanners send \n, some \r\n) terminates.
    if (next.endsWith('\n') || next.endsWith('\r')) {
      bufferRef.current += next.replace(/[\r\n]/g, '');
      flushBuffer();
      // Reset the input to empty for next scan.
      try { inputRef.current?.clear?.(); } catch {}
      return;
    }

    bufferRef.current = next;
  }, [flushBuffer]);

  // Also handle onSubmitEditing (some scanners emit Enter as submit event
  // rather than character stream). Belt & suspenders.
  const handleSubmit = useCallback(() => {
    flushBuffer();
    try { inputRef.current?.clear?.(); } catch {}
  }, [flushBuffer]);

  if (!enabled) return null;

  return (
    <View style={styles.wrapper} pointerEvents="none">
      <TextInput
        ref={inputRef}
        style={styles.input}
        onChangeText={handleChange}
        onSubmitEditing={handleSubmit}
        autoFocus
        blurOnSubmit={false}
        caretHidden
        showSoftInputOnFocus={false} // suppress soft keyboard on Android
        contextMenuHidden
        keyboardType="default"
        autoCorrect={false}
        autoCapitalize="none"
        spellCheck={false}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  wrapper: {
    position: 'absolute',
    top: -1000,
    left: -1000,
    width: 1,
    height: 1,
    opacity: 0,
  },
  input: {
    width: 1,
    height: 1,
    color: 'transparent',
  },
});
