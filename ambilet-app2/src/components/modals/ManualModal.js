// In-app manual viewer — renders the docs/manual/*.md chapters with
// react-native-markdown-display and handles two link types:
//
//   1. `app://navigate/<RouteName>` — closes the manual and navigates
//      to that tab (Dashboard / CheckIn / Sales / Reports / Settings).
//      Powers the "Testează pe viu" hands-on-learning flow.
//
//   2. `./NN_name.md` — jumps to another chapter inside the manual
//      without leaving. Powers the "Următorul capitol →" links.
//
// Chapter content is loaded lazily: the bundled .md asset is downloaded
// (locally cached by expo-asset) and read via expo-file-system. Metro
// is configured (metro.config.js) to accept .md in assetExts so the
// `require()` calls below resolve to real bundled resources.
import React, { useEffect, useMemo, useState } from 'react';
import {
  View, Text, ScrollView, TouchableOpacity, TextInput,
  StyleSheet, ActivityIndicator, Modal, Linking, Alert, Image,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import Svg, { Path } from 'react-native-svg';
import { colors } from '../../theme/colors';

// Optional deps — silent no-op if not linked (dev without prebuild).
let Markdown = null;
try { Markdown = require('react-native-markdown-display').default; } catch {}
let Asset = null;
try { Asset = require('expo-asset').Asset; } catch {}
let FileSystem = null;
try { FileSystem = require('expo-file-system'); } catch {}

// Chapter registry — order matches the file numbering. Grouped by
// section for the index view. `file` is the bundled asset (loaded by
// metro via require).
const CHAPTERS = [
  { id: '00', file: require('../../../docs/manual/00_cuprins.md'), title: 'Cuprins', section: 'Meta' },
  { id: '01', file: require('../../../docs/manual/01_bine_ai_venit.md'), title: 'Bine ai venit — primii pași', section: '🚀 Începutul' },
  { id: '02', file: require('../../../docs/manual/02_tur_rapid.md'), title: 'Turul rapid al aplicației', section: '🚀 Începutul' },
  { id: '03', file: require('../../../docs/manual/03_selectie_eveniment.md'), title: 'Selectarea evenimentului', section: '🚀 Începutul' },
  { id: '04', file: require('../../../docs/manual/04_vanzare_bilete.md'), title: 'Vânzarea normală — cash, card, tap', section: '💳 Vânzarea de bilete' },
  { id: '05', file: require('../../../docs/manual/05_vanzare_locuri.md'), title: 'Vânzarea cu locuri (seating)', section: '💳 Vânzarea de bilete' },
  { id: '06', file: require('../../../docs/manual/06_vanzare_offline.md'), title: 'Vânzarea fără internet (offline)', section: '💳 Vânzarea de bilete' },
  { id: '07', file: require('../../../docs/manual/07_bilete_eveniment.md'), title: 'Bilete Eveniment — istoricul vânzărilor', section: '💳 Vânzarea de bilete' },
  { id: '08', file: require('../../../docs/manual/08_bilete_test.md'), title: 'Bilete de test (Test POS)', section: '💳 Vânzarea de bilete' },
  { id: '09', file: require('../../../docs/manual/09_scanare_camera.md'), title: 'Scanarea cu camera', section: '🎫 Check-in la intrare' },
  { id: '10', file: require('../../../docs/manual/10_scanare_manuala.md'), title: 'Scanarea manuală — cod sau nume', section: '🎫 Check-in la intrare' },
  { id: '11', file: require('../../../docs/manual/11_scanere_bluetooth.md'), title: 'Scanere Bluetooth externe', section: '🎫 Check-in la intrare' },
  { id: '12', file: require('../../../docs/manual/12_probleme_scanare.md'), title: 'Ce faci cu biletele problematice', section: '🎫 Check-in la intrare' },
  { id: '13', file: require('../../../docs/manual/13_panou_control.md'), title: 'Panoul de control', section: '📊 Panoul' },
  { id: '14', file: require('../../../docs/manual/14_ritm_vanzare.md'), title: 'Ritmul vânzărilor în timp real', section: '📊 Panoul' },
  { id: '15', file: require('../../../docs/manual/15_rapoarte.md'), title: 'Rapoarte — cifre, grafice, export CSV', section: '📈 Rapoarte' },
  { id: '16', file: require('../../../docs/manual/16_personal.md'), title: 'Personalul — adăugare, roluri, parole', section: '👥 Echipa & porțile' },
  { id: '17', file: require('../../../docs/manual/17_porti.md'), title: 'Porțile de acces', section: '👥 Echipa & porțile' },
  { id: '18', file: require('../../../docs/manual/18_raportare_urgente.md'), title: 'Raportarea unei urgențe', section: '🚨 Urgențe & comunicare' },
  { id: '19', file: require('../../../docs/manual/19_contact_urgente.md'), title: 'Contact urgențe — numere de telefon', section: '🚨 Urgențe & comunicare' },
  { id: '20', file: require('../../../docs/manual/20_notificari.md'), title: 'Panoul de notificări', section: '🚨 Urgențe & comunicare' },
  { id: '21', file: require('../../../docs/manual/21_tura.md'), title: 'Tura de lucru', section: '⏱️ Tura' },
  { id: '22', file: require('../../../docs/manual/22_aspect.md'), title: 'Aspect — Standard / Contrast / Noapte', section: '⚙️ Setări' },
  { id: '23', file: require('../../../docs/manual/23_securitate.md'), title: 'Securitate — auto-logout', section: '⚙️ Setări' },
  { id: '24', file: require('../../../docs/manual/24_setari_scanner.md'), title: 'Setări scanner — sunet, vibrație, auto-confirm', section: '⚙️ Setări' },
  { id: '25', file: require('../../../docs/manual/25_mod_offline.md'), title: 'Modul offline manual', section: '⚙️ Setări' },
  { id: '26', file: require('../../../docs/manual/26_widget_android.md'), title: 'Widget pe ecranul principal Android', section: '🎁 Extras' },
  { id: '27', file: require('../../../docs/manual/27_landscape_tablete.md'), title: 'Tabletă — landscape mode', section: '🎁 Extras' },
  { id: '28', file: require('../../../docs/manual/28_comutare_organizatori.md'), title: 'Comutarea între organizatori', section: '🎁 Extras' },
];

// Screenshot registry — Metro nu poate rezolva dinamic path-uri relative
// din markdown-uri bundled, asa ca fiecare imagine referita din .md trebuie
// mentionata explicit aici (require rezolvat la bundle time). Chei = filename
// din markdown (ex. src='./screenshots/01-splash.png' -> key '01-splash.png').
//
// Adaugare screenshot nou: pune fisierul in docs/manual/screenshots/ +
// adauga un rand aici. Screenshot-uri lipsa afiseaza silent nimic (nu
// crash), astfel incat placeholder-ele `![...](./screenshots/XX.png)` din
// capitolele in lucru nu strica capitolul.
const SCREENSHOTS = {
  '01-splash.png':          require('../../../docs/manual/screenshots/01-splash.png'),
  '01-login.png':           require('../../../docs/manual/screenshots/01-login.png'),
  '01-dashboard-tour.png':  require('../../../docs/manual/screenshots/01-dashboard-tour.png'),
};

// Custom markdown rules — override the default image renderer so relative
// ./screenshots/*.png paths resolve to bundled require() sources instead
// of being treated as remote URIs (which fail with no error, invisible).
const markdownRules = {
  image: (node, children, parent, styles) => {
    const src = node?.attributes?.src || '';
    const alt = node?.attributes?.alt || '';
    const filename = src.split('/').pop();
    const source = SCREENSHOTS[filename];
    if (!source) {
      // Missing screenshot — return null to render nothing (no visual
      // break). Alt-text conservat pentru screen readers / a11y in
      // dev logging daca vom activa vreodata.
      return null;
    }
    return (
      <Image
        key={node.key}
        source={source}
        style={{ width: '100%', maxWidth: 380, aspectRatio: undefined, marginVertical: 8, borderRadius: 8, alignSelf: 'center' }}
        resizeMode="contain"
        accessibilityLabel={alt}
      />
    );
  },
};

async function loadChapterContent(chapter) {
  if (!Asset || !FileSystem) {
    return '# Manualul nu poate fi afișat\n\nModulele necesare (expo-asset, expo-file-system) nu sunt disponibile. Rebuild aplicația cu ultimele dependințe.';
  }
  try {
    const asset = Asset.fromModule(chapter.file);
    await asset.downloadAsync();
    const uri = asset.localUri || asset.uri;
    // SDK 54 File API (preferred)
    if (FileSystem.File) {
      const f = new FileSystem.File(uri);
      return await f.text();
    }
    // Legacy fallback
    if (FileSystem.readAsStringAsync) {
      return await FileSystem.readAsStringAsync(uri);
    }
    return '# Manual indisponibil\n\nAPI-ul de fișiere nu e disponibil.';
  } catch (e) {
    return `# Eroare la încărcare\n\n${e.message || 'Necunoscută'}`;
  }
}

// Group chapters by section for the index view. Preserves declaration
// order within each section.
function groupBySection(chapters) {
  const map = new Map();
  for (const ch of chapters) {
    if (!map.has(ch.section)) map.set(ch.section, []);
    map.get(ch.section).push(ch);
  }
  return Array.from(map.entries()).map(([section, items]) => ({ section, items }));
}

export default function ManualModal({ visible, onClose, navigation }) {
  const [currentId, setCurrentId] = useState(null); // null = index view
  const [content, setContent] = useState('');
  const [loading, setLoading] = useState(false);
  const [query, setQuery] = useState('');
  const insets = useSafeAreaInsets();

  // Reset state on close.
  useEffect(() => {
    if (!visible) {
      setCurrentId(null);
      setContent('');
      setQuery('');
    }
  }, [visible]);

  const currentChapter = currentId ? CHAPTERS.find(c => c.id === currentId) : null;

  // Load chapter content when currentId changes.
  useEffect(() => {
    if (!currentChapter) return;
    let cancelled = false;
    setLoading(true);
    setContent('');
    loadChapterContent(currentChapter).then(text => {
      if (!cancelled) {
        setContent(text);
        setLoading(false);
      }
    });
    return () => { cancelled = true; };
  }, [currentId]);

  // Handle markdown link taps.
  const handleLinkPress = (url) => {
    if (!url) return false;
    // 1. Deep-link to a route: app://navigate/<RouteName>
    if (url.startsWith('app://navigate/')) {
      const route = url.replace('app://navigate/', '').split('?')[0].split('#')[0];
      onClose?.();
      // Delay slightly so the modal has time to unmount before nav fires.
      setTimeout(() => {
        try { navigation?.navigate?.(route); } catch {}
      }, 150);
      return false;
    }
    // 2. In-manual chapter link: ./NN_name.md or NN_name.md
    const chapterMatch = url.match(/(?:^|\/)(\d{2})_[^/]*\.md/);
    if (chapterMatch) {
      const targetId = chapterMatch[1];
      if (CHAPTERS.some(c => c.id === targetId)) {
        setCurrentId(targetId);
        return false;
      }
    }
    // 3. External URL — open in browser
    if (url.startsWith('http')) {
      Linking.openURL(url).catch(() => {
        Alert.alert('Nu am putut deschide linkul');
      });
      return false;
    }
    return false;
  };

  const filteredGroups = useMemo(() => {
    const needle = query.trim().toLowerCase();
    const filtered = needle
      ? CHAPTERS.filter(c =>
          c.title.toLowerCase().includes(needle) ||
          c.section.toLowerCase().includes(needle))
      : CHAPTERS;
    return groupBySection(filtered);
  }, [query]);

  return (
    <Modal
      visible={visible}
      transparent={false}
      animationType="slide"
      onRequestClose={() => {
        if (currentId) setCurrentId(null);
        else onClose?.();
      }}
    >
      <View style={[styles.container, { paddingTop: insets.top }]}>
        {/* Header */}
        <View style={styles.header}>
          <TouchableOpacity
            style={styles.headerBtn}
            onPress={() => {
              if (currentId) setCurrentId(null);
              else onClose?.();
            }}
            activeOpacity={0.7}
          >
            <Svg width={22} height={22} viewBox="0 0 24 24" fill="none">
              {currentId ? (
                <Path d="M19 12H5m0 0l7-7m-7 7l7 7"
                  stroke={colors.textPrimary} strokeWidth={2}
                  strokeLinecap="round" strokeLinejoin="round" />
              ) : (
                <Path d="M18 6L6 18M6 6l12 12"
                  stroke={colors.textPrimary} strokeWidth={2}
                  strokeLinecap="round" strokeLinejoin="round" />
              )}
            </Svg>
          </TouchableOpacity>
          <Text style={styles.headerTitle} numberOfLines={1}>
            {currentChapter ? currentChapter.title : 'Ghid AmBilet'}
          </Text>
          {currentId ? (
            <TouchableOpacity
              style={styles.headerBtn}
              onPress={() => setCurrentId(null)}
              activeOpacity={0.7}
            >
              <Svg width={22} height={22} viewBox="0 0 24 24" fill="none">
                <Path d="M4 6h16M4 12h16M4 18h16"
                  stroke={colors.textPrimary} strokeWidth={2}
                  strokeLinecap="round" strokeLinejoin="round" />
              </Svg>
            </TouchableOpacity>
          ) : (
            <View style={styles.headerBtn} />
          )}
        </View>

        {/* Index view */}
        {!currentId && (
          <>
            <View style={styles.searchWrap}>
              <Svg width={16} height={16} viewBox="0 0 24 24" fill="none">
                <Path d="M11 19a8 8 0 100-16 8 8 0 000 16zM21 21l-4.35-4.35"
                  stroke={colors.textTertiary} strokeWidth={2}
                  strokeLinecap="round" strokeLinejoin="round" />
              </Svg>
              <TextInput
                style={styles.searchInput}
                placeholder="Caută în manual"
                placeholderTextColor={colors.textTertiary}
                value={query}
                onChangeText={setQuery}
                autoCorrect={false}
                autoCapitalize="none"
                returnKeyType="search"
              />
              {query.length > 0 && (
                <TouchableOpacity onPress={() => setQuery('')} activeOpacity={0.7}>
                  <Svg width={16} height={16} viewBox="0 0 24 24" fill="none">
                    <Path d="M18 6L6 18M6 6l12 12"
                      stroke={colors.textTertiary} strokeWidth={2}
                      strokeLinecap="round" strokeLinejoin="round" />
                  </Svg>
                </TouchableOpacity>
              )}
            </View>

            <ScrollView contentContainerStyle={styles.indexContent}>
              {filteredGroups.length === 0 && (
                <View style={styles.emptyState}>
                  <Text style={styles.emptyText}>Nimic pentru „{query.trim()}"</Text>
                </View>
              )}
              {filteredGroups.map(group => (
                <View key={group.section} style={styles.indexSection}>
                  <Text style={styles.indexSectionTitle}>{group.section}</Text>
                  {group.items.map(ch => (
                    <TouchableOpacity
                      key={ch.id}
                      style={styles.indexRow}
                      onPress={() => setCurrentId(ch.id)}
                      activeOpacity={0.7}
                    >
                      <Text style={styles.indexRowNum}>{ch.id}</Text>
                      <Text style={styles.indexRowTitle} numberOfLines={2}>{ch.title}</Text>
                      <Svg width={18} height={18} viewBox="0 0 24 24" fill="none">
                        <Path d="M9 18l6-6-6-6"
                          stroke={colors.textTertiary} strokeWidth={2}
                          strokeLinecap="round" strokeLinejoin="round" />
                      </Svg>
                    </TouchableOpacity>
                  ))}
                </View>
              ))}
              <View style={{ height: 40 }} />
            </ScrollView>
          </>
        )}

        {/* Chapter reader */}
        {currentId && (
          <ScrollView contentContainerStyle={styles.readerContent}>
            {loading ? (
              <View style={styles.loadingWrap}>
                <ActivityIndicator size="large" color={colors.purple} />
                <Text style={styles.loadingText}>Se încarcă capitolul…</Text>
              </View>
            ) : Markdown ? (
              <Markdown
                onLinkPress={handleLinkPress}
                style={markdownStyles}
                rules={markdownRules}
              >
                {content}
              </Markdown>
            ) : (
              <Text style={styles.fallbackText}>{content}</Text>
            )}
            <View style={{ height: 40 }} />
          </ScrollView>
        )}
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 12,
    paddingVertical: 10,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
    backgroundColor: colors.surface,
  },
  headerBtn: {
    width: 40, height: 40,
    borderRadius: 20,
    alignItems: 'center', justifyContent: 'center',
  },
  headerTitle: {
    flex: 1,
    fontSize: 16,
    fontWeight: '700',
    color: colors.textPrimary,
    textAlign: 'center',
    paddingHorizontal: 8,
  },
  searchWrap: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginHorizontal: 16,
    marginTop: 12,
    marginBottom: 4,
    paddingHorizontal: 12,
    paddingVertical: 10,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.surface,
  },
  searchInput: {
    flex: 1,
    fontSize: 14,
    color: colors.textPrimary,
    padding: 0,
  },
  indexContent: {
    paddingBottom: 20,
  },
  emptyState: {
    padding: 32,
    alignItems: 'center',
  },
  emptyText: {
    color: colors.textTertiary,
    fontSize: 14,
  },
  indexSection: {
    marginTop: 20,
    marginHorizontal: 16,
  },
  indexSectionTitle: {
    fontSize: 12,
    fontWeight: '700',
    color: colors.textSecondary,
    letterSpacing: 0.5,
    marginBottom: 8,
    textTransform: 'uppercase',
  },
  indexRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    backgroundColor: colors.surface,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: colors.border,
    paddingVertical: 12,
    paddingHorizontal: 14,
    marginBottom: 6,
  },
  indexRowNum: {
    width: 28,
    fontSize: 12,
    fontWeight: '700',
    color: colors.purple,
    fontVariant: ['tabular-nums'],
  },
  indexRowTitle: {
    flex: 1,
    fontSize: 14,
    fontWeight: '600',
    color: colors.textPrimary,
  },
  readerContent: {
    padding: 20,
  },
  loadingWrap: {
    padding: 40,
    alignItems: 'center',
    gap: 12,
  },
  loadingText: {
    fontSize: 13,
    color: colors.textTertiary,
  },
  fallbackText: {
    fontSize: 14,
    color: colors.textPrimary,
    fontFamily: 'monospace',
  },
});

