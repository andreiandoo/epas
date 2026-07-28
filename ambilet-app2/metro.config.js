// Metro bundler config — extends Expo defaults to bundle .md files as
// static assets. Enables the in-app manual viewer (src/screens/ManualScreen)
// to `require('../../docs/manual/XX_name.md')` and read the chapter text
// via expo-asset + expo-file-system at runtime.
const { getDefaultConfig } = require('expo/metro-config');

const config = getDefaultConfig(__dirname);

// Add `md` to asset extensions so metro bundles our manual files without
// trying to parse them as JS source.
if (!config.resolver.assetExts.includes('md')) {
  config.resolver.assetExts.push('md');
}

module.exports = config;
