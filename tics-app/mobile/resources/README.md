# Iconițele aplicației

Sursele, copiate din `tics-app/elements/icons/`:

| fișier | rol |
|---|---|
| `icon-ink-1024.svg` | iconița aplicației — cea de pe telefon |
| `icon-white-1024.svg` | varianta pe fundal deschis; din ea e extrasă glifa din `src/design/ticsMark.ts` |
| `android-foreground.svg` / `android-background.svg` | straturile pentru iconița adaptivă Android |

## De ce nu sunt generate automat aici

`@capacitor/assets` are nevoie de **PNG 1024×1024**, nu de SVG, iar pe mașina
asta nu există niciun rasterizator (`sharp` lipsește, nu e instalat nici
ImageMagick). Conversia trebuie făcută o dată, manual — după aceea generarea e
un singur pas și se poate repeta oricând.

## Pași

1. Exportă `icon-ink-1024.svg` ca PNG 1024×1024, în `resources/icon.png`
   (Figma, Inkscape, sau orice convertor SVG→PNG).
2. Opțional, pentru iconița adaptivă Android: exportă și
   `android-foreground.svg` → `resources/android/icon-foreground.png` și
   `android-background.svg` → `resources/android/icon-background.png`.
3. Rulează:

   ```bash
   npx @capacitor/assets generate --android
   ```

4. Construiește APK-ul. Iconița **nu vine prin OTA** — bundle-ul conține doar
   partea web; tot ce ține de `android/` cere build nou.

Numele aplicației e deja `Tics`, în `capacitor.config.ts` și în
`android/app/src/main/res/values/strings.xml`.
