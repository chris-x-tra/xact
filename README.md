# xact

chrissie ^ x-tra-designs 07.09.2026

Fügt Für Markdown-Dateien einen Eintrag "Als PDF exportieren" 
im Filebrowser unter Rechtsklick-Menu / Drei-Punkte-Menu hinzu.

Es ist eine spezielle Anpassung erst mal nur für (DIN-)Briefe:
- soft_break ist <br>
- Leerzeilen können mit dem Pilgrim-Zeichen ¶ eingegeben werden
- Seitenumbruch kann mit dem Zeichen Pagebreak ⎘  eingegeben werden
- Diese Zeichen sollten einmal via Nextcloud Text Editor eingegeben werden können: Oben zwei Knöpfe in der Leiste
- Änderung H3: normal, H4: center,  H5: rechtsbündig, H6: kleine Schrift, hier braucht es noch ein css für den Nextcloud Text Editor
- es wird in der Fusszeile Seite 1/x hinzugefügt
- es werden Faltmarken hinzugefügt
- Diese Funktionen müssen einmal in xact konfigurierbar sein, genauso wie die Papiergröße/Ränder. 

Die Logik wurde dafür 1:1 aus dem Projekt md2pdf 
übernommen (League CommonMark + mpdf), nur Namespace/Appname 
angepasst und unbenutzte IConfig-Injection entfernt.

Getestet gegen die `@nextcloud/files`-API, wie sie ab Nextcloud 28+
verwendet wird (registerFileAction). Kompatibilitätsbereich in `info.xml`
auf 33–35 gesetzt.

**Wichtig – Dateiliste aktualisiert sich nicht von selbst:** Die alte
`window.OCA.Files.App.fileList.reload()`-API (aus md2pdf übernommen) gibt
es in der neuen Vue-Files-App nicht mehr. Der aktuelle, offizielle Weg ist
der Event-Bus (`@nextcloud/event-bus`) mit einem echten `Node`-Objekt:

```js
import { emit } from '@nextcloud/event-bus'
emit('files:node:created', node)
```

Da der PHP-Endpoint nur `{ fileId, name }` zurückgibt, holen wir den
frisch erzeugten Node per WebDAV-PROPFIND nach (gleiches Muster wie
Nextcloud selbst z. B. in `apps/files_sharing/src/services/WebdavClient.ts`
verwendet) und emitten erst dann das Event. Das bringt `webdav` als
zusätzliche Abhängigkeit mit ins Bundle (spürbar größer, ~245 statt
~103 KB) — für eine "minimale" App ein bewusster Trade-off gegen
zuverlässiges Live-Update der Dateiliste.

**Wichtig – exec()-Aufrufkonvention hat sich geändert:** Nextcloud hat die
Signatur von `exec()` umgestellt (siehe `@nextcloud/files`-CHANGELOG):

```
alt:  action.exec(node, view, dir)
neu:  action.exec({ nodes, view, folder, contents })
```

Die Core-Files-App ruft unsere registrierte Aktion nach IHRER eigenen,
serverseitig ausgelieferten Version auf – unabhängig davon, welche
`@nextcloud/files`-Version wir selbst gebündelt haben. Deshalb extrahiert
`src/main.js` den eigentlichen `Node` robust über eine kleine
`extractNodes()`-Hilfsfunktion, die sowohl die alte Form (Node oder
Node[]) als auch die neue Form (`{ nodes, ... }`) versteht.

**Wichtig – Versionsfalle:** `@nextcloud/files` v4.x ist für Nextcloud 33+,
v3.x nur bis NC 32 (siehe Kompatibilitätstabelle im npm-Paket). In v4 wurde
die alte `FileAction`-Klasse entfernt (`new FileAction({...})`) und durch
ein reines Objekt ersetzt, weil `instanceof`-Prüfungen über App-Bundle-
Grenzen hinweg nicht funktionieren (jede App bündelt ihre eigene Kopie der
Bibliothek). Wichtiger noch: die interne Registry ist komplett anders –
v3 schreibt nach `window._nc_fileactions` (Array), v4 nach
`window._nc_files_scope.v4_0` (versionierter, geteilter Namespace). Läuft
man mit v3 gegen einen NC-33+-Server, registriert sich die Aktion
"erfolgreich" in der alten, von der Core-Files-App aber gar nicht mehr
gelesenen Struktur – kein Fehler, aber der Menüpunkt bleibt unsichtbar.
Diese App nutzt daher `@nextcloud/files: ^4.0.0` mit reinem Objekt statt
Klasse.

Der JS-Build nutzt bewusst nur reines Vite (kein `@nextcloud/vite-config`),
da dieses Paket intern `vite-plugin-dts` lädt, das mit manchen
TypeScript/Vite-Versionskombinationen crasht (auch wenn man gar keine
Types generieren will). Für eine App dieser Größe reicht ein simples
IIFE-Bundle, alle Abhängigkeiten (inkl. `@nextcloud/files`) landen mit
im Bundle.

## JS-Build

```bash
npm install
npm run build
```

Das erzeugt `js/xact-main.js`, passend zum `Util::addScript('xact', 'xact-main')`-Aufruf
im Listener.

## PHP-Dependencies (composer)

```bash
composer install --no-dev --optimize-autoloader
```

Erzeugt alles in `vendor/` neu

## Installation

1. dieses Repository mit git clonen
2. `npm install && npm run build` im Repo ausführen
   `composer install` im Repo ausführen
3. auf dem Server im Nextcloud-Verzeichnis ein Verzeichnis app/xact erzeugen
   Folgende Verzeichnisse nach app/xact/ kopieren, z. B. mit rsnc
   js lib appinfo img vendor
   Beispiel:
```
   rsync -avz --delete js lib appinfo img vendor chrissie@example.com:/var/customers/webs/user99/nextcloud/apps/xact/
```
3. In den Nextcloud-Admin-Einstellungen unter "Apps" die App "xact"
   aktivieren (oder `occ app:enable xact`).
4. Nextcloud-Seite ohne Caching neu laden, Rechtsklick auf eine .md-Datei:
   "Als PDF exportieren" erscheint, beim Anklicken wird ein PDF ertellt.

