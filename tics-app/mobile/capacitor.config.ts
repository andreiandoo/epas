import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'ro.tixello.app',
  appName: 'Tixello',
  webDir: 'dist',
  android: {
    allowMixedContent: true,
    backgroundColor: '#0A0711',
  },
  plugins: {
    Keyboard: {
      resize: 'none' as never,
    },
  },
};

export default config;
