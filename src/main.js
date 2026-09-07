import { registerFileAction } from '@nextcloud/files'
import { getClient, getDefaultPropfind, resultToNode, getRootPath } from '@nextcloud/files/dav'
import { emit } from '@nextcloud/event-bus'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

// Ab @nextcloud/files v4 (Nextcloud 33+) werden Aktionen als reines Objekt
// registriert, nicht mehr als `new FileAction({...})`-Instanz. Klassen
// funktionieren über App-Bundle-Grenzen hinweg nicht zuverlässig
// (instanceof-Prüfungen scheitern, da jede App ihre eigene Kopie der
// Bibliothek bündelt), daher hat @nextcloud/files das in v4 entfernt.
//
// Zusätzlich hat Nextcloud die exec()-Aufrufkonvention zwischenzeitlich
// geändert:
//   alt:  action.exec(node, view, dir)
//   neu:  action.exec({ nodes, view, folder, contents })
// (siehe @nextcloud/files CHANGELOG). Die Core-Files-App ruft unsere
// registrierte Aktion nach IHRER eigenen, serverseitig ausgelieferten
// Version auf — unabhängig davon, welche @nextcloud/files-Version wir
// selbst gebündelt haben. Daher hier robust gegen beide Formen.
function extractNodes(arg) {
	if (Array.isArray(arg)) {
		return arg
	}
	if (arg && Array.isArray(arg.nodes)) {
		return arg.nodes
	}
	return arg ? [arg] : []
}

// window.OCA.Files.App.fileList existiert in der neuen Vue-Files-App nicht
// mehr (das war die alte jQuery-Files-App-API). Der offizielle, aktuelle
// Weg, die Files-App über einen neuen/geänderten Node zu informieren, ist
// der Event-Bus mit einem echten Node-Objekt — genau wie es Nextcloud
// intern z. B. nach dem Anlegen eines Freigabe-Links macht (Core-Vorbild:
// apps/files_sharing/src/services/WebdavClient.ts, fetchNode()).
const davClient = getClient()
const davRoot = getRootPath()

async function fetchCreatedNode(path) {
	const result = await davClient.stat(`${davRoot}${path}`, {
		details: true,
		data: getDefaultPropfind(),
	})
	return resultToNode(result.data, davRoot)
}

registerFileAction({
	id: 'xact-md2pdf',

	displayName: () => t('xact', 'Als PDF exportieren'),

	iconSvgInline: () => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="currentColor" d="M6 2a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6H6zm7 1.5L18.5 9H13V3.5zM8 13h1.5a1.5 1.5 0 0 1 0 3H8.75v1.5H8V13zm.75 2h.75a.5.5 0 0 0 0-1h-.75v1zM12 13h1.4c.9 0 1.6.8 1.6 2s-.7 2-1.6 2H12v-4zm.75.75v2.5h.65c.45 0 .85-.5.85-1.25s-.4-1.25-.85-1.25h-.65zM16 13h2v.75h-1.25v.875H18v.75h-1.25V17H16v-4z"/></svg>',

	// Nur für Markdown-Dateien anzeigen
//	enabled: (arg) => {
//		const nodes = extractNodes(arg)
//		return nodes.length > 0 && nodes.every((node) => ['text/markdown', 'text/x-markdown'].includes(node?.mime))
//	},

	        // Für jede Datei/jeden Ordner anzeigen; hier ggf. einschränken
        enabled: () => true,


	order: 100,

	async exec(arg) {
		const node = extractNodes(arg)[0]
		const folder = arg?.folder
		try {
			const id = node?.id ?? node?.fileid
			const { data } = await axios.post(generateUrl('/apps/xact/convert/{fileId}', { fileId: id }))
			if (data.status !== 'success') {
				throw new Error(data.data?.message ?? 'unknown error')
			}

			// Neue PDF per WebDAV nachladen und die Files-App per Event-Bus
			// informieren, damit sie sofort in der Liste auftaucht.
			const parentPath = (folder?.path ?? node?.dirname ?? '/').replace(/\/+$/, '')
			try {
				const newNode = await fetchCreatedNode(`${parentPath}/${data.data.name}`)
				emit('files:node:created', newNode)
			} catch (fetchErr) {
				// eslint-disable-next-line no-console
				console.warn('xact: neue PDF per WebDAV nachladen fehlgeschlagen, Liste evtl. nicht sofort aktuell', fetchErr)
			}

			window.OC?.Notification?.showTemporary
				? window.OC.Notification.showTemporary(t('xact', '{name} wurde erstellt', { name: data.data.name }))
				: alert('xact: ' + data.data.name + ' erstellt')
			return null
		} catch (e) {
			// eslint-disable-next-line no-console
			console.error('xact: PDF-Export fehlgeschlagen', e)
			const message = e?.response?.data?.data?.message ?? e.message
			window.OC?.Notification?.showTemporary
				? window.OC.Notification.showTemporary(t('xact', 'PDF-Export fehlgeschlagen: {message}', { message }))
				: alert('xact: PDF-Export fehlgeschlagen: ' + message)
			return null
		}
	},
})
