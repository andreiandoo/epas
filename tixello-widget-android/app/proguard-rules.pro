# Widget-urile si receiver-ele sunt instantiate prin reflectie de framework,
# din numele scris in manifest — fara asta, release-ul le sterge.
-keep class com.tixello.widget.** { *; }