// Markdown styles — kept close to the app theme (colors.purple for
// headings & links, colors.border for hairlines, tabular numerals for
// code + tables).
const markdownStyles = StyleSheet.create({
  body: { color: colors.textPrimary, fontSize: 15, lineHeight: 22 },
  heading1: { color: colors.purple, fontSize: 24, fontWeight: '800', marginTop: 12, marginBottom: 8 },
  heading2: { color: colors.textPrimary, fontSize: 20, fontWeight: '700', marginTop: 20, marginBottom: 8 },
  heading3: { color: colors.textPrimary, fontSize: 17, fontWeight: '700', marginTop: 16, marginBottom: 6 },
  heading4: { color: colors.textPrimary, fontSize: 15, fontWeight: '700', marginTop: 12, marginBottom: 4 },
  paragraph: { marginBottom: 10, color: colors.textPrimary },
  strong: { fontWeight: '700', color: colors.textPrimary },
  em: { fontStyle: 'italic' },
  link: { color: colors.purple, textDecorationLine: 'underline' },
  blockquote: {
    backgroundColor: colors.background,
    borderLeftWidth: 4,
    borderLeftColor: colors.purple,
    paddingHorizontal: 12,
    paddingVertical: 8,
    marginVertical: 8,
  },
  code_inline: {
    backgroundColor: colors.background,
    borderRadius: 4,
    paddingHorizontal: 4,
    fontFamily: 'monospace',
    fontSize: 13,
  },
  code_block: {
    backgroundColor: colors.background,
    borderRadius: 8,
    padding: 12,
    fontFamily: 'monospace',
    fontSize: 13,
  },
  fence: {
    backgroundColor: colors.background,
    borderRadius: 8,
    padding: 12,
    fontFamily: 'monospace',
    fontSize: 13,
  },
  bullet_list: { marginBottom: 8 },
  ordered_list: { marginBottom: 8 },
  list_item: { marginBottom: 4 },
  hr: { backgroundColor: colors.border, height: 1, marginVertical: 16 },
  table: { borderColor: colors.border, borderWidth: 1, borderRadius: 8 },
  thead: { backgroundColor: colors.background },
  th: { padding: 8, fontWeight: '700', color: colors.textPrimary },
  tr: { borderBottomColor: colors.border, borderBottomWidth: 1 },
  td: { padding: 8, color: colors.textPrimary },
  image: { marginVertical: 8 },
});
